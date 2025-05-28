# MySQL Database Backup and Recovery - Complete Guide

## Table of Contents
1. [Importance of Backups and Recovery Strategies](#importance)
2. [Types of MySQL Backups](#types)
3. [Performing Backups using mysqldump and Other Tools](#tools)
4. [Schedule Backups and Restore](#schedule)
5. [Strategies for Database Recovery in Case of Failures](#recovery)
6. [Kali Linux Specific Techniques](#kali)
7. [Laravel Integration](#laravel)

---

## 1. Importance of Backups and Recovery Strategies {#importance}

### Why Database Backups are Critical

Database backups serve as your safety net against various disasters and failures. They protect against:

- **Hardware failures**: Disk crashes, server failures, or system corruption
- **Human errors**: Accidental data deletion, incorrect updates, or schema changes
- **Software bugs**: Application errors that corrupt data
- **Security breaches**: Ransomware attacks or malicious data destruction
- **Natural disasters**: Fire, flood, or other catastrophic events
- **Compliance requirements**: Legal and regulatory data retention needs

### Recovery Time Objective (RTO) vs Recovery Point Objective (RPO)

- **RTO**: Maximum acceptable downtime after a failure
- **RPO**: Maximum acceptable data loss measured in time
- These metrics determine your backup strategy frequency and complexity

### Backup Strategy Fundamentals

A robust backup strategy follows the **3-2-1 Rule**:
- **3** copies of your data
- **2** different storage media types
- **1** offsite backup

---

## 2. Types of MySQL Backups {#types}

### Logical Backups
Logical backups export data as SQL statements that can recreate the database structure and data.

**Advantages:**
- Human-readable format
- Platform independent
- Selective backup/restore possible
- Can be version-controlled

**Disadvantages:**
- Slower for large databases
- Larger file sizes
- Requires more processing power

### Physical Backups
Physical backups copy the actual database files and directories.

**Advantages:**
- Faster backup and restore
- Smaller backup sizes
- Less CPU overhead

**Disadvantages:**
- Platform dependent
- All-or-nothing approach
- Requires consistent state

### Hot vs Cold vs Warm Backups

**Hot Backups (Online)**
- Database remains operational during backup
- No downtime required
- May have slight performance impact

**Cold Backups (Offline)**
- Database must be shut down
- Guaranteed consistency
- No performance impact during operation

**Warm Backups**
- Database is in read-only mode
- Limited operations allowed
- Balance between hot and cold approaches

### Full, Incremental, and Differential Backups

**Full Backup**
- Complete copy of entire database
- Self-contained and independent
- Largest storage requirement

**Incremental Backup**
- Only changes since last backup (any type)
- Smallest storage requirement
- Requires chain of backups for restore

**Differential Backup**
- Changes since last full backup
- Moderate storage requirement
- Requires only full + differential for restore

---

## 3. Performing Backups using mysqldump and Other Tools {#tools}

### mysqldump - The Standard Tool

#### Basic Syntax
```bash
mysqldump [options] database_name > backup_file.sql
```

#### Essential Options
```bash
# Basic database backup
mysqldump -u username -p database_name > backup.sql

# All databases
mysqldump -u root -p --all-databases > all_databases.sql

# Multiple specific databases
mysqldump -u root -p --databases db1 db2 db3 > multiple_dbs.sql

# Single table
mysqldump -u root -p database_name table_name > table_backup.sql

# Structure only (no data)
mysqldump -u root -p --no-data database_name > structure_only.sql

# Data only (no structure)
mysqldump -u root -p --no-create-info database_name > data_only.sql
```

#### Advanced mysqldump Options
```bash
# Optimized backup for large databases
mysqldump -u root -p \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --opt \
    database_name > optimized_backup.sql

# Compressed backup
mysqldump -u root -p database_name | gzip > backup.sql.gz

# Remote backup
mysqldump -h remote_host -u username -p database_name > remote_backup.sql
```

### Advanced Backup Tools

#### MySQL Enterprise Backup (Commercial)
```bash
# Full backup
mysqlbackup --user=root --password --backup-dir=/backup/full backup

# Incremental backup
mysqlbackup --user=root --password --backup-dir=/backup/inc --incremental backup
```

#### Percona XtraBackup (Free Alternative)
```bash
# Install on Ubuntu/Debian
sudo apt-get install percona-xtrabackup-24

# Full backup
xtrabackup --user=root --password=password --backup --target-dir=/backup/full

# Incremental backup
xtrabackup --user=root --password=password --backup --target-dir=/backup/inc \
    --incremental-basedir=/backup/full

# Prepare backup
xtrabackup --prepare --target-dir=/backup/full
```

#### MySQL Binary Logs for Point-in-Time Recovery
```bash
# Enable binary logging in my.cnf
[mysqld]
log-bin=mysql-bin
expire_logs_days=7
max_binlog_size=100M

# Show binary logs
SHOW BINARY LOGS;

# Backup binary log
mysqlbinlog mysql-bin.000001 > binlog_backup.sql
```

---

## 4. Schedule Backups and Restore {#schedule}

### Automated Backup Scripts

#### Basic Backup Script
```bash
#!/bin/bash
# backup_mysql.sh

# Configuration
DB_USER="backup_user"
DB_PASS="secure_password"
DB_NAME="your_database"
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql"

# Create backup directory
mkdir -p $BACKUP_DIR

# Perform backup
mysqldump -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --routines \
    --triggers \
    $DB_NAME > $BACKUP_FILE

# Compress backup
gzip $BACKUP_FILE

# Remove backups older than 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: ${BACKUP_FILE}.gz"
```

#### Advanced Backup Script with Error Handling
```bash
#!/bin/bash
# advanced_backup.sh

set -e  # Exit on any error

# Configuration
CONFIG_FILE="/etc/mysql/backup.conf"
LOG_FILE="/var/log/mysql_backup.log"

# Load configuration
source $CONFIG_FILE

# Logging function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

# Backup function
backup_database() {
    local db_name=$1
    local backup_file="$BACKUP_DIR/${db_name}_$(date +%Y%m%d_%H%M%S).sql"
    
    log "Starting backup of database: $db_name"
    
    mysqldump -u $DB_USER -p$DB_PASS \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        $db_name > $backup_file
    
    if [ $? -eq 0 ]; then
        gzip $backup_file
        log "Backup successful: ${backup_file}.gz"
        
        # Upload to remote storage (optional)
        if [ "$REMOTE_BACKUP" = "true" ]; then
            rsync -av ${backup_file}.gz $REMOTE_HOST:$REMOTE_DIR/
            log "Remote backup uploaded"
        fi
    else
        log "ERROR: Backup failed for database $db_name"
        exit 1
    fi
}

# Main execution
for db in $DATABASES; do
    backup_database $db
done

# Cleanup old backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
log "Cleanup completed"
```

### Cron Job Scheduling

#### Daily Backup at 2 AM
```bash
# Edit crontab
crontab -e

# Add this line for daily backup at 2:00 AM
0 2 * * * /usr/local/bin/backup_mysql.sh >> /var/log/mysql_backup.log 2>&1

# Weekly full backup on Sunday at 1:00 AM
0 1 * * 0 /usr/local/bin/full_backup.sh >> /var/log/mysql_backup.log 2>&1

# Incremental backup every 6 hours
0 */6 * * * /usr/local/bin/incremental_backup.sh >> /var/log/mysql_backup.log 2>&1
```

### Restore Procedures

#### Basic Restore
```bash
# Restore from SQL dump
mysql -u root -p database_name < backup.sql

# Restore compressed backup
gunzip < backup.sql.gz | mysql -u root -p database_name

# Create database before restore
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS new_database;"
mysql -u root -p new_database < backup.sql
```

#### Point-in-Time Recovery
```bash
# 1. Restore from full backup
mysql -u root -p database_name < full_backup.sql

# 2. Apply binary logs up to specific time
mysqlbinlog --stop-datetime="2024-01-15 10:30:00" \
    mysql-bin.000001 mysql-bin.000002 | mysql -u root -p database_name
```

---

## 5. Strategies for Database Recovery in Case of Failures {#recovery}

### Disaster Recovery Planning

#### Recovery Strategy Matrix
| Failure Type | Recovery Method | RTO | RPO |
|--------------|----------------|-----|-----|
| Hardware Failure | Hot Standby + Binary Logs | < 5 min | < 1 min |
| Corruption | Point-in-time Recovery | < 30 min | < 15 min |
| Human Error | Selective Restore | < 60 min | Variable |
| Site Disaster | Remote Replica | < 4 hours | < 1 hour |

#### High Availability Setup
```sql
-- Master-Slave Replication Setup
-- On Master Server
CHANGE MASTER TO
    MASTER_HOST='slave_server',
    MASTER_USER='replication_user',
    MASTER_PASSWORD='replication_password',
    MASTER_LOG_FILE='mysql-bin.000001',
    MASTER_LOG_POS=107;

START SLAVE;
```

### Recovery Scenarios and Solutions

#### Scenario 1: Accidental Table Drop
```bash
# 1. Stop MySQL to prevent further changes
sudo systemctl stop mysql

# 2. Restore from latest backup before the incident
mysql -u root -p database_name < backup_before_incident.sql

# 3. Apply binary logs up to just before the DROP statement
mysqlbinlog --stop-datetime="2024-01-15 14:25:00" \
    mysql-bin.000003 | mysql -u root -p database_name

# 4. Restart MySQL
sudo systemctl start mysql
```

#### Scenario 2: Database Corruption
```bash
# 1. Check and repair tables
mysqlcheck -u root -p --auto-repair --all-databases

# 2. If repair fails, restore from backup
mysql -u root -p -e "DROP DATABASE corrupted_db;"
mysql -u root -p -e "CREATE DATABASE corrupted_db;"
mysql -u root -p corrupted_db < latest_backup.sql
```

#### Scenario 3: Complete Server Failure
```bash
# 1. Set up new server with same MySQL version
# 2. Restore system databases
mysql -u root -p mysql < mysql_system_backup.sql

# 3. Restore all user databases
for backup in /backups/*.sql.gz; do
    db_name=$(basename $backup .sql.gz)
    gunzip < $backup | mysql -u root -p $db_name
done

# 4. Apply any remaining binary logs
mysqlbinlog --start-datetime="2024-01-15 02:00:00" \
    saved_binlogs/* | mysql -u root -p
```

---

## 6. Kali Linux Specific Techniques {#kali}

### Installing MySQL on Kali Linux

```bash
# Update package list
sudo apt update

# Install MySQL server
sudo apt install mysql-server mysql-client

# Secure installation
sudo mysql_secure_installation

# Start MySQL service
sudo systemctl start mysql
sudo systemctl enable mysql
```

### Kali-Specific Backup Tools and Techniques

#### Using Kali's Built-in Tools
```bash
# Install additional tools
sudo apt install percona-xtrabackup-24 mydumper

# High-performance backup with mydumper
mydumper -u root -p password -h localhost -B database_name -c -o /backup/

# Restore with myloader
myloader -u root -p password -h localhost -B database_name -d /backup/
```

#### Forensic Database Analysis
```bash
# Create forensic copy of MySQL data directory
sudo dd if=/var/lib/mysql/database_name/table_name.ibd \
    of=/forensic/table_backup.ibd conv=noerror,sync

# Analyze binary logs for security incidents
mysqlbinlog --start-datetime="2024-01-15 00:00:00" \
    --stop-datetime="2024-01-15 23:59:59" \
    mysql-bin.* | grep -i "suspicious_activity"
```

#### Penetration Testing Backup Scenarios
```bash
# Test backup integrity
#!/bin/bash
# backup_integrity_test.sh

BACKUP_FILE="/backup/test_backup.sql"
TEST_DB="backup_test_db"

# Create test backup
mysqldump -u root -p original_db > $BACKUP_FILE

# Create test database
mysql -u root -p -e "CREATE DATABASE $TEST_DB;"

# Restore to test database
mysql -u root -p $TEST_DB < $BACKUP_FILE

# Compare record counts
ORIGINAL_COUNT=$(mysql -u root -p -e "SELECT COUNT(*) FROM original_db.main_table;" | tail -1)
TEST_COUNT=$(mysql -u root -p -e "SELECT COUNT(*) FROM $TEST_DB.main_table;" | tail -1)

if [ "$ORIGINAL_COUNT" -eq "$TEST_COUNT" ]; then
    echo "Backup integrity verified"
else
    echo "WARNING: Backup integrity compromised"
fi

# Cleanup
mysql -u root -p -e "DROP DATABASE $TEST_DB;"
```

#### Automated Security Backup Script for Kali
```bash
#!/bin/bash
# security_backup_kali.sh

# Security-focused backup script for Kali Linux
BACKUP_DIR="/root/security_backups"
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="/var/log/security_backup.log"

# Create encrypted backup
backup_with_encryption() {
    local db_name=$1
    local backup_file="$BACKUP_DIR/${db_name}_${DATE}.sql"
    
    # Dump database
    mysqldump -u root -p$MYSQL_PASSWORD \
        --single-transaction \
        --hex-blob \
        $db_name > $backup_file
    
    # Encrypt backup
    gpg --symmetric --cipher-algo AES256 --compress-algo 1 \
        --s2k-mode 3 --s2k-digest-algo SHA512 --s2k-count 65011712 \
        --quiet --batch --passphrase "$GPG_PASSPHRASE" \
        $backup_file
    
    # Remove unencrypted file
    rm $backup_file
    
    echo "$(date): Encrypted backup created: ${backup_file}.gpg" >> $LOG_FILE
}

# Main execution
mkdir -p $BACKUP_DIR
backup_with_encryption "security_database"
```

---

## 7. Laravel Integration {#laravel}

### Laravel Database Configuration

#### Database Configuration (config/database.php)
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
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
        
        'backup' => [
            'driver' => 'mysql',
            'host' => env('BACKUP_DB_HOST', '127.0.0.1'),
            'port' => env('BACKUP_DB_PORT', '3306'),
            'database' => env('BACKUP_DB_DATABASE', 'laravel_backup'),
            'username' => env('BACKUP_DB_USERNAME', 'root'),
            'password' => env('BACKUP_DB_PASSWORD', ''),
        ],
    ],
];
```

### Laravel Backup Package Integration

#### Installation
```bash
# Install Spatie Laravel Backup package
composer require spatie/laravel-backup

# Publish configuration
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

#### Configuration (config/backup.php)
```php
<?php
// config/backup.php

return [
    'backup' => [
        'name' => env('APP_NAME', 'laravel-backup'),
        
        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                ],
            ],
            
            'databases' => [
                'mysql',
            ],
        ],
        
        'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,
        
        'destination' => [
            'filename_prefix' => '',
            'disks' => [
                'local',
                's3', // For cloud storage
            ],
        ],
        
        'temporary_directory' => storage_path('app/backup-temp'),
    ],
    
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class         => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class        => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class     => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class   => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class    => ['mail'],
        ],
        
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
        
        'mail' => [
            'to' => 'your-email@example.com',
        ],
    ],
    
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],
    
    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
```

### Laravel Artisan Commands for Backup

#### Basic Backup Commands
```bash
# Run backup
php artisan backup:run

# Run backup only for database
php artisan backup:run --only-db

# Run backup only for files
php artisan backup:run --only-files

# Run backup to specific disk
php artisan backup:run --only-to-disk=s3

# List all backups
php artisan backup:list

# Monitor backup health
php artisan backup:monitor

# Clean old backups
php artisan backup:clean
```

### Custom Laravel Backup Commands

#### Create Custom Backup Command
```bash
php artisan make:command DatabaseBackupCommand
```

```php
<?php
// app/Console/Commands/DatabaseBackupCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {--compress} {--tables=*}';
    protected $description = 'Create database backup with custom options';

    public function handle()
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $databaseName = config('database.connections.mysql.database');
        $fileName = "backup_{$databaseName}_{$timestamp}.sql";
        
        if ($this->option('compress')) {
            $fileName .= '.gz';
        }
        
        $this->info("Starting database backup...");
        
        try {
            $this->createBackup($fileName);
            $this->info("Backup created successfully: {$fileName}");
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function createBackup($fileName)
    {
        $config = config('database.connections.mysql');
        $tables = $this->option('tables');
        
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s %s %s',
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password'],
            $tables ? implode(' ', $tables) : '',
            $config['database'],
            $this->option('compress') ? '| gzip' : ''
        );
        
        $backupPath = storage_path("app/backups/{$fileName}");
        $fullCommand = "{$command} > {$backupPath}";
        
        exec($fullCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Backup command failed with return code: {$returnCode}");
        }
        
        // Store backup info in database
        DB::table('backup_logs')->insert([
            'filename' => $fileName,
            'size' => filesize($backupPath),
            'created_at' => now(),
        ]);
    }
}
```

### Laravel Backup Service

#### Create Backup Service
```php
<?php
// app/Services/BackupService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupService
{
    private $config;
    
    public function __construct()
    {
        $this->config = config('database.connections.mysql');
    }
    
    public function createFullBackup($compress = true)
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $fileName = "full_backup_{$timestamp}.sql";
        
        if ($compress) {
            $fileName .= '.gz';
        }
        
        $this->executeMysqldump($fileName, [], $compress);
        
        return $fileName;
    }
    
    public function createTableBackup($tables, $compress = true)
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $tableNames = implode('_', $tables);
        $fileName = "table_backup_{$tableNames}_{$timestamp}.sql";
        
        if ($compress) {
            $fileName .= '.gz';
        }
        
        $this->executeMysqldump($fileName, $tables, $compress);
        
        return $fileName;
    }
    
    public function restoreBackup($fileName)
    {
        $backupPath = storage_path("app/backups/{$fileName}");
        
        if (!file_exists($backupPath)) {
            throw new \Exception("Backup file not found: {$fileName}");
        }
        
        $isCompressed = pathinfo($fileName, PATHINFO_EXTENSION) === 'gz';
        
        $command = sprintf(
            '%s mysql -h%s -P%s -u%s -p%s %s',
            $isCompressed ? 'gunzip < ' : '',
            $this->config['host'],
            $this->config['port'],
            $this->config['username'],
            $this->config['password'],
            $this->config['database']
        );
        
        $fullCommand = $isCompressed 
            ? "gunzip < {$backupPath} | mysql -h{$this->config['host']} -P{$this->config['port']} -u{$this->config['username']} -p{$this->config['password']} {$this->config['database']}"
            : "mysql -h{$this->config['host']} -P{$this->config['port']} -u{$this->config['username']} -p{$this->config['password']} {$this->config['database']} < {$backupPath}";
        
        exec($fullCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Restore failed with return code: {$returnCode}");
        }
        
        return true;
    }
    
    private function executeMysqldump($fileName, $tables = [], $compress = false)
    {
        $tableList = empty($tables) ? '' : implode(' ', $tables);
        
        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s --single-transaction --routines --triggers %s %s',
            $this->config['host'],
            $this->config['port'],
            $this->config['username'],
            $this->config['password'],
            $this->config['database'],
            $tableList
        );
        
        if ($compress) {
            $command .= ' | gzip';
        }
        
        $backupPath = storage_path("app/backups/{$fileName}");
        $fullCommand = "{$command} > {$backupPath}";
        
        exec($fullCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Backup failed with return code: {$returnCode}");
        }
        
        // Log backup
        DB::table('backup_logs')->insert([
            'filename' => $fileName,
            'type' => empty($tables) ? 'full' : 'partial',
            'tables' => empty($tables) ? null : implode(',', $tables),
            'size' => filesize($backupPath),
            'compressed' => $compress,
            'created_at' => now(),
        ]);
    }
    
    public function getBackupList()
    {
        return DB::table('backup_logs')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function cleanupOldBackups($days = 7)
    {
        $cutoffDate = Carbon::now()->subDays($days);
        
        $oldBackups = DB::table('backup_logs')
            ->where('created_at', '<', $cutoffDate)
            ->get();
        
        foreach ($oldBackups as $backup) {
            $backupPath = storage_path("app/backups/{$backup->filename}");
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
        }
        
        DB::table('backup_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        return count($oldBackups);
    }
}
```

### Laravel Backup Controller

```php
<?php
// app/Http/Controllers/BackupController.php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackupController extends Controller
{
    private $backupService;
    
    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }
    
    public function index()
    {
        $backups = $this->backupService->getBackupList();
        return view('admin.backups.index', compact('backups'));
    }
    
    public function create(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,partial',
            'tables' => 'required_if:type,partial|array',
            'compress' => 'boolean',
        ]);
        
        try {
            if ($request->type === 'full') {
                $fileName = $this->backupService->createFullBackup($request->compress ?? true);
            } else {
                $fileName = $this->backupService->createTableBackup(
                    $request->tables, 
                    $request->compress ?? true
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $fileName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function restore(Request $request)
    {
        $request->validate([
            'filename' => 'required|string'
        ]);
        
        try {
            $this->backupService->restoreBackup($request->filename);
            
            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function download($filename)
    {
        $backupPath = storage_path("app/backups/{$filename}");
        
        if (!file_exists($backupPath)) {
            abort(404, 'Backup file not found');
        }
        
        return response()->download($backupPath);
    }
    
    public function delete($filename)
    {
        try {
            $backupPath = storage_path("app/backups/{$filename}");
            
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            DB::table('backup_logs')->where('filename', $filename)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function cleanup()
    {
        try {
            $deletedCount = $this->backupService->cleanupOldBackups(7);
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} old backups"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
```

### Laravel Scheduled Backups

#### Register Scheduled Tasks (app/Console/Kernel.php)
```php
<?php
// app/Console/Kernel.php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\DatabaseBackupCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Daily full backup at 2:00 AM
        $schedule->command('backup:run --only-db')
                 ->dailyAt('02:00')
                 ->emailOutputOnFailure('admin@example.com');
        
        // Weekly full backup (database + files) on Sunday at 1:00 AM
        $schedule->command('backup:run')
                 ->weekly()
                 ->sundays()
                 ->at('01:00')
                 ->emailOutputOnFailure('admin@example.com');
        
        // Custom database backup every 6 hours
        $schedule->command('db:backup --compress')
                 ->cron('0 */6 * * *')
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Monitor backup health daily
        $schedule->command('backup:monitor')
                 ->daily()
                 ->emailOutputOnFailure('admin@example.com');
        
        // Clean old backups weekly
        $schedule->command('backup:clean')
                 ->weekly()
                 ->fridays()
                 ->at('03:00');
        
        // Custom cleanup of local backups
        $schedule->call(function () {
            app(App\Services\BackupService::class)->cleanupOldBackups(30);
        })->weekly();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
```

### Laravel Backup Migration

#### Create Backup Logs Table
```bash
php artisan make:migration create_backup_logs_table
```

```php
<?php
// database/migrations/xxxx_xx_xx_create_backup_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBackupLogsTable extends Migration
{
    public function up()
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->enum('type', ['full', 'partial', 'incremental'])->default('full');
            $table->text('tables')->nullable();
            $table->bigInteger('size')->unsigned();
            $table->boolean('compressed')->default(false);
            $table->enum('status', ['success', 'failed', 'in_progress'])->default('success');
            $table->text('error_message')->nullable();
            $table->string('disk')->default('local');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['created_at', 'type']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('backup_logs');
    }
}
```

### Laravel Backup Events and Listeners

#### Create Backup Events
```bash
php artisan make:event BackupStarted
php artisan make:event BackupCompleted
php artisan make:event BackupFailed
```

```php
<?php
// app/Events/BackupCompleted.php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filename;
    public $size;
    public $duration;

    public function __construct($filename, $size, $duration)
    {
        $this->filename = $filename;
        $this->size = $size;
        $this->duration = $duration;
    }
}
```

#### Create Backup Listener
```bash
php artisan make:listener SendBackupNotification
```

```php
<?php
// app/Listeners/SendBackupNotification.php

namespace App\Listeners;

use App\Events\BackupCompleted;
use Illuminate\Support\Facades\Mail;
use App\Mail\BackupCompletedMail;

class SendBackupNotification
{
    public function handle(BackupCompleted $event)
    {
        // Send email notification
        Mail::to(config('backup.notifications.mail.to'))
            ->send(new BackupCompletedMail($event));
        
        // Log to system log
        logger()->info('Backup completed', [
            'filename' => $event->filename,
            'size' => $event->size,
            'duration' => $event->duration,
        ]);
        
        // Send to monitoring service (optional)
        // $this->sendToMonitoringService($event);
    }
}
```

### Advanced Laravel Backup Features

#### Backup Model with Relationships
```php
<?php
// app/Models/Backup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Backup extends Model
{
    use HasFactory;
    
    protected $table = 'backup_logs';
    
    protected $fillable = [
        'filename', 'type', 'tables', 'size', 'compressed',
        'status', 'error_message', 'disk', 'metadata'
    ];
    
    protected $casts = [
        'compressed' => 'boolean',
        'metadata' => 'array',
        'size' => 'integer',
    ];
    
    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
    
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
    
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
    
    // Accessors
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    public function getFileExistsAttribute()
    {
        $path = storage_path("app/backups/{$this->filename}");
        return file_exists($path);
    }
    
    // Methods
    public function download()
    {
        $path = storage_path("app/backups/{$this->filename}");
        
        if (!$this->file_exists) {
            throw new \Exception("Backup file not found: {$this->filename}");
        }
        
        return response()->download($path);
    }
    
    public function delete()
    {
        $path = storage_path("app/backups/{$this->filename}");
        
        if (file_exists($path)) {
            unlink($path);
        }
        
        return parent::delete();
    }
}
```

#### Backup Job for Queue Processing
```bash
php artisan make:job CreateDatabaseBackup
```

```php
<?php
// app/Jobs/CreateDatabaseBackup.php

namespace App\Jobs;

use App\Services\BackupService;
use App\Events\BackupStarted;
use App\Events\BackupCompleted;
use App\Events\BackupFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $options;
    public $timeout = 3600; // 1 hour timeout

    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function handle(BackupService $backupService)
    {
        $startTime = microtime(true);
        
        event(new BackupStarted($this->options));
        
        try {
            $filename = isset($this->options['tables']) 
                ? $backupService->createTableBackup(
                    $this->options['tables'], 
                    $this->options['compress'] ?? true
                )
                : $backupService->createFullBackup($this->options['compress'] ?? true);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            $size = filesize(storage_path("app/backups/{$filename}"));
            
            event(new BackupCompleted($filename, $size, $duration));
            
        } catch (\Exception $e) {
            event(new BackupFailed($e->getMessage(), $this->options));
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        event(new BackupFailed($exception->getMessage(), $this->options));
    }
}
```

### Backup API Routes

#### API Routes (routes/api.php)
```php
<?php
// routes/api.php

use App\Http\Controllers\BackupController;

Route::middleware(['auth:sanctum'])->prefix('backups')->group(function () {
    Route::get('/', [BackupController::class, 'index']);
    Route::post('/', [BackupController::class, 'create']);
    Route::post('/restore', [BackupController::class, 'restore']);
    Route::get('/download/{filename}', [BackupController::class, 'download']);
    Route::delete('/{filename}', [BackupController::class, 'delete']);
    Route::post('/cleanup', [BackupController::class, 'cleanup']);
    Route::get('/status', [BackupController::class, 'status']);
});
```

### Environment Configuration

#### Example .env Configuration
```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_app
DB_USERNAME=root
DB_PASSWORD=secure_password

# Backup Database (Optional)
BACKUP_DB_HOST=127.0.0.1
BACKUP_DB_PORT=3306
BACKUP_DB_DATABASE=laravel_backup
BACKUP_DB_USERNAME=backup_user
BACKUP_DB_PASSWORD=backup_password

# Backup Storage
BACKUP_DISK=local
BACKUP_S3_DISK=s3

# Backup Notifications
BACKUP_MAIL_TO=admin@example.com
BACKUP_NOTIFICATION_SLACK_WEBHOOK=https://hooks.slack.com/services/...

# Backup Settings
BACKUP_RETENTION_DAYS=30
BACKUP_COMPRESS=true
BACKUP_ENCRYPT=false
BACKUP_ENCRYPTION_KEY=your-encryption-key
```

---

## Best Practices and Security Considerations

### Security Best Practices

1. **Encrypt Sensitive Backups**
   - Use GPG encryption for backups containing sensitive data
   - Store encryption keys separately from backups
   - Implement key rotation policies

2. **Access Control**
   - Create dedicated backup users with minimal privileges
   - Use strong passwords and consider key-based authentication
   - Implement IP whitelisting for backup operations

3. **Backup Verification**
   - Regularly test backup integrity
   - Perform periodic restore tests
   - Monitor backup sizes and completion times

4. **Storage Security**
   - Use encrypted storage for backup files
   - Implement proper file permissions
   - Consider offsite and cloud storage options

### Performance Optimization

1. **Backup Timing**
   - Schedule backups during low-traffic periods
   - Use incremental backups for frequently changing data
   - Implement backup compression

2. **Resource Management**
   - Monitor disk space for backup storage
   - Use background processes for large backups
   - Implement backup queue management

3. **Network Optimization**
   - Use local storage for initial backups
   - Compress backups before remote transfer
   - Implement bandwidth throttling if necessary

### Monitoring and Alerting

1. **Backup Success Monitoring**
   - Log all backup operations
   - Set up alerts for failed backups
   - Monitor backup file sizes and timing

2. **Storage Monitoring**
   - Track backup storage usage
   - Alert on low disk space
   - Monitor backup retention policies

3. **Recovery Testing**
   - Schedule regular recovery tests
   - Document recovery procedures
   - Train team members on recovery processes

---

## Conclusion

This comprehensive guide covers all aspects of MySQL database backup and recovery, from basic concepts to advanced implementation in Kali Linux and Laravel environments. Key takeaways include:

- **Backup Strategy**: Implement a robust 3-2-1 backup strategy with appropriate RTO/RPO targets
- **Tool Selection**: Choose the right backup tools based on your specific requirements
- **Automation**: Use scheduled backups and monitoring to ensure consistent data protection
- **Security**: Implement proper encryption and access controls for backup data
- **Testing**: Regularly test backup integrity and recovery procedures
- **Documentation**: Maintain clear procedures for backup and recovery operations

Remember that a backup is only as good as your ability to restore from it. Regular testing and maintenance of your backup and recovery procedures are essential for ensuring business continuity and data protection.
