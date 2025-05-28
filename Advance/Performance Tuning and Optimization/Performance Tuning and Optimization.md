# MySQL Performance Tuning and Optimization - Complete Guide

## Table of Contents
1. [Identifying and Resolving Performance Bottlenecks](#bottlenecks)
2. [Optimizing Database Schema and Queries](#optimization)
3. [Slow Query Log and Error Log Analysis](#logs)
4. [InnoDB Engine Status and Configuration](#innodb)
5. [Monitoring and Analyzing Database Performance](#monitoring)
6. [Kali Linux Performance Tools](#kali)
7. [Laravel Performance Optimization](#laravel)

---

## 1. Identifying and Resolving Performance Bottlenecks {#bottlenecks}

### Understanding Performance Bottlenecks

Performance bottlenecks in MySQL can occur at various levels:

- **Hardware Level**: CPU, Memory, Disk I/O, Network
- **Operating System Level**: File system, kernel parameters
- **MySQL Server Level**: Configuration, engine settings
- **Database Level**: Schema design, indexing
- **Query Level**: SQL optimization, execution plans
- **Application Level**: Connection pooling, caching

### Performance Diagnosis Methodology

#### Step 1: Identify the Problem
```sql
-- Check current connections and processes
SHOW PROCESSLIST;

-- View current status variables
SHOW STATUS;

-- Check engine status
SHOW ENGINE INNODB STATUS;

-- Examine slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;
```

#### Step 2: Gather Performance Metrics
```sql
-- Key performance indicators
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Threads_running';
SHOW STATUS LIKE 'Slow_queries';
SHOW STATUS LIKE 'Questions';
SHOW STATUS LIKE 'Uptime';

-- Calculate queries per second
SELECT VARIABLE_VALUE/UPTIME AS QPS 
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'QUESTIONS'
CROSS JOIN (
    SELECT VARIABLE_VALUE AS UPTIME 
    FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
    WHERE VARIABLE_NAME = 'UPTIME'
) t;
```

#### Step 3: Performance Analysis Query
```sql
-- Comprehensive performance overview
SELECT 
    'Connections' as Metric,
    VARIABLE_VALUE as Value
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Threads_connected'

UNION ALL

SELECT 
    'Running Threads',
    VARIABLE_VALUE
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Threads_running'

UNION ALL

SELECT 
    'Slow Queries',
    VARIABLE_VALUE
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Slow_queries'

UNION ALL

SELECT 
    'Total Queries',
    VARIABLE_VALUE
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Questions';
```

### Common Performance Bottlenecks and Solutions

#### CPU Bottlenecks
**Symptoms:**
- High CPU usage (>80% consistently)
- Many threads in "running" state
- Long execution times for simple queries

**Solutions:**
```sql
-- Optimize CPU-intensive queries
-- Enable query cache (MySQL 5.7 and earlier)
SET GLOBAL query_cache_size = 268435456; -- 256MB
SET GLOBAL query_cache_type = ON;

-- Optimize sort operations
SET GLOBAL sort_buffer_size = 2097152; -- 2MB per connection
SET GLOBAL read_buffer_size = 131072;   -- 128KB
```

#### Memory Bottlenecks
**Symptoms:**
- High swap usage
- Frequent disk I/O for caching
- OOM (Out of Memory) errors

**Solutions:**
```sql
-- Optimize InnoDB buffer pool
SET GLOBAL innodb_buffer_pool_size = 2147483648; -- 2GB (70-80% of RAM)

-- Configure key buffer for MyISAM
SET GLOBAL key_buffer_size = 268435456; -- 256MB

-- Optimize table cache
SET GLOBAL table_open_cache = 2000;
SET GLOBAL table_definition_cache = 1400;
```

#### Disk I/O Bottlenecks
**Symptoms:**
- High disk utilization
- Long wait times for disk operations
- Slow SELECT and INSERT operations

**Solutions:**
```sql
-- Enable InnoDB file-per-table
SET GLOBAL innodb_file_per_table = ON;

-- Optimize log file size
-- SET in my.cnf: innodb_log_file_size = 256M

-- Configure I/O capacity
SET GLOBAL innodb_io_capacity = 200;
SET GLOBAL innodb_io_capacity_max = 2000;
```

#### Network Bottlenecks
**Symptoms:**
- High network latency
- Connection timeouts
- Slow data transfer

**Solutions:**
```sql
-- Optimize connection handling
SET GLOBAL max_connections = 500;
SET GLOBAL thread_cache_size = 50;

-- Configure timeouts
SET GLOBAL wait_timeout = 3600;
SET GLOBAL interactive_timeout = 3600;
```

### Performance Bottleneck Analysis Script

```bash
#!/bin/bash
# mysql_bottleneck_analyzer.sh

MYSQL_USER="root"
MYSQL_PASS="password"
MYSQL_HOST="localhost"

echo "=== MySQL Performance Bottleneck Analysis ==="
echo "Timestamp: $(date)"
echo

# System resource usage
echo "=== System Resources ==="
echo "CPU Usage:"
top -bn1 | grep "Cpu(s)" | awk '{print $2 + $4"%"}'

echo "Memory Usage:"
free -h | awk 'NR==2{printf "Memory Usage: %s/%s (%.2f%%)\n", $3,$2,$3*100/$2 }'

echo "Disk Usage:"
df -h | awk '$NF=="/"{printf "Disk Usage: %d/%dGB (%s)\n", $3,$2,$5}'

echo

# MySQL process information
echo "=== MySQL Process Information ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "
SELECT 
    COUNT(*) as Total_Connections,
    SUM(CASE WHEN COMMAND != 'Sleep' THEN 1 ELSE 0 END) as Active_Connections,
    SUM(CASE WHEN COMMAND = 'Sleep' THEN 1 ELSE 0 END) as Sleeping_Connections
FROM INFORMATION_SCHEMA.PROCESSLIST;
"

echo

# Performance metrics
echo "=== Key Performance Metrics ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "
SELECT 
    VARIABLE_NAME as Metric,
    VARIABLE_VALUE as Value
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME IN (
    'Slow_queries',
    'Questions',
    'Uptime',
    'Threads_connected',
    'Threads_running',
    'Created_tmp_disk_tables',
    'Created_tmp_tables',
    'Key_read_requests',
    'Key_reads'
)
ORDER BY VARIABLE_NAME;
"

echo

# Top slow queries
echo "=== Recent Slow Queries ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "
SELECT 
    start_time,
    query_time,
    lock_time,
    rows_sent,
    rows_examined,
    LEFT(sql_text, 100) as query_preview
FROM mysql.slow_log 
ORDER BY start_time DESC 
LIMIT 5;
" 2>/dev/null || echo "Slow log not enabled or accessible"

echo

# InnoDB status summary
echo "=== InnoDB Status Summary ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "SHOW ENGINE INNODB STATUS\G" | grep -A 5 -B 5 "DEADLOCK\|LOCK WAIT\|PENDING"
```

---

## 2. Optimizing Database Schema and Queries {#optimization}

### Database Schema Optimization

#### Proper Data Types Selection
```sql
-- Use appropriate data types for better performance
-- Bad: Using VARCHAR for numeric data
CREATE TABLE bad_example (
    id VARCHAR(50),
    age VARCHAR(10),
    price VARCHAR(20)
);

-- Good: Using proper data types
CREATE TABLE good_example (
    id INT AUTO_INCREMENT PRIMARY KEY,
    age TINYINT UNSIGNED,
    price DECIMAL(10,2)
);
```

#### Normalization vs Denormalization
```sql
-- Normalized approach (better for write-heavy workloads)
CREATE TABLE users (
    id INT PRIMARY KEY,
    username VARCHAR(50),
    email VARCHAR(100)
);

CREATE TABLE user_profiles (
    user_id INT,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    bio TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Denormalized approach (better for read-heavy workloads)
CREATE TABLE users_denormalized (
    id INT PRIMARY KEY,
    username VARCHAR(50),
    email VARCHAR(100),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    bio TEXT,
    -- Cache frequently accessed data
    total_posts INT DEFAULT 0,
    last_login TIMESTAMP
);
```

### Index Optimization Strategies

#### Primary and Secondary Indexes
```sql
-- Effective primary key design
-- Bad: UUID as primary key (random, causes page splits)
CREATE TABLE orders_bad (
    id CHAR(36) PRIMARY KEY,
    customer_id INT,
    order_date DATETIME,
    total DECIMAL(10,2)
);

-- Good: Auto-increment integer primary key
CREATE TABLE orders_good (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE,
    customer_id INT,
    order_date DATETIME,
    total DECIMAL(10,2),
    INDEX idx_customer_date (customer_id, order_date),
    INDEX idx_uuid (uuid)
);
```

#### Composite Index Optimization
```sql
-- Create table for demonstration
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    product_id INT,
    sale_date DATE,
    amount DECIMAL(10,2),
    status ENUM('pending', 'completed', 'cancelled')
);

-- Effective composite indexes
-- Index for queries filtering by customer and date range
CREATE INDEX idx_customer_date ON sales (customer_id, sale_date);

-- Index for queries filtering by status and date
CREATE INDEX idx_status_date ON sales (status, sale_date);

-- Covering index for common report queries
CREATE INDEX idx_covering ON sales (customer_id, sale_date, amount, status);
```

#### Index Usage Analysis
```sql
-- Check index usage statistics
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'your_database'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Find unused indexes
SELECT 
    s.TABLE_SCHEMA,
    s.TABLE_NAME,
    s.INDEX_NAME,
    s.COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS s
LEFT JOIN INFORMATION_SCHEMA.INDEX_STATISTICS i 
    ON s.TABLE_SCHEMA = i.TABLE_SCHEMA 
    AND s.TABLE_NAME = i.TABLE_NAME 
    AND s.INDEX_NAME = i.INDEX_NAME
WHERE s.TABLE_SCHEMA = 'your_database'
    AND s.INDEX_NAME != 'PRIMARY'
    AND i.INDEX_NAME IS NULL;
```

### Query Optimization Techniques

#### Using EXPLAIN for Query Analysis
```sql
-- Basic EXPLAIN usage
EXPLAIN SELECT * FROM orders WHERE customer_id = 123;

-- Extended EXPLAIN with additional information
EXPLAIN EXTENDED 
SELECT o.id, o.total, c.name 
FROM orders o 
JOIN customers c ON o.customer_id = c.id 
WHERE o.order_date >= '2024-01-01';

-- JSON format for detailed analysis
EXPLAIN FORMAT=JSON 
SELECT * FROM products 
WHERE category_id IN (1, 2, 3) 
ORDER BY price DESC 
LIMIT 10;
```

#### Query Optimization Examples
```sql
-- Optimizing WHERE clauses
-- Bad: Function on indexed column
SELECT * FROM orders WHERE YEAR(order_date) = 2024;

-- Good: Range query
SELECT * FROM orders 
WHERE order_date >= '2024-01-01' 
    AND order_date < '2025-01-01';

-- Optimizing JOINs
-- Bad: Inefficient join without proper indexes
SELECT o.*, c.name 
FROM orders o, customers c 
WHERE o.customer_id = c.id 
    AND o.total > 100;

-- Good: Proper JOIN with indexes
SELECT o.id, o.total, c.name 
FROM orders o 
INNER JOIN customers c ON o.customer_id = c.id 
WHERE o.total > 100;

-- Optimizing subqueries
-- Bad: Correlated subquery
SELECT * FROM customers c 
WHERE (
    SELECT COUNT(*) FROM orders o 
    WHERE o.customer_id = c.id
) > 5;

-- Good: JOIN instead of subquery
SELECT DISTINCT c.* 
FROM customers c 
INNER JOIN (
    SELECT customer_id 
    FROM orders 
    GROUP BY customer_id 
    HAVING COUNT(*) > 5
) o ON c.id = o.customer_id;
```

#### Advanced Query Optimization
```sql
-- Use LIMIT for large result sets
-- Bad: Return all results
SELECT * FROM large_table WHERE condition = 'value';

-- Good: Use pagination
SELECT * FROM large_table 
WHERE condition = 'value' 
LIMIT 20 OFFSET 0;

-- Optimize COUNT queries
-- Bad: COUNT(*) on large tables
SELECT COUNT(*) FROM large_table WHERE date_field > '2024-01-01';

-- Good: Use approximate count for large datasets
SELECT table_rows 
FROM INFORMATION_SCHEMA.TABLES 
WHERE table_schema = 'database_name' 
    AND table_name = 'large_table';

-- Or use covering index
SELECT COUNT(indexed_column) FROM large_table 
WHERE indexed_column IS NOT NULL 
    AND date_field > '2024-01-01';
```

### Schema Optimization Script
```sql
-- Schema analysis and optimization recommendations
DELIMITER //
CREATE PROCEDURE AnalyzeSchemaPerformance(IN db_name VARCHAR(64))
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tbl_name VARCHAR(64);
    DECLARE cur CURSOR FOR 
        SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = db_name AND TABLE_TYPE = 'BASE TABLE';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Create temporary table for results
    CREATE TEMPORARY TABLE IF NOT EXISTS schema_analysis (
        table_name VARCHAR(64),
        issue_type VARCHAR(50),
        description TEXT,
        recommendation TEXT
    );

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO tbl_name;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Check for tables without primary key
        IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = db_name 
                AND TABLE_NAME = tbl_name 
                AND CONSTRAINT_NAME = 'PRIMARY'
        ) THEN
            INSERT INTO schema_analysis VALUES (
                tbl_name, 
                'Missing Primary Key', 
                'Table has no primary key defined',
                'Add an AUTO_INCREMENT PRIMARY KEY column'
            );
        END IF;

        -- Check for large VARCHAR columns
        INSERT INTO schema_analysis
        SELECT 
            tbl_name,
            'Large VARCHAR',
            CONCAT('Column ', COLUMN_NAME, ' has size ', CHARACTER_MAXIMUM_LENGTH),
            'Consider using TEXT for large content or reducing VARCHAR size'
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = db_name 
            AND TABLE_NAME = tbl_name
            AND DATA_TYPE = 'varchar'
            AND CHARACTER_MAXIMUM_LENGTH > 1000;

    END LOOP;
    CLOSE cur;

    -- Display results
    SELECT * FROM schema_analysis ORDER BY table_name, issue_type;
    DROP TEMPORARY TABLE schema_analysis;
END //
DELIMITER ;

-- Usage
CALL AnalyzeSchemaPerformance('your_database_name');
```

---

## 3. Slow Query Log and Error Log Analysis {#logs}

### Enabling and Configuring Slow Query Log

#### Configuration in my.cnf
```ini
[mysqld]
# Enable slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2.0

# Log queries not using indexes
log_queries_not_using_indexes = 1

# Log slow admin statements
log_slow_admin_statements = 1

# Log slave SQL thread slow queries
log_slow_slave_statements = 1

# Minimum examined rows to log
min_examined_row_limit = 100
```

#### Dynamic Configuration
```sql
-- Enable slow query log dynamically
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL slow_query_log_file = '/var/log/mysql/mysql-slow.log';
SET GLOBAL long_query_time = 2.0;
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Check current settings
SHOW VARIABLES LIKE '%slow%';
SHOW VARIABLES LIKE '%long_query_time%';
```

### Analyzing Slow Query Log

#### Using mysqldumpslow
```bash
# Basic analysis - top 10 slowest queries
mysqldumpslow -s t -t 10 /var/log/mysql/mysql-slow.log

# Most frequent queries
mysqldumpslow -s c -t 10 /var/log/mysql/mysql-slow.log

# Queries with most rows examined
mysqldumpslow -s r -t 10 /var/log/mysql/mysql-slow.log

# Combined analysis with details
mysqldumpslow -s at -t 10 -v /var/log/mysql/mysql-slow.log
```

#### Advanced Slow Log Analysis Script
```bash
#!/bin/bash
# slow_query_analyzer.sh

SLOW_LOG="/var/log/mysql/mysql-slow.log"
REPORT_FILE="/tmp/slow_query_report_$(date +%Y%m%d_%H%M%S).txt"

echo "MySQL Slow Query Analysis Report" > $REPORT_FILE
echo "Generated: $(date)" >> $REPORT_FILE
echo "Log file: $SLOW_LOG" >> $REPORT_FILE
echo "=================================" >> $REPORT_FILE
echo >> $REPORT_FILE

# Top 10 slowest queries by execution time
echo "Top 10 Slowest Queries by Execution Time:" >> $REPORT_FILE
echo "----------------------------------------" >> $REPORT_FILE
mysqldumpslow -s t -t 10 $SLOW_LOG >> $REPORT_FILE
echo >> $REPORT_FILE

# Top 10 most frequent slow queries
echo "Top 10 Most Frequent Slow Queries:" >> $REPORT_FILE
echo "-----------------------------------" >> $REPORT_FILE
mysqldumpslow -s c -t 10 $SLOW_LOG >> $REPORT_FILE
echo >> $REPORT_FILE

# Queries examining most rows
echo "Top 10 Queries by Rows Examined:" >> $REPORT_FILE
echo "--------------------------------" >> $REPORT_FILE
mysqldumpslow -s r -t 10 $SLOW_LOG >> $REPORT_FILE
echo >> $REPORT_FILE

# Summary statistics
echo "Summary Statistics:" >> $REPORT_FILE
echo "------------------" >> $REPORT_FILE
echo "Total slow queries: $(grep -c "Query_time:" $SLOW_LOG)" >> $REPORT_FILE
echo "Unique slow queries: $(mysqldumpslow $SLOW_LOG | grep -c "Count:")" >> $REPORT_FILE
echo "Date range: $(head -5 $SLOW_LOG | grep "Time:" | head -1) to $(tail -10 $SLOW_LOG | grep "Time:" | tail -1)" >> $REPORT_FILE

echo "Report generated: $REPORT_FILE"
```

#### Slow Query Log Analysis with pt-query-digest (Percona Toolkit)
```bash
# Install Percona Toolkit
sudo apt-get install percona-toolkit

# Comprehensive analysis
pt-query-digest /var/log/mysql/mysql-slow.log > slow_query_digest.txt

# Analysis with specific filters
pt-query-digest --filter '$event->{fingerprint} =~ m/SELECT.*FROM orders/' /var/log/mysql/mysql-slow.log

# Real-time analysis
pt-query-digest --processlist h=localhost,u=root,p=password

# Generate report for specific database
pt-query-digest --filter '$event->{db} eq "production_db"' /var/log/mysql/mysql-slow.log
```

### Error Log Analysis

#### Error Log Configuration
```ini
[mysqld]
# Error log configuration
log_error = /var/log/mysql/error.log
log_error_verbosity = 3

# Log warnings
log_warnings = 2
```

#### Common Error Patterns and Analysis
```bash
#!/bin/bash
# error_log_analyzer.sh

ERROR_LOG="/var/log/mysql/error.log"

echo "MySQL Error Log Analysis"
echo "========================"
echo "Log file: $ERROR_LOG"
echo

# Check for critical errors
echo "Critical Errors:"
echo "---------------"
grep -i "ERROR\|FATAL\|CRITICAL" $ERROR_LOG | tail -20
echo

# Connection issues
echo "Connection Issues:"
echo "-----------------"
grep -i "connection\|connect" $ERROR_LOG | tail -10
echo

# InnoDB errors
echo "InnoDB Errors:"
echo "-------------"
grep -i "innodb" $ERROR_LOG | tail -10
echo

# Replication errors
echo "Replication Errors:"
echo "------------------"
grep -i "slave\|replication\|binlog" $ERROR_LOG | tail -10
echo

# Performance warnings
echo "Performance Warnings:"
echo "--------------------"
grep -i "slow\|timeout\|wait" $ERROR_LOG | tail -10
echo

# Crash recovery
echo "Crash Recovery Events:"
echo "---------------------"
grep -i "crash\|recovery\|restart" $ERROR_LOG | tail -5
```

### Log Rotation and Management

#### Log Rotation Configuration
```bash
# /etc/logrotate.d/mysql
/var/log/mysql/*.log {
    daily
    missingok
    rotate 52
    compress
    notifempty
    create 644 mysql mysql
    postrotate
        if test -x /usr/bin/mysqladmin && \
           /usr/bin/mysqladmin ping &>/dev/null
        then
           /usr/bin/mysqladmin flush-logs
        fi
    endscript
}
```

#### Manual Log Management
```sql
-- Flush logs to rotate them
FLUSH LOGS;

-- Clear slow query log
TRUNCATE TABLE mysql.slow_log;

-- Reset slow query counters
FLUSH STATUS;
```

---

## 4. InnoDB Engine Status and Configuration {#innodb}

### InnoDB Status Analysis

#### Understanding SHOW ENGINE INNODB STATUS
```sql
-- Get comprehensive InnoDB status
SHOW ENGINE INNODB STATUS;

-- Parse specific sections
SHOW ENGINE INNODB STATUS\G
```

#### Key InnoDB Status Sections

**1. Background Thread Status**
```sql
-- Check InnoDB background threads
SELECT * FROM INFORMATION_SCHEMA.INNODB_METRICS 
WHERE NAME LIKE '%thread%' AND STATUS = 'enabled';
```

**2. Semaphores and Mutexes**
```sql
-- Monitor lock waits and contention
SELECT * FROM INFORMATION_SCHEMA.INNODB_METRICS 
WHERE NAME LIKE '%lock%' OR NAME LIKE '%latch%';
```

**3. Transactions and Deadlocks**
```sql
-- View current transactions
SELECT 
    trx_id,
    trx_state,
    trx_started,
    trx_requested_lock_id,
    trx_wait_started,
    trx_weight,
    trx_mysql_thread_id,
    trx_query
FROM INFORMATION_SCHEMA.INNODB_TRX;

-- Check for lock waits
SELECT 
    r.trx_id waiting_trx_id,
    r.trx_mysql_thread_id waiting_thread,
    r.trx_query waiting_query,
    b.trx_id blocking_trx_id,
    b.trx_mysql_thread_id blocking_thread,
    b.trx_query blocking_query
FROM INFORMATION_SCHEMA.INNODB_LOCK_WAITS w
INNER JOIN INFORMATION_SCHEMA.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
INNER JOIN INFORMATION_SCHEMA.INNODB_TRX r ON r.trx_id = w.requesting_trx_id;
```

### InnoDB Configuration Optimization

#### Buffer Pool Configuration
```sql
-- Buffer pool settings (70-80% of available RAM)
SET GLOBAL innodb_buffer_pool_size = 2147483648; -- 2GB

-- Multiple buffer pool instances for better concurrency
SET GLOBAL innodb_buffer_pool_instances = 8;

-- Buffer pool dump/restore for faster warmup
SET GLOBAL innodb_buffer_pool_dump_at_shutdown = ON;
SET GLOBAL innodb_buffer_pool_load_at_startup = ON;

-- Check buffer pool status
SHOW STATUS LIKE 'Innodb_buffer_pool%';
```

#### InnoDB Log Configuration
```ini
# my.cnf settings for InnoDB logs
[mysqld]
# Log file size (25% of buffer pool size)
innodb_log_file_size = 512M

# Number of log files
innodb_log_files_in_group = 2

# Log buffer size
innodb_log_buffer_size = 16M

# Flush method
innodb_flush_method = O_DIRECT

# Flush log at transaction commit
innodb_flush_log_at_trx_commit = 1
```

#### InnoDB I/O Configuration
```sql
-- Configure I/O capacity based on storage type
-- For SSD
SET GLOBAL innodb_io_capacity = 2000;
SET GLOBAL innodb_io_capacity_max = 4000;

-- For traditional HDD
SET GLOBAL innodb_io_capacity = 200;
SET GLOBAL innodb_io_capacity_max = 400;

-- Read/write threads
SET GLOBAL innodb_read_io_threads = 8;
SET GLOBAL innodb_write_io_threads = 8;

-- File format and row format
SET GLOBAL innodb_file_format = 'Barracuda';
SET GLOBAL innodb_file_per_table = ON;
```

### InnoDB Monitoring Queries

#### Buffer Pool Efficiency
```sql
-- Buffer pool hit ratio (should be > 99%)
SELECT 
  ROUND(
    (1 - (Innodb_buffer_pool_reads / Innodb_buffer_pool_read_requests)) * 100, 2
  ) AS buffer_pool_hit_ratio
FROM (
  SELECT 
    VARIABLE_VALUE AS Innodb_buffer_pool_reads
  FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
  WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads'
) t1
CROSS JOIN (
  SELECT 
    VARIABLE_VALUE AS Innodb_buffer_pool_read_requests
  FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
  WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'
) t2;
```

#### InnoDB Performance Metrics
```sql
-- Comprehensive InnoDB performance overview
SELECT 
    'Buffer Pool Hit Ratio' as Metric,
    CONCAT(
        ROUND(
            (1 - (
                (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads') /
                (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests')
            )) * 100, 2
        ), '%'
    ) as Value

UNION ALL

SELECT 
    'Buffer Pool Usage',
    CONCAT(
        ROUND(
            ((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_data') /
             (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total')) * 100, 2
        ), '%'
    )

UNION ALL

SELECT 
    'Log Waits',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_log_waits')

UNION ALL

SELECT 
    'Deadlocks',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_deadlocks')

UNION ALL

SELECT 
    'Rows Read',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_rows_read')

UNION ALL

SELECT 
    'Rows Inserted',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_rows_inserted')

UNION ALL

SELECT 
    'Rows Updated',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_rows_updated')

UNION ALL

SELECT 
    'Rows Deleted',
    (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_rows_deleted');
```

### InnoDB Troubleshooting

#### Deadlock Analysis
```sql
-- Enable InnoDB monitor for detailed deadlock info
SET GLOBAL innodb_print_all_deadlocks = ON;

-- Create procedure to analyze recent deadlocks
DELIMITER //
CREATE PROCEDURE AnalyzeDeadlocks()
BEGIN
    DECLARE deadlock_info TEXT;
    
    -- Get the latest InnoDB status
    SHOW ENGINE INNODB STATUS;
    
    -- Display deadlock information
    SELECT 
        'Check the error log for detailed deadlock information' as Note,
        'Enable innodb_print_all_deadlocks for full deadlock logging' as Recommendation;
END //
DELIMITER ;
```

#### Lock Contention Analysis
```sql
-- Monitor lock waits
SELECT 
    waiting.trx_mysql_thread_id AS waiting_thread,
    waiting.trx_query AS waiting_query,
    blocking.trx_mysql_thread_id AS blocking_thread,
    blocking.trx_query AS blocking_query,
    TIMESTAMPDIFF(SECOND, waiting.trx_wait_started, NOW()) AS wait_time_seconds
FROM INFORMATION_SCHEMA.INNODB_LOCK_WAITS w
INNER JOIN INFORMATION_SCHEMA.INNODB_TRX waiting ON waiting.trx_id = w.requesting_trx_id
INNER JOIN INFORMATION_SCHEMA.INNODB_TRX blocking ON blocking.trx_id = w.blocking_trx_id
ORDER BY wait_time_seconds DESC;
```

### InnoDB Optimization Script
```bash
#!/bin/bash
# innodb_optimizer.sh

MYSQL_USER="root"
MYSQL_PASS="password"
MYSQL_HOST="localhost"

echo "=== InnoDB Performance Optimization Analysis ==="
echo "Timestamp: $(date)"
echo

# Get system memory
TOTAL_RAM=$(free -b | awk 'NR==2{print $2}')
TOTAL_RAM_GB=$(echo "scale=2; $TOTAL_RAM/1024/1024/1024" | bc)

echo "System RAM: ${TOTAL_RAM_GB}GB"

# Current InnoDB configuration
echo "=== Current InnoDB Configuration ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "
SELECT 
    VARIABLE_NAME,
    VARIABLE_VALUE,
    CASE 
        WHEN VARIABLE_NAME = 'innodb_buffer_pool_size' THEN 
            CONCAT(ROUND(VARIABLE_VALUE/1024/1024/1024, 2), 'GB')
        WHEN VARIABLE_NAME LIKE '%_size%' AND VARIABLE_VALUE > 1024 THEN 
            CONCAT(ROUND(VARIABLE_VALUE/1024/1024, 2), 'MB')
        ELSE VARIABLE_VALUE
    END as Formatted_Value
FROM INFORMATION_SCHEMA.GLOBAL_VARIABLES 
WHERE VARIABLE_NAME IN (
    'innodb_buffer_pool_size',
    'innodb_buffer_pool_instances',
    'innodb_log_file_size',
    'innodb_log_buffer_size',
    'innodb_io_capacity',
    'innodb_io_capacity_max',
    'innodb_read_io_threads',
    'innodb_write_io_threads'
)
ORDER BY VARIABLE_NAME;
"

echo

# Buffer pool analysis
echo "=== Buffer Pool Analysis ==="
mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -e "
SELECT 
    'Buffer Pool Hit Ratio' as Metric,
    CONCAT(
        ROUND(
            (1 - (
                (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads') /
                NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'), 0)
            )) * 100, 2
        ), '%'
    ) as Value

UNION ALL

SELECT 
    'Buffer Pool Usage',
    CONCAT(
        ROUND(
            ((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_data') /
             NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total'), 0)) * 100, 2
        ), '%'
    )

UNION ALL

SELECT 
    'Dirty Pages Ratio',
    CONCAT(
        ROUND(
            ((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_dirty') /
             NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total'), 0)) * 100, 2
        ), '%'
    );
"

echo

# Recommendations
CURRENT_BUFFER_POOL=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_VARIABLES WHERE VARIABLE_NAME = 'innodb_buffer_pool_size'")
RECOMMENDED_BUFFER_POOL=$(echo "scale=0; $TOTAL_RAM * 0.75" | bc)

echo "=== Optimization Recommendations ==="
echo "Current Buffer Pool Size: $(echo "scale=2; $CURRENT_BUFFER_POOL/1024/1024/1024" | bc)GB"
echo "Recommended Buffer Pool Size: $(echo "scale=2; $RECOMMENDED_BUFFER_POOL/1024/1024/1024" | bc)GB (75% of RAM)"

if [ $(echo "$CURRENT_BUFFER_POOL < $RECOMMENDED_BUFFER_POOL" | bc) -eq 1 ]; then
    echo "RECOMMENDATION: Increase innodb_buffer_pool_size to $(echo "scale=0; $RECOMMENDED_BUFFER_POOL" | bc) bytes"
fi

echo
```

---

## 5. Monitoring and Analyzing Database Performance {#monitoring}

### Performance Monitoring Tools and Techniques

#### Built-in Performance Schema
```sql
-- Enable Performance Schema (MySQL 5.6+)
UPDATE performance_schema.setup_instruments 
SET ENABLED = 'YES', TIMED = 'YES' 
WHERE NAME LIKE '%statement/%';

UPDATE performance_schema.setup_consumers 
SET ENABLED = 'YES' 
WHERE NAME LIKE '%events_statements_%';

-- Top SQL statements by execution time
SELECT 
    SCHEMA_NAME,
    DIGEST_TEXT,
    COUNT_STAR as exec_count,
    AVG_TIMER_WAIT/1000000000 as avg_exec_time_sec,
    SUM_TIMER_WAIT/1000000000 as total_exec_time_sec,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent
FROM performance_schema.events_statements_summary_by_digest 
WHERE SCHEMA_NAME IS NOT NULL 
ORDER BY SUM_TIMER_WAIT DESC 
LIMIT 10;
```

#### Real-time Performance Monitoring
```sql
-- Create comprehensive monitoring view
CREATE VIEW performance_overview AS
SELECT 
    'Current Connections' as metric,
    VARIABLE_VALUE as value
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Threads_connected'

UNION ALL

SELECT 
    'Running Queries',
    COUNT(*)
FROM INFORMATION_SCHEMA.PROCESSLIST 
WHERE COMMAND != 'Sleep'

UNION ALL

SELECT 
    'Slow Queries',
    VARIABLE_VALUE
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Slow_queries'

UNION ALL

SELECT 
    'QPS (Queries Per Second)',
    ROUND(VARIABLE_VALUE / (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Uptime'), 2)
FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
WHERE VARIABLE_NAME = 'Questions'

UNION ALL

SELECT 
    'Buffer Pool Hit Ratio %',
    ROUND(
        (1 - (
            (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads') /
            NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'), 0)
        )) * 100, 2
    );

-- Use the view
SELECT * FROM performance_overview;
```

### Custom Performance Monitoring System

#### Performance Metrics Collection
```sql
-- Create performance metrics table
CREATE TABLE performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100),
    metric_value DECIMAL(20,4),
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_time (metric_name, collected_at)
);

-- Stored procedure to collect metrics
DELIMITER //
CREATE PROCEDURE CollectPerformanceMetrics()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE metric_name VARCHAR(100);
    DECLARE metric_value DECIMAL(20,4);
    
    -- Collect key performance metrics
    INSERT INTO performance_metrics (metric_name, metric_value) VALUES
    ('threads_connected', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_connected')),
    ('threads_running', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_running')),
    ('slow_queries', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Slow_queries')),
    ('questions', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Questions')),
    ('qps', (SELECT ROUND(VARIABLE_VALUE / (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Uptime'), 2) FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Questions')),
    ('innodb_buffer_pool_hit_ratio', (
        SELECT ROUND(
            (1 - (
                (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads') /
                NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'), 0)
            )) * 100, 2
        )
    )),
    ('innodb_deadlocks', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_deadlocks')),
    ('created_tmp_disk_tables', (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Created_tmp_disk_tables'));
    
    -- Clean up old metrics (keep only last 7 days)
    DELETE FROM performance_metrics 
    WHERE collected_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
END //
DELIMITER ;

-- Schedule metrics collection (run every minute)
-- Add to cron: * * * * * mysql -u root -p -e "CALL CollectPerformanceMetrics();"
```

#### Performance Dashboard Query
```sql
-- Performance dashboard with trends
SELECT 
    metric_name,
    AVG(metric_value) as avg_value,
    MIN(metric_value) as min_value,
    MAX(metric_value) as max_value,
    COUNT(*) as data_points
FROM performance_metrics 
WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY metric_name
ORDER BY metric_name;

-- Performance trend analysis
SELECT 
    DATE_FORMAT(collected_at, '%Y-%m-%d %H:%i:00') as time_bucket,
    metric_name,
    AVG(metric_value) as avg_value
FROM performance_metrics 
WHERE collected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND metric_name IN ('qps', 'threads_connected', 'innodb_buffer_pool_hit_ratio')
GROUP BY time_bucket, metric_name
ORDER BY time_bucket, metric_name;
```

### External Monitoring Tools Integration

#### Prometheus + Grafana Integration
```yaml
# docker-compose.yml for monitoring stack
version: '3.8'
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
      - ./my.cnf:/etc/mysql/my.cnf

  mysql-exporter:
    image: prom/mysqld-exporter
    environment:
      DATA_SOURCE_NAME: "root:rootpassword@(mysql:3306)/"
    ports:
      - "9104:9104"
    depends_on:
      - mysql

  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      GF_SECURITY_ADMIN_PASSWORD: admin

volumes:
  mysql_data:
```

```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'mysql'
    static_configs:
      - targets: ['mysql-exporter:9104']
```

#### Custom Monitoring Script with Alerting
```bash
#!/bin/bash
# mysql_monitor.sh

MYSQL_USER="root"
MYSQL_PASS="password"
MYSQL_HOST="localhost"
ALERT_EMAIL="admin@example.com"
LOG_FILE="/var/log/mysql_monitor.log"

# Thresholds
MAX_CONNECTIONS=80
MAX_SLOW_QUERIES=100
MIN_BUFFER_POOL_HIT_RATIO=95
MAX_THREADS_RUNNING=20

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

send_alert() {
    local subject="$1"
    local message="$2"
    echo "$message" | mail -s "$subject" $ALERT_EMAIL
    log_message "ALERT: $subject"
}

check_connections() {
    local current_connections=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_connected'")
    local max_connections=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_VARIABLES WHERE VARIABLE_NAME = 'max_connections'")
    local connection_percentage=$(echo "scale=2; $current_connections * 100 / $max_connections" | bc)
    
    if [ $(echo "$connection_percentage > $MAX_CONNECTIONS" | bc) -eq 1 ]; then
        send_alert "High Connection Usage" "Current connections: $current_connections ($connection_percentage% of max)"
    fi
    
    log_message "Connections: $current_connections/$max_connections ($connection_percentage%)"
}

check_slow_queries() {
    local slow_queries=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Slow_queries'")
    
    # Compare with previous check (stored in temp file)
    local prev_slow_queries=0
    if [ -f /tmp/mysql_monitor_slow_queries ]; then
        prev_slow_queries=$(cat /tmp/mysql_monitor_slow_queries)
    fi
    
    local new_slow_queries=$((slow_queries - prev_slow_queries))
    echo $slow_queries > /tmp/mysql_monitor_slow_queries
    
    if [ $new_slow_queries -gt $MAX_SLOW_QUERIES ]; then
        send_alert "High Slow Query Rate" "New slow queries in last check: $new_slow_queries"
    fi
    
    log_message "Slow queries: $slow_queries (new: $new_slow_queries)"
}

check_buffer_pool() {
    local hit_ratio=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "
        SELECT ROUND(
            (1 - (
                (SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_reads') /
                NULLIF((SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Innodb_buffer_pool_read_requests'), 0)
            )) * 100, 2
        );
    ")
    
    if [ $(echo "$hit_ratio < $MIN_BUFFER_POOL_HIT_RATIO" | bc) -eq 1 ]; then
        send_alert "Low Buffer Pool Hit Ratio" "Current hit ratio: $hit_ratio%"
    fi
    
    log_message "Buffer pool hit ratio: $hit_ratio%"
}

check_running_threads() {
    local running_threads=$(mysql -u$MYSQL_USER -p$MYSQL_PASS -h$MYSQL_HOST -se "SELECT VARIABLE_VALUE FROM INFORMATION_SCHEMA.GLOBAL_STATUS WHERE VARIABLE_NAME = 'Threads_running'")
    
    if [ $running_threads -gt $MAX_THREADS_RUNNING ]; then
        send_alert "High Running Threads" "Current running threads: $running_threads"
    fi
    
    log_message "Running threads: $running_threads"
}

# Main monitoring loop
log_message "Starting MySQL monitoring"

check_connections
check_slow_queries
check_buffer_pool
check_running_threads

log_message "Monitoring check completed"
```

---

## 6. Kali Linux Performance Tools {#kali}

### Installing Performance Monitoring Tools on Kali

```bash
# Update package list
sudo apt update

# Install MySQL performance tools
sudo apt install mysql-server mysql-client
sudo apt install percona-toolkit
sudo apt install mytop
sudo apt install innotop

# Install system monitoring tools
sudo apt install htop iotop sysstat
sudo apt install dstat nethogs

# Install network monitoring
sudo apt install iftop tcpdump wireshark

# Install benchmarking tools
sudo apt install sysbench
```

### Kali-Specific Performance Analysis Tools

#### Using mytop for Real-time MySQL Monitoring
```bash
# Connect to MySQL with mytop
mytop -u root -p -h localhost

# mytop with specific database
mytop -u root -p -d production_db

# mytop with custom refresh interval
mytop -u root -p -s 2  # 2 second refresh
```

#### Using innotop for InnoDB Monitoring
```bash
# Launch innotop
innotop -u root -p

# innotop with specific connection
innotop -h localhost -u root -p --mode=T  # Transaction mode

# Key innotop modes:
# T - Transaction/InnoDB Locks
# Q - Query List
# I - InnoDB I/O Info
# B - InnoDB Buffer Pool
# R - InnoDB Row Operations
```

#### System-Level Performance Monitoring
```bash
#!/bin/bash
# system_mysql_monitor.sh - Kali Linux specific

echo "=== System and MySQL Performance Monitor ==="
echo "Timestamp: $(date)"
echo

# CPU and Memory usage
echo "=== System Resources ==="
echo "CPU Usage:"
top -bn1 | grep "Cpu(s)" | awk '{print "User: " $2 ", System: " $4 ", Idle: " $8}'

echo "Memory Usage:"
free -h | awk 'NR==2{printf "RAM: %s/%s (%.2f%%) ", $3,$2,$3*100/$2}'
free -h | awk 'NR==3{printf "Swap: %s/%s (%.2f%%)\n", $3,$2,$3*100/$2}'

echo "Disk I/O:"
iostat -d 1 2 | tail -n +4 | head -n -1

echo

# MySQL process monitoring
echo "=== MySQL Process Information ==="
ps aux | grep mysql | grep -v grep

echo

# Network connections to MySQL
echo "=== MySQL Network Connections ==="
netstat -an | grep :3306 | wc -l | awk '{print "Total connections to port 3306: " $1}'
netstat -an | grep :3306 | grep ESTABLISHED | wc -l | awk '{print "Established connections: " $1}'

echo

# Disk usage for MySQL data directory
echo "=== MySQL Disk Usage ==="
du -sh /var/lib/mysql
df -h /var/lib/mysql
```

### Performance Testing with sysbench on Kali

#### Install and Configure sysbench
```bash
# Install sysbench
sudo apt install sysbench

# Create test database
mysql -u root -p -e "CREATE DATABASE sbtest;"
mysql -u root -p -e "CREATE USER 'sbtest'@'localhost' IDENTIFIED BY 'password';"
mysql -u root -p -e "GRANT ALL ON sbtest.* TO 'sbtest'@'localhost';"
```

#### sysbench Performance Tests
```bash
#!/bin/bash
# mysql_benchmark.sh

DB_USER="sbtest"
DB_PASS="password"
DB_NAME="sbtest"
THREADS=4
TABLE_SIZE=10000
TEST_DURATION=60

echo "=== MySQL Performance Benchmark with sysbench ==="
echo "Threads: $THREADS"
echo "Table size: $TABLE_SIZE"
echo "Duration: $TEST_DURATION seconds"
echo

# Prepare test data
echo "Preparing test data..."
sysbench oltp_read_write \
    --mysql-host=localhost \
    --mysql-user=$DB_USER \
    --mysql-password=$DB_PASS \
    --mysql-db=$DB_NAME \
    --tables=4 \
    --table-size=$TABLE_SIZE \
    prepare

echo

# Read-write test
echo "Running read-write benchmark..."
sysbench oltp_read_write \
    --mysql-host=localhost \
    --mysql-user=$DB_USER \
    --mysql-password=$DB_PASS \
    --mysql-db=$DB_NAME \
    --tables=4 \
    --threads=$THREADS \
    --time=$TEST_DURATION \
    --report-interval=10 \
    run

echo

# Read-only test
echo "Running read-only benchmark..."
sysbench oltp_read_only \
    --mysql-host=localhost \
    --mysql-user=$DB_USER \
    --mysql-password=$DB_PASS \
    --mysql-db=$DB_NAME \
    --tables=4 \
    --threads=$THREADS \
    --time=$TEST_DURATION \
    --report-interval=10 \
    run

echo

# Write-only test
echo "Running write-only benchmark..."
sysbench oltp_write_only \
    --mysql-host=localhost \
    --mysql-user=$DB_USER \
    --mysql-password=$DB_PASS \
    --mysql-db=$DB_NAME \
    --tables=4 \
    --threads=$THREADS \
    --time=$TEST_DURATION \
    --report-interval=10 \
    run

echo

# Cleanup
echo "Cleaning up test data..."
sysbench oltp_read_write \
    --mysql-host=localhost \
    --mysql-user=$DB_USER \
    --mysql-password=$DB_PASS \
    --mysql-db=$DB_NAME \
    --tables=4 \
    cleanup

echo "Benchmark completed!"
```

### Network Performance Analysis for MySQL
```bash
#!/bin/bash
# mysql_network_analysis.sh

MYSQL_PORT=3306

echo "=== MySQL Network Performance Analysis ==="
echo

# Check MySQL port connectivity
echo "=== Port Connectivity ==="
nmap -p $MYSQL_PORT localhost
echo

# Monitor MySQL network traffic
echo "=== Network Traffic Monitoring ==="
echo "Monitoring MySQL traffic for 30 seconds..."
tcpdump -i any -c 100 port $MYSQL_PORT &
TCPDUMP_PID=$!

# Generate some MySQL traffic
mysql -u root -p -e "SELECT 1; SELECT NOW(); SHOW STATUS;" >/dev/null 2>&1

sleep 5
kill $TCPDUMP_PID 2>/dev/null

echo

# Network connection analysis
echo "=== Connection Analysis ==="
ss -tuna | grep :$MYSQL_PORT | head -10

echo

# Bandwidth usage
echo "=== Bandwidth Usage ==="
iftop -t -s 10 2>/dev/null | grep -A 5 -B 5 $MYSQL_PORT || echo "iftop not available or no MySQL traffic detected"
```

---

## 7. Laravel Performance Optimization {#laravel}

### Laravel Database Configuration Optimization

#### Database Configuration for Performance
```php
<?php
// config/database.php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ],
            // Connection pooling settings
            'pool' => [
                'max_connections' => 100,
                'min_connections' => 5,
            ],
        ],
        
        // Read-only replica for read operations
        'mysql_read' => [
            'driver' => 'mysql',
            'host' => env('DB_READ_HOST', '127.0.0.1'),
            'port' => env('DB_READ_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_READ_USERNAME', 'readonly_user'),
            'password' => env('DB_READ_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    
    // Redis for session and cache
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
```

### Laravel Query Optimization

#### Eloquent Performance Optimization
```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class User extends Model
{
    protected $fillable = ['name', 'email', 'status'];
    
    // Define relationships with proper indexes
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    
    // Scope for common queries
    public function scopeActive(Builder $query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeWithPostCount(Builder $query)
    {
        return $query->withCount('posts');
    }
    
    // Optimized query methods
    public static function getActiveUsersWithPosts()
    {
        return self::active()
            ->with(['posts' => function ($query) {
                $query->select('id', 'user_id', 'title', 'created_at')
                      ->latest()
                      ->limit(5);
            }])
            ->select('id', 'name', 'email', 'status')
            ->get();
    }
    
    public static function getUsersWithPostCount($limit = 10)
    {
        return self::withPostCount()
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }
}
```

#### Query Optimization Service
```php
<?php
// app/Services/QueryOptimizationService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueryOptimizationService
{
    public function analyzeSlowQueries($limit = 10)
    {
        return DB::select("
            SELECT 
                query_time,
                lock_time,
                rows_sent,
                rows_examined,
                sql_text
            FROM mysql.slow_log 
            ORDER BY query_time DESC 
            LIMIT ?
        ", [$limit]);
    }
    
    public function getTableStats($database = null)
    {
        $database = $database ?: config('database.connections.mysql.database');
        
        return DB::select("
            SELECT 
                TABLE_NAME as table_name,
                TABLE_ROWS as estimated_rows,
                ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_size_mb,
                ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_size_mb,
                ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_size_mb
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ?
                AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
        ", [$database]);
    }
    
    public function getIndexUsageStats($database = null)
    {
        $database = $database ?: config('database.connections.mysql.database');
        
        return DB::select("
            SELECT 
                s.TABLE_NAME,
                s.INDEX_NAME,
                s.COLUMN_NAME,
                s.CARDINALITY,
                CASE 
                    WHEN i.INDEX_NAME IS NULL THEN 'Potentially Unused'
                    ELSE 'Used'
                END as usage_status
            FROM INFORMATION_SCHEMA.STATISTICS s
            LEFT JOIN (
                SELECT DISTINCT 
                    OBJECT_SCHEMA,
                    OBJECT_NAME,
                    INDEX_NAME
                FROM performance_schema.table_io_waits_summary_by_index_usage
                WHERE COUNT_READ > 0 OR COUNT_WRITE > 0 OR COUNT_FETCH > 0
            ) i ON s.TABLE_SCHEMA = i.OBJECT_SCHEMA 
                AND s.TABLE_NAME = i.OBJECT_NAME 
                AND s.INDEX_NAME = i.INDEX_NAME
            WHERE s.TABLE_SCHEMA = ?
                AND s.INDEX_NAME != 'PRIMARY'
            ORDER BY s.TABLE_NAME, s.INDEX_NAME
        ", [$database]);
    }
    
    public function optimizeQuery($sql, $bindings = [])
    {
        // Get query execution plan
        $explained = DB::select("EXPLAIN FORMAT=JSON " . $sql, $bindings);
        
        return [
            'original_query' => $sql,
            'execution_plan' => json_decode($explained[0]->EXPLAIN, true),
            'recommendations' => $this->generateOptimizationRecommendations($explained[0]->EXPLAIN)
        ];
    }
    
    private function generateOptimizationRecommendations($explainJson)
    {
        $plan = json_decode($explainJson, true);
        $recommendations = [];
        
        // Analyze the execution plan and generate recommendations
        if (isset($plan['query_block']['table'])) {
            $table = $plan['query_block']['table'];
            
            // Check for full table scan
            if (isset($table['access_type']) && $table['access_type'] === 'ALL') {
                $recommendations[] = 'Consider adding an index to avoid full table scan';
            }
            
            // Check for temporary table usage
            if (isset($table['using_temporary_table']) && $table['using_temporary_table']) {
                $recommendations[] = 'Query uses temporary table - consider optimization';
            }
            
            // Check for filesort
            if (isset($table['using_filesort']) && $table['using_filesort']) {
                $recommendations[] = 'Query uses filesort - consider adding appropriate index for ORDER BY';
            }
        }
        
        return $recommendations;
    }
    
    public function getCachedQueryStats()
    {
        return Cache::remember('query_stats', 300, function () {
            return [
                'slow_queries' => $this->analyzeSlowQueries(5),
                'table_stats' => $this->getTableStats(),
                'performance_metrics' => $this->getPerformanceMetrics()
            ];
        });
    }
    
    private function getPerformanceMetrics()
    {
        $metrics = DB::select("
            SELECT 
                VARIABLE_NAME as metric,
                VARIABLE_VALUE as value
            FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
            WHERE VARIABLE_NAME IN (
                'Slow_queries',
                'Questions',
                'Uptime',
                'Threads_connected',
                'Threads_running',
                'Created_tmp_disk_tables',
                'Created_tmp_tables'
            )
        ");
        
        $result = [];
        foreach ($metrics as $metric) {
            $result[$metric->metric] = $metric->value;
        }
        
        return $result;
    }
}
```

#### Laravel Database Performance Middleware
```php
<?php
// app/Http/Middleware/DatabasePerformanceMonitor.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabasePerformanceMonitor
{
    public function handle(Request $request, Closure $next)
    {
        // Start monitoring
        $startTime = microtime(true);
        $startQueries = count(DB::getQueryLog());
        
        // Enable query logging
        DB::enableQueryLog();
        
        $response = $next($request);
        
        // Calculate metrics
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // milliseconds
        $queries = DB::getQueryLog();
        $queryCount = count($queries) - $startQueries;
        
        // Log slow requests
        if ($executionTime > 1000 || $queryCount > 10) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => $executionTime,
                'query_count' => $queryCount,
                'queries' => array_slice($queries, -$queryCount),
                'memory_usage' => memory_get_peak_usage(true)
            ]);
        }
        
        // Add performance headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-Database-Query-Count', $queryCount);
            $response->headers->set('X-Database-Query-Time', round($executionTime, 2) . 'ms');
        }
        
        return $response;
    }
}
```

### Laravel Caching Strategies

#### Database Query Caching
```php
<?php
// app/Services/CachedDataService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Post;

class CachedDataService
{
    protected $defaultTtl = 3600; // 1 hour
    
    public function getUsersWithPostCount($limit = 10)
    {
        $cacheKey = "users_with_post_count_{$limit}";
        
        return Cache::remember($cacheKey, $this->defaultTtl, function () use ($limit) {
            return User::withCount('posts')
                ->orderBy('posts_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }
    
    public function getPopularPosts($days = 7, $limit = 10)
    {
        $cacheKey = "popular_posts_{$days}_{$limit}";
        
        return Cache::remember($cacheKey, $this->defaultTtl, function () use ($days, $limit) {
            return Post::where('created_at', '>=', now()->subDays($days))
                ->withCount('comments')
                ->orderBy('views', 'desc')
                ->orderBy('comments_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }
    
    public function getDashboardStats()
    {
        return Cache::remember('dashboard_stats', 300, function () {
            return [
                'total_users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'total_posts' => Post::count(),
                'posts_today' => Post::whereDate('created_at', today())->count(),
                'avg_posts_per_user' => DB::table('posts')
                    ->selectRaw('AVG(post_count) as avg')
                    ->from(DB::raw('(SELECT user_id, COUNT(*) as post_count FROM posts GROUP BY user_id) as user_posts'))
                    ->value('avg')
            ];
        });
    }
    
    public function invalidateUserCache($userId)
    {
        $patterns = [
            "user_{$userId}_*",
            "users_with_post_count_*",
            "dashboard_stats"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
    
    // Cache warming for critical data
    public function warmCache()
    {
        $this->getUsersWithPostCount(10);
        $this->getUsersWithPostCount(20);
        $this->getPopularPosts(7, 10);
        $this->getPopularPosts(30, 10);
        $this->getDashboardStats();
    }
}
```

#### Advanced Caching with Tags
```php
<?php
// app/Services/TaggedCacheService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TaggedCacheService
{
    public function cacheUserData($userId, $data, $ttl = 3600)
    {
        $cacheKey = "user_data_{$userId}";
        
        return Cache::tags(['users', "user_{$userId}"])
            ->remember($cacheKey, $ttl, function () use ($data) {
                return $data;
            });
    }
    
    public function cachePostData($postId, $data, $ttl = 3600)
    {
        $cacheKey = "post_data_{$postId}";
        
        return Cache::tags(['posts', "post_{$postId}"])
            ->remember($cacheKey, $ttl, function () use ($data) {
                return $data;
            });
    }
    
    public function invalidateUserCaches($userId)
    {
        Cache::tags(["user_{$userId}"])->flush();
    }
    
    public function invalidatePostCaches($postId)
    {
        Cache::tags(["post_{$postId}"])->flush();
    }
    
    public function invalidateAllUserCaches()
    {
        Cache::tags(['users'])->flush();
    }
    
    public function invalidateAllPostCaches()
    {
        Cache::tags(['posts'])->flush();
    }
}
```

### Laravel Queue Optimization for Database Operations

#### Database-Heavy Job Processing
```php
<?php
// app/Jobs/ProcessLargeDataset.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessLargeDataset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchSize;
    protected $offset;
    
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct($batchSize = 1000, $offset = 0)
    {
        $this->batchSize = $batchSize;
        $this->offset = $offset;
    }

    public function handle()
    {
        DB::transaction(function () {
            // Process data in chunks to avoid memory issues
            DB::table('large_table')
                ->offset($this->offset)
                ->limit($this->batchSize)
                ->chunk(100, function ($records) {
                    foreach ($records as $record) {
                        // Process each record
                        $this->processRecord($record);
                    }
                });
        });
    }
    
    private function processRecord($record)
    {
        // Your processing logic here
        DB::table('processed_data')->insert([
            'original_id' => $record->id,
            'processed_data' => json_encode($record),
            'processed_at' => now()
        ]);
    }
    
    public function failed(\Exception $exception)
    {
        // Handle failed job
        \Log::error('Large dataset processing failed', [
            'batch_size' => $this->batchSize,
            'offset' => $this->offset,
            'error' => $exception->getMessage()
        ]);
    }
}
```

#### Batch Database Operations
```php
<?php
// app/Services/BatchOperationService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;

class BatchOperationService
{
    public function batchInsert($table, $data, $batchSize = 1000)
    {
        $chunks = array_chunk($data, $batchSize);
        
        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
    
    public function batchUpdate($table, $updates, $keyColumn = 'id')
    {
        DB::transaction(function () use ($table, $updates, $keyColumn) {
            foreach ($updates as $update) {
                DB::table($table)
                    ->where($keyColumn, $update[$keyColumn])
                    ->update($update);
            }
        });
    }
    
    public function batchUpsert($table, $data, $uniqueColumns, $updateColumns)
    {
        // Use MySQL's INSERT ... ON DUPLICATE KEY UPDATE
        $columns = array_keys($data[0]);
        $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
        $values = [];
        
        foreach ($data as $row) {
            $values = array_merge($values, array_values($row));
        }
        
        $updateClause = implode(', ', array_map(function ($col) {
            return "{$col} = VALUES({$col})";
        }, $updateColumns));
        
        $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES " 
             . str_repeat($placeholders . ',', count($data) - 1) . $placeholders
             . " ON DUPLICATE KEY UPDATE {$updateClause}";
        
        DB::statement($sql, $values);
    }
    
    public function processLargeTableInChunks($table, $callback, $chunkSize = 1000)
    {
        DB::table($table)->chunk($chunkSize, $callback);
    }
}
```

### Laravel Performance Monitoring Dashboard

#### Performance Metrics Controller
```php
<?php
// app/Http/Controllers/PerformanceController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QueryOptimizationService;
use App\Services\CachedDataService;

class PerformanceController extends Controller
{
    protected $queryService;
    protected $cacheService;
    
    public function __construct(
        QueryOptimizationService $queryService,
        CachedDataService $cacheService
    ) {
        $this->queryService = $queryService;
        $this->cacheService = $cacheService;
    }
    
    public function dashboard()
    {
        $metrics = $this->queryService->getCachedQueryStats();
        
        return view('admin.performance.dashboard', [
            'slow_queries' => $metrics['slow_queries'],
            'table_stats' => $metrics['table_stats'],
            'performance_metrics' => $metrics['performance_metrics'],
            'cache_stats' => $this->getCacheStats()
        ]);
    }
    
    public function slowQueries()
    {
        $slowQueries = $this->queryService->analyzeSlowQueries(20);
        
        return view('admin.performance.slow-queries', [
            'queries' => $slowQueries
        ]);
    }
    
    public function indexAnalysis()
    {
        $indexStats = $this->queryService->getIndexUsageStats();
        
        return view('admin.performance.indexes', [
            'index_stats' => $indexStats
        ]);
    }
    
    public function optimizeQuery(Request $request)
    {
        $request->validate([
            'query' => 'required|string'
        ]);
        
        $optimization = $this->queryService->optimizeQuery($request->query);
        
        return response()->json($optimization);
    }
    
    public function clearCache()
    {
        \Cache::flush();
        
        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }
    
    public function warmCache()
    {
        $this->cacheService->warmCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Cache warmed successfully'
        ]);
    }
    
    private function getCacheStats()
    {
        // This would depend on your cache driver
        // For Redis, you could use Redis::info()
        return [
            'total_keys' => 0, // Implement based on your cache driver
            'memory_usage' => 0,
            'hit_ratio' => 0
        ];
    }
}
```

#### Performance Dashboard Blade Template
```blade
{{-- resources/views/admin/performance/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Performance Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1>MySQL Performance Dashboard</h1>
        </div>
    </div>
    
    <!-- Performance Metrics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Queries</h5>
                    <h2>{{ number_format($performance_metrics['Questions'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Slow Queries</h5>
                    <h2>{{ number_format($performance_metrics['Slow_queries'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Connections</h5>
                    <h2>{{ $performance_metrics['Threads_connected'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Running Threads</h5>
                    <h2>{{ $performance_metrics['Threads_running'] ?? 0 }}</h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Slow Queries -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Slow Queries</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Query Time</th>
                                    <th>Lock Time</th>
                                    <th>Rows Sent</th>
                                    <th>Rows Examined</th>
                                    <th>Query</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($slow_queries as $query)
                                <tr>
                                    <td>{{ $query->query_time }}</td>
                                    <td>{{ $query->lock_time }}</td>
                                    <td>{{ $query->rows_sent }}</td>
                                    <td>{{ $query->rows_examined }}</td>
                                    <td>
                                        <code>{{ Str::limit($query->sql_text, 100) }}</code>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Table Statistics -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Table Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Estimated Rows</th>
                                    <th>Data Size (MB)</th>
                                    <th>Index Size (MB)</th>
                                    <th>Total Size (MB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($table_stats as $table)
                                <tr>
                                    <td>{{ $table->table_name }}</td>
                                    <td>{{ number_format($table->estimated_rows) }}</td>
                                    <td>{{ $table->data_size_mb }}</td>
                                    <td>{{ $table->index_size_mb }}</td>
                                    <td>{{ $table->total_size_mb }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cache Management -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Cache Management</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-warning" onclick="clearCache()">Clear Cache</button>
                    <button class="btn btn-success" onclick="warmCache()">Warm Cache</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function clearCache() {
    fetch('/admin/performance/clear-cache', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        location.reload();
    });
}

function warmCache() {
    fetch('/admin/performance/warm-cache', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
    });
}
</script>
@endsection
```

### Environment Configuration for Performance

#### Optimized .env Configuration
```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_app
DB_USERNAME=laravel_user
DB_PASSWORD=secure_password

# Database Performance Settings
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_STRICT_MODE=true
DB_ENGINE=InnoDB

# Connection Pooling
DB_POOL_MIN=5
DB_POOL_MAX=20

# Read Replica Configuration
DB_READ_HOST=127.0.0.1
DB_READ_USERNAME=readonly_user
DB_READ_PASSWORD=readonly_password

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2

# Queue Configuration
QUEUE_DRIVER=redis
QUEUE_FAILED_DRIVER=database

# Performance Monitoring
QUERY_LOG_ENABLED=false
SLOW_QUERY_LOG=true
SLOW_QUERY_TIME=2.0

# Cache TTL Settings
CACHE_DEFAULT_TTL=3600
CACHE_LONG_TTL=86400
CACHE_SHORT_TTL=300
```

---

## Best Practices and Performance Guidelines

### Query Optimization Best Practices

1. **Index Strategy**
   - Create indexes for frequently queried columns
   - Use composite indexes for multi-column queries
   - Monitor and remove unused indexes
   - Consider covering indexes for read-heavy queries

2. **Query Writing**
   - Use LIMIT for large result sets
   - Avoid SELECT * in production queries
   - Use proper JOIN types and conditions
   - Minimize subqueries and use JOINs instead

3. **Schema Design**
   - Choose appropriate data types
   - Normalize for write-heavy, denormalize for read-heavy
   - Use partitioning for very large tables
   - Implement proper foreign key constraints

### Laravel-Specific Performance Tips

1. **Eloquent Optimization**
   - Use eager loading to prevent N+1 queries
   - Implement query scopes for reusable conditions
   - Use select() to limit columns returned
   - Leverage chunk() for processing large datasets

2. **Caching Strategy**
   - Cache expensive queries and computations
   - Use cache tags for organized cache invalidation
   - Implement cache warming for critical data
   - Monitor cache hit ratios

3. **Database Configuration**
   - Use read replicas for read-heavy operations
   - Configure connection pooling
   - Optimize MySQL configuration parameters
   - Regular monitoring and maintenance

---

## Conclusion

This comprehensive guide covers all aspects of MySQL performance tuning and optimization, from identifying bottlenecks to implementing advanced monitoring solutions in Laravel applications. Key takeaways include:

**Performance Monitoring**: Regular monitoring of key metrics, slow queries, and system resources is essential for maintaining optimal database performance.

**Query Optimization**: Proper indexing, query structure, and execution plan analysis are fundamental to database performance.

**Laravel Integration**: Leveraging Laravel's built-in features like Eloquent optimization, caching, and queue systems can significantly improve application performance.

**Proactive Maintenance**: Regular analysis of slow queries, index usage, and system metrics helps prevent performance issues before they impact users.

**Tool Utilization**: Using specialized tools like Percona Toolkit, mytop, and custom monitoring solutions provides deeper insights into database performance.

Remember that performance optimization is an ongoing process that requires continuous monitoring, testing, and refinement based on your specific application requirements and usage patterns.
