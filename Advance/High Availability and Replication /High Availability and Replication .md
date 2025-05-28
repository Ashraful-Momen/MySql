# MySQL High Availability and Replication Guide
## Complete Guide for Kali Linux and Laravel

---

## Table of Contents
1. [Understanding High Availability Concepts](#understanding-high-availability-concepts)
2. [MySQL Replication and Its Advantages](#mysql-replication-and-its-advantages)
3. [Replication Methods, Synchronization, and Formats](#replication-methods-synchronization-and-formats)
4. [Configuring and Managing MySQL Replication](#configuring-and-managing-mysql-replication)
5. [Implementing Failover and Load Balancing](#implementing-failover-and-load-balancing)
6. [Laravel Integration](#laravel-integration)
7. [Kali Linux Specific Configurations](#kali-linux-specific-configurations)

---

## Understanding High Availability Concepts

### What is High Availability?
High Availability (HA) refers to systems that remain operational and accessible for extended periods, typically measured in "nines" (99.9%, 99.99%, etc.). In database context, HA ensures:

- **Minimal Downtime**: System remains accessible even during failures
- **Data Consistency**: Data integrity is maintained across all nodes
- **Automatic Recovery**: Systems can recover from failures without manual intervention
- **Scalability**: Ability to handle increased load

### Key HA Metrics
- **Recovery Time Objective (RTO)**: Maximum acceptable downtime
- **Recovery Point Objective (RPO)**: Maximum acceptable data loss
- **Mean Time Between Failures (MTBF)**: Average time between system failures
- **Mean Time To Recovery (MTTR)**: Average time to restore service

### HA Architecture Components
1. **Redundancy**: Multiple servers/components
2. **Load Distribution**: Spreading workload across multiple nodes
3. **Monitoring**: Continuous health checks
4. **Failover Mechanisms**: Automatic switching to backup systems

---

## MySQL Replication and Its Advantages

### What is MySQL Replication?
MySQL replication is a process that enables data from one MySQL database server (master/source) to be copied automatically to one or more MySQL database servers (slaves/replicas).

### Types of MySQL Replication

#### 1. Traditional Replication (Master-Slave)
```
Master Server → Slave Server(s)
```

#### 2. Master-Master Replication
```
Master A ←→ Master B
```

#### 3. Group Replication
```
Node A ←→ Node B ←→ Node C
```

### Advantages of MySQL Replication

#### Performance Benefits
- **Read Scaling**: Distribute read queries across multiple replicas
- **Write Performance**: Master handles writes while slaves handle reads
- **Reduced Latency**: Geographic distribution of data

#### Availability Benefits
- **Redundancy**: Multiple copies of data
- **Backup Solutions**: Live backups without affecting master
- **Disaster Recovery**: Quick recovery from hardware failures

#### Operational Benefits
- **Analytics**: Run heavy analytical queries on slaves
- **Testing**: Use replicas for testing without affecting production
- **Maintenance**: Perform maintenance on slaves without downtime

---

## Replication Methods, Synchronization, and Formats

### Replication Methods

#### 1. Asynchronous Replication (Default)
- Master doesn't wait for slave acknowledgment
- Better performance but potential data loss
- Default MySQL replication method

#### 2. Semi-Synchronous Replication
- Master waits for at least one slave acknowledgment
- Balance between performance and data safety
- Requires plugin installation

#### 3. Synchronous Replication (Group Replication)
- All nodes must acknowledge before commit
- Strongest consistency but slower performance

### Binary Log Formats

#### 1. Statement-Based Replication (SBR)
```sql
-- Logs actual SQL statements
UPDATE users SET status = 'active' WHERE id > 100;
```
**Advantages:**
- Smaller log files
- Human-readable logs
- Less network traffic

**Disadvantages:**
- Non-deterministic functions may cause inconsistencies
- Some statements cannot be replicated safely

#### 2. Row-Based Replication (RBR)
```sql
-- Logs actual row changes
# Row changes for table 'users'
# Field 1: id=101, status='active'
# Field 2: id=102, status='active'
```
**Advantages:**
- More reliable replication
- No issues with non-deterministic functions
- Better for complex statements

**Disadvantages:**
- Larger log files
- More network traffic

#### 3. Mixed-Based Replication
- Automatically switches between SBR and RBR
- Uses SBR by default, switches to RBR when necessary
- Best of both worlds

---

## Configuring and Managing MySQL Replication

### Prerequisites for Kali Linux

#### Install MySQL Server
```bash
# Update package list
sudo apt update

# Install MySQL Server
sudo apt install mysql-server mysql-client

# Secure MySQL installation
sudo mysql_secure_installation

# Start and enable MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql
```

#### Check MySQL Status
```bash
sudo systemctl status mysql
mysql --version
```

### Master Server Configuration

#### 1. Edit MySQL Configuration
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Add the following configuration:
```ini
[mysqld]
# Server ID (unique for each server)
server-id = 1

# Enable binary logging
log-bin = mysql-bin
log-bin-index = mysql-bin.index

# Binary log format
binlog_format = mixed

# Enable GTID (Global Transaction Identifier)
gtid_mode = ON
enforce_gtid_consistency = ON

# Replication settings
sync_binlog = 1
innodb_flush_log_at_trx_commit = 1

# Network settings
bind-address = 0.0.0.0
port = 3306

# Buffer pool size (adjust based on available RAM)
innodb_buffer_pool_size = 1G

# Log settings for better performance
log_slave_updates = ON
```

#### 2. Restart MySQL Service
```bash
sudo systemctl restart mysql
```

#### 3. Create Replication User
```sql
-- Connect to MySQL
mysql -u root -p

-- Create replication user
CREATE USER 'replication_user'@'%' IDENTIFIED BY 'strong_password_here';
GRANT REPLICATION SLAVE ON *.* TO 'replication_user'@'%';
FLUSH PRIVILEGES;

-- Check master status
SHOW MASTER STATUS;
```

Note the `File` and `Position` values from the output.

### Slave Server Configuration

#### 1. Edit MySQL Configuration
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

```ini
[mysqld]
# Unique server ID
server-id = 2

# Relay log settings
relay-log = relay-bin
relay-log-index = relay-bin.index

# Read-only mode (optional)
read_only = 1

# GTID settings
gtid_mode = ON
enforce_gtid_consistency = ON

# Network settings
bind-address = 0.0.0.0
port = 3306

# Replication settings
log_slave_updates = ON
slave_skip_errors = 1062,1053
```

#### 2. Restart MySQL Service
```bash
sudo systemctl restart mysql
```

#### 3. Configure Replication
```sql
-- Connect to MySQL
mysql -u root -p

-- Stop slave if running
STOP SLAVE;

-- Configure master connection
CHANGE MASTER TO
    MASTER_HOST='MASTER_IP_ADDRESS',
    MASTER_USER='replication_user',
    MASTER_PASSWORD='strong_password_here',
    MASTER_LOG_FILE='mysql-bin.000001',
    MASTER_LOG_POS=154;

-- Start slave
START SLAVE;

-- Check slave status
SHOW SLAVE STATUS\G
```

### Verification Commands

#### On Master:
```sql
-- Show master status
SHOW MASTER STATUS;

-- Show binary logs
SHOW BINARY LOGS;

-- Show processlist
SHOW PROCESSLIST;
```

#### On Slave:
```sql
-- Show slave status (detailed)
SHOW SLAVE STATUS\G

-- Check replication lag
SELECT 
    SECONDS_BEHIND_MASTER 
FROM 
    INFORMATION_SCHEMA.REPLICA_HOST_STATUS;
```

### GTID-Based Replication Setup

#### Master Configuration
```sql
-- Enable GTID on master
SET @@GLOBAL.GTID_MODE = OFF_PERMISSIVE;
SET @@GLOBAL.GTID_MODE = ON_PERMISSIVE;
SET @@GLOBAL.ENFORCE_GTID_CONSISTENCY = ON;
SET @@GLOBAL.GTID_MODE = ON;
```

#### Slave Configuration
```sql
-- Configure GTID-based replication
CHANGE MASTER TO
    MASTER_HOST='MASTER_IP_ADDRESS',
    MASTER_USER='replication_user',
    MASTER_PASSWORD='strong_password_here',
    MASTER_AUTO_POSITION=1;

START SLAVE;
```

---

## Implementing Failover and Load Balancing

### Manual Failover Process

#### 1. Promote Slave to Master
```sql
-- On the slave to be promoted
STOP SLAVE;
RESET SLAVE ALL;

-- Remove read-only mode
SET GLOBAL read_only = 0;

-- Show master status
SHOW MASTER STATUS;
```

#### 2. Point Applications to New Master
Update Laravel database configuration to point to the new master server.

### Automatic Failover with MHA (Master High Availability)

#### Install MHA on Kali Linux
```bash
# Install dependencies
sudo apt install libdbd-mysql-perl libconfig-tiny-perl liblog-dispatch-perl
sudo apt install libparallel-forkmanager-perl libtime-hires-perl

# Download and install MHA
wget https://github.com/yoshinorim/mha4mysql-manager/releases/download/v0.58/mha4mysql-manager_0.58-0_all.deb
sudo dpkg -i mha4mysql-manager_0.58-0_all.deb
```

#### MHA Configuration
```ini
# /etc/mha/app1.cnf
[server default]
manager_workdir=/var/log/mha/app1
manager_log=/var/log/mha/app1/manager.log
master_binlog_dir=/var/lib/mysql
user=mha
password=mha_password
ssh_user=root
repl_user=replication_user
repl_password=strong_password_here

[server1]
hostname=master-server-ip
port=3306
candidate_master=1

[server2]
hostname=slave1-server-ip
port=3306
candidate_master=1

[server3]
hostname=slave2-server-ip
port=3306
no_master=1
```

### Load Balancing Strategies

#### 1. Application-Level Load Balancing (Laravel)
Configure Laravel to use separate read/write connections:

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['slave1-ip', 'slave2-ip'],
    ],
    'write' => [
        'host' => ['master-ip'],
    ],
    'driver' => 'mysql',
    'database' => 'laravel_db',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
],
```

#### 2. ProxySQL for Connection Pooling and Load Balancing

##### Install ProxySQL on Kali Linux
```bash
# Add ProxySQL repository
wget -O - 'https://repo.proxysql.com/ProxySQL/repo_pub_key' | apt-key add -
echo deb https://repo.proxysql.com/ProxySQL/proxysql-2.4.x/$(lsb_release -sc)/ ./ | tee /etc/apt/sources.list.d/proxysql.list

# Install ProxySQL
sudo apt update
sudo apt install proxysql
```

##### Configure ProxySQL
```sql
-- Connect to ProxySQL admin interface
mysql -u admin -padmin -h 127.0.0.1 -P6032

-- Add MySQL servers
INSERT INTO mysql_servers(hostgroup_id, hostname, port, weight) VALUES
(0, 'master-ip', 3306, 1000),
(1, 'slave1-ip', 3306, 900),
(1, 'slave2-ip', 3306, 900);

-- Create user
INSERT INTO mysql_users(username, password, default_hostgroup) VALUES
('laravel_user', 'laravel_password', 0);

-- Query routing rules
INSERT INTO mysql_query_rules(active, match_pattern, destination_hostgroup, apply) VALUES
(1, '^SELECT.*', 1, 1),
(1, '^INSERT|UPDATE|DELETE.*', 0, 1);

-- Load configuration
LOAD MYSQL SERVERS TO RUNTIME;
LOAD MYSQL USERS TO RUNTIME;
LOAD MYSQL QUERY_RULES TO RUNTIME;

-- Save configuration
SAVE MYSQL SERVERS TO DISK;
SAVE MYSQL USERS TO DISK;
SAVE MYSQL QUERY_RULES TO DISK;
```

### Monitoring and Alerting

#### MySQL Replication Monitoring Script
```bash
#!/bin/bash
# monitor_replication.sh

SLAVE_HOST="slave-ip"
MYSQL_USER="monitor_user"
MYSQL_PASS="monitor_password"

# Check slave status
SLAVE_STATUS=$(mysql -h $SLAVE_HOST -u $MYSQL_USER -p$MYSQL_PASS -e "SHOW SLAVE STATUS\G")

# Extract important metrics
IO_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_IO_Running" | awk '{print $2}')
SQL_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_SQL_Running" | awk '{print $2}')
SECONDS_BEHIND=$(echo "$SLAVE_STATUS" | grep "Seconds_Behind_Master" | awk '{print $2}')

echo "Replication Status Report"
echo "========================"
echo "IO Thread Running: $IO_RUNNING"
echo "SQL Thread Running: $SQL_RUNNING"
echo "Seconds Behind Master: $SECONDS_BEHIND"

# Alert conditions
if [ "$IO_RUNNING" != "Yes" ] || [ "$SQL_RUNNING" != "Yes" ]; then
    echo "ALERT: Replication threads not running!"
    # Send alert (email, Slack, etc.)
fi

if [ "$SECONDS_BEHIND" -gt 300 ]; then
    echo "ALERT: Replication lag is high ($SECONDS_BEHIND seconds)"
    # Send alert
fi
```

---

## Laravel Integration

### Database Configuration for Replication

#### 1. Multiple Database Connections
```php
// config/database.php
'connections' => [
    'mysql_master' => [
        'driver' => 'mysql',
        'host' => env('DB_MASTER_HOST', '127.0.0.1'),
        'port' => env('DB_MASTER_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
    
    'mysql_slave' => [
        'driver' => 'mysql',
        'host' => env('DB_SLAVE_HOST', '127.0.0.1'),
        'port' => env('DB_SLAVE_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

#### 2. Read/Write Splitting Configuration
```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_SLAVE1_HOST', '127.0.0.1'),
            env('DB_SLAVE2_HOST', '127.0.0.1'),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_MASTER_HOST', '127.0.0.1'),
        ],
    ],
    'sticky' => true,
    'driver' => 'mysql',
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

### Environment Configuration
```env
# .env file
DB_CONNECTION=mysql
DB_MASTER_HOST=master-server-ip
DB_SLAVE_HOST=slave-server-ip
DB_SLAVE1_HOST=slave1-server-ip
DB_SLAVE2_HOST=slave2-server-ip
DB_PORT=3306
DB_DATABASE=laravel_app
DB_USERNAME=laravel_user
DB_PASSWORD=secure_password
```

### Laravel Models with Connection Specification

#### Force Specific Connection
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // Use slave connection for read operations
    protected $connection = 'mysql_slave';
    
    // Method to use master connection
    public static function onMaster()
    {
        return (new static)->setConnection('mysql_master');
    }
}
```

#### Service Class for Database Operations
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DatabaseService
{
    public function readFromSlave($query, $bindings = [])
    {
        return DB::connection('mysql_slave')->select($query, $bindings);
    }
    
    public function writeToMaster($query, $bindings = [])
    {
        return DB::connection('mysql_master')->statement($query, $bindings);
    }
    
    public function getUsersFromSlave()
    {
        return DB::connection('mysql_slave')
            ->table('users')
            ->where('active', 1)
            ->get();
    }
    
    public function createUserOnMaster($userData)
    {
        return DB::connection('mysql_master')
            ->table('users')
            ->insert($userData);
    }
}
```

### Middleware for Connection Management
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class DatabaseConnectionMiddleware
{
    public function handle($request, Closure $next)
    {
        // Use master for write operations
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            DB::setDefaultConnection('mysql_master');
        } else {
            // Use slave for read operations
            DB::setDefaultConnection('mysql_slave');
        }
        
        return $next($request);
    }
}
```

### Queue Configuration for Replication
```php
// config/queue.php
'connections' => [
    'database_master' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'connection' => 'mysql_master', // Always use master for queue jobs
    ],
],
```

---

## Kali Linux Specific Configurations

### Firewall Configuration
```bash
# Allow MySQL port
sudo ufw allow 3306/tcp

# Allow specific IPs only (recommended)
sudo ufw allow from SLAVE_IP to any port 3306
sudo ufw allow from MASTER_IP to any port 3306

# Check firewall status
sudo ufw status
```

### Network Configuration
```bash
# Check network interfaces
ip addr show

# Configure static IP (if needed)
sudo nano /etc/network/interfaces

# Example static IP configuration
auto eth0
iface eth0 inet static
    address 192.168.1.100
    netmask 255.255.255.0
    gateway 192.168.1.1
    dns-nameservers 8.8.8.8 8.8.4.4
```

### Performance Tuning for Kali Linux

#### System-level Optimizations
```bash
# Increase file descriptor limits
echo "mysql soft nofile 65535" >> /etc/security/limits.conf
echo "mysql hard nofile 65535" >> /etc/security/limits.conf

# Optimize kernel parameters
echo "net.core.somaxconn = 65535" >> /etc/sysctl.conf
echo "net.ipv4.tcp_max_syn_backlog = 65535" >> /etc/sysctl.conf
sysctl -p
```

#### MySQL Performance Configuration
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
# Memory settings
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_log_buffer_size = 64M
query_cache_size = 128M
query_cache_limit = 2M

# Connection settings
max_connections = 1000
max_connect_errors = 10000
thread_cache_size = 50

# Timeout settings
wait_timeout = 28800
interactive_timeout = 28800

# Binary log settings
binlog_cache_size = 1M
max_binlog_cache_size = 2G
max_binlog_size = 512M
expire_logs_days = 7

# Replication settings
slave_net_timeout = 60
slave_compressed_protocol = 1
```

### Security Hardening

#### MySQL Security
```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove remote root access
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Remove test database
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';

-- Reload privilege tables
FLUSH PRIVILEGES;
```

#### SSL Configuration for Replication
```bash
# Generate SSL certificates
sudo mysql_ssl_rsa_setup --uid=mysql

# Configure MySQL for SSL
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

```ini
[mysqld]
ssl-ca=/var/lib/mysql/ca.pem
ssl-cert=/var/lib/mysql/server-cert.pem
ssl-key=/var/lib/mysql/server-key.pem
```

### Backup and Recovery Strategies

#### Automated Backup Script
```bash
#!/bin/bash
# mysql_backup.sh

BACKUP_DIR="/var/backups/mysql"
MYSQL_USER="backup_user"
MYSQL_PASSWORD="backup_password"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup with mysqldump
mysqldump --single-transaction --routines --triggers \
    --all-databases \
    -u $MYSQL_USER -p$MYSQL_PASSWORD \
    | gzip > $BACKUP_DIR/full_backup_$DATE.sql.gz

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: full_backup_$DATE.sql.gz"
```

#### Binary Log Backup
```bash
#!/bin/bash
# binlog_backup.sh

BINLOG_DIR="/var/lib/mysql"
BACKUP_DIR="/var/backups/mysql/binlogs"
DATE=$(date +%Y%m%d)

# Create backup directory
mkdir -p $BACKUP_DIR/$DATE

# Copy binary logs
cp $BINLOG_DIR/mysql-bin.* $BACKUP_DIR/$DATE/

# Flush logs to create new binary log
mysql -u root -p -e "FLUSH LOGS;"

echo "Binary log backup completed for $DATE"
```

### Monitoring and Logging

#### Enable MySQL Slow Query Log
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
```

#### Log Rotation Configuration
```bash
# /etc/logrotate.d/mysql-server
/var/log/mysql/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 640 mysql adm
    postrotate
        if test -x /usr/bin/mysqladmin && \
           /usr/bin/mysqladmin ping &>/dev/null
        then
           /usr/bin/mysqladmin flush-logs
        fi
    endscript
}
```

### Troubleshooting Common Issues

#### Replication Lag Issues
```sql
-- Check slave status
SHOW SLAVE STATUS\G

-- Check processlist on master
SHOW PROCESSLIST;

-- Optimize for replication
SET GLOBAL sync_binlog = 0;
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
```

#### Connection Issues
```bash
# Check MySQL process
ps aux | grep mysql

# Check port binding
netstat -tlnp | grep 3306

# Check MySQL error log
tail -f /var/log/mysql/error.log
```

#### Disk Space Issues
```bash
# Check disk usage
df -h

# Check MySQL data directory size
du -sh /var/lib/mysql/

# Clean old binary logs
mysql -u root -p -e "PURGE BINARY LOGS BEFORE DATE_SUB(NOW(), INTERVAL 3 DAY);"
```

---

## Best Practices and Recommendations

### Replication Best Practices
1. **Use GTID**: Enables easier failover and slave promotion
2. **Monitor Replication Lag**: Keep lag under acceptable limits
3. **Regular Testing**: Test failover procedures regularly
4. **Consistent Configuration**: Keep master and slave configurations similar
5. **Proper Indexing**: Ensure proper indexing on all servers

### Laravel Best Practices
1. **Connection Pooling**: Use ProxySQL or similar tools
2. **Query Optimization**: Optimize queries for both read and write operations
3. **Caching**: Implement Redis/Memcached to reduce database load
4. **Transaction Management**: Use database transactions appropriately
5. **Error Handling**: Implement proper error handling for connection failures

### Security Best Practices
1. **SSL/TLS**: Use encrypted connections
2. **User Privileges**: Follow principle of least privilege
3. **Network Security**: Restrict access to database ports
4. **Regular Updates**: Keep MySQL and system packages updated
5. **Audit Logging**: Enable MySQL audit logging

---

## Conclusion

This comprehensive guide covers all aspects of MySQL High Availability and Replication for Kali Linux and Laravel environments. The implementation of these concepts will provide:

- **High Availability**: Reduced downtime through redundancy
- **Scalability**: Better performance through load distribution
- **Data Protection**: Multiple copies of critical data
- **Disaster Recovery**: Quick recovery from failures

Remember to thoroughly test your replication setup in a development environment before implementing in production, and always have a comprehensive backup and recovery strategy in place.
