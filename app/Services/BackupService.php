<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupService
{
    /**
     * Create a complete backup of the system.
     */
    public function createBackup(?Company $company = null, ?int $userId = null, string $type = 'manual', string $storageType = 'local'): Backup
    {
        $backup = Backup::create([
            'company_id' => null, // System-wide backup, not per company
            'user_id' => $userId,
            'name' => 'Backup ' . now()->format('Y-m-d H:i:s'),
            'type' => $type,
            'storage_type' => $storageType,
            'status' => 'in_progress',
            'file_name' => '',
            'file_path' => '',
            'file_size' => 0,
        ]);

        try {
            $backupPath = $this->performBackup($backup);
            
            // Get file size - use the path as returned by performBackup (relative to storage/app)
            $fileSize = 0;
            if (Storage::disk('local')->exists($backupPath)) {
                $fileSize = Storage::disk('local')->size($backupPath);
            } else {
                // Fallback: try to get size from filesystem directly
                $fullPath = storage_path('app/' . $backupPath);
                if (File::exists($fullPath)) {
                    $fileSize = File::size($fullPath);
                }
            }
            
            $backup->update([
                'status' => 'completed',
                'completed_at' => now(),
                'file_path' => $backupPath,
                'file_name' => basename($backupPath),
                'file_size' => $fileSize,
            ]);

            // Upload to cloud storage if specified
            if ($storageType !== 'local') {
                $this->uploadToCloud($backup, $storageType);
            }

            return $backup->fresh();
        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage(), [
                'backup_id' => $backup->id,
                'exception' => $e,
            ]);

            $backup->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform the actual backup operation.
     */
    protected function performBackup(Backup $backup): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = storage_path('app/backups');
        
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $zipPath = $backupDir . '/backup_' . $timestamp . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Failed to create backup archive');
        }

        // Backup database
        $this->backupDatabase($zip, $timestamp);

        // Backup storage files
        $this->backupStorage($zip);

        // Backup public files (if needed)
        $this->backupPublicFiles($zip);

        $zip->close();

        // Return path relative to storage/app for Storage facade
        $relativePath = 'backups/backup_' . $timestamp . '.zip';
        
        // Verify file exists
        if (!File::exists($zipPath)) {
            throw new \Exception('Backup file was not created successfully');
        }
        
        return $relativePath;
    }

    /**
     * Backup the database.
     */
    protected function backupDatabase(ZipArchive $zip, string $timestamp): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        $sqlFile = storage_path('app/backups/db_' . $timestamp . '.sql');

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $this->backupMySQL($database, $sqlFile);
                break;
            case 'pgsql':
                $this->backupPostgreSQL($database, $sqlFile);
                break;
            case 'sqlite':
                $this->backupSQLite($database, $sqlFile);
                break;
            default:
                throw new \Exception("Unsupported database driver: {$driver}");
        }

        if (File::exists($sqlFile)) {
            $zip->addFile($sqlFile, 'database.sql');
        }
    }

    /**
     * Backup MySQL database using mysqldump.
     */
    protected function backupMySQL(string $database, string $outputFile): void
    {
        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $username = $config['username'];
        $password = $config['password'];

        // Ensure output directory exists
        $outputDir = dirname($outputFile);
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // Build mysqldump command
        // Use --password=password format instead of --password password to avoid shell interpretation issues
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database)
        );

        // On Windows, handle output redirection differently
        if (PHP_OS_FAMILY === 'Windows') {
            $command .= ' > ' . escapeshellarg($outputFile) . ' 2>&1';
        } else {
            $command .= ' > ' . escapeshellarg($outputFile) . ' 2>&1';
        }

        exec($command, $output, $returnCode);

        // If mysqldump fails, try fallback method using Laravel DB
        if ($returnCode !== 0 || !File::exists($outputFile) || (File::exists($outputFile) && File::size($outputFile) === 0)) {
            // Check if mysqldump is available
            $mysqldumpCheck = PHP_OS_FAMILY === 'Windows' 
                ? 'where mysqldump 2>nul'
                : 'which mysqldump 2>/dev/null';
            exec($mysqldumpCheck, $checkOutput, $checkReturn);
            
            if ($checkReturn !== 0) {
                // Fallback to Laravel DB export
                Log::info('mysqldump not found, using Laravel DB fallback method');
                $this->backupMySQLFallback($database, $outputFile);
                return;
            }
            
            $errorMessage = !empty($output) ? implode("\n", $output) : 'Unknown error';
            
            // Try fallback method if mysqldump failed
            Log::warning('mysqldump failed, trying Laravel DB fallback method', [
                'error' => $errorMessage,
                'return_code' => $returnCode,
            ]);
            
            try {
                $this->backupMySQLFallback($database, $outputFile);
                return;
            } catch (\Exception $e) {
                throw new \Exception('Failed to backup MySQL database: ' . $errorMessage . ' | Fallback also failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Backup MySQL database using Laravel DB facade (fallback method).
     */
    protected function backupMySQLFallback(string $database, string $outputFile): void
    {
        $handle = fopen($outputFile, 'w');
        
        if (!$handle) {
            throw new \Exception('Failed to create output file: ' . $outputFile);
        }

        try {
            // Write header
            fwrite($handle, "-- MySQL dump created by Laravel Backup Service\n");
            fwrite($handle, "-- Database: {$database}\n");
            fwrite($handle, "-- Created: " . now()->toDateTimeString() . "\n\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n\n");

            // Get all tables
            $tables = DB::select('SHOW TABLES');
            
            foreach ($tables as $table) {
                // Handle different MySQL versions - the column name varies
                $tableArray = (array) $table;
                $tableName = reset($tableArray);
                
                // Write table structure
                fwrite($handle, "\n-- Table structure for table `{$tableName}`\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                
                $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                if (!empty($createTable)) {
                    // Handle different MySQL versions - column name varies
                    $createTableArray = (array) $createTable[0];
                    // Find the 'Create Table' key (case-insensitive)
                    $createTableSql = null;
                    foreach ($createTableArray as $key => $value) {
                        if (strtolower($key) === 'create table' || strtolower($key) === 'create_table') {
                            $createTableSql = $value;
                            break;
                        }
                    }
                    if ($createTableSql) {
                        fwrite($handle, $createTableSql . ";\n\n");
                    }
                }

                // Write table data
                fwrite($handle, "-- Dumping data for table `{$tableName}`\n");
                $rows = DB::table($tableName)->get();
                
                if ($rows->count() > 0) {
                    $columns = Schema::getColumnListing($tableName);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows->chunk(100) as $chunk) {
                        $values = [];
                        foreach ($chunk as $row) {
                            $rowValues = [];
                            foreach ($columns as $column) {
                                $value = $row->$column;
                                if ($value === null) {
                                    $rowValues[] = 'NULL';
                                } else {
                                    $rowValues[] = "'" . addslashes($value) . "'";
                                }
                            }
                            $values[] = '(' . implode(', ', $rowValues) . ')';
                        }
                        
                        fwrite($handle, "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n");
                        fwrite($handle, implode(",\n", $values) . ";\n\n");
                    }
                }
            }

            // Write footer
            fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        } finally {
            fclose($handle);
        }

        if (!File::exists($outputFile) || File::size($outputFile) === 0) {
            throw new \Exception('Fallback backup method failed to create valid SQL file');
        }
    }

    /**
     * Backup PostgreSQL database using pg_dump.
     */
    protected function backupPostgreSQL(string $database, string $outputFile): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'];
        $port = $config['port'] ?? 5432;
        $username = $config['username'];
        $password = $config['password'];

        putenv("PGPASSWORD={$password}");
        
        $command = sprintf(
            'pg_dump --host=%s --port=%s --username=%s --dbname=%s --file=%s --no-password',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $returnCode);
        putenv('PGPASSWORD=');

        if ($returnCode !== 0) {
            throw new \Exception('Failed to backup PostgreSQL database');
        }
    }

    /**
     * Backup SQLite database by copying the file.
     */
    protected function backupSQLite(string $database, string $outputFile): void
    {
        if (!File::exists($database)) {
            throw new \Exception('SQLite database file not found');
        }

        File::copy($database, $outputFile);
    }

    /**
     * Backup storage files.
     */
    protected function backupStorage(ZipArchive $zip): void
    {
        $storagePath = storage_path('app');
        $this->addDirectoryToZip($zip, $storagePath, 'storage', ['backups']);
    }

    /**
     * Backup public files.
     */
    protected function backupPublicFiles(ZipArchive $zip): void
    {
        $publicPath = public_path('storage');
        if (File::exists($publicPath)) {
            $this->addDirectoryToZip($zip, $publicPath, 'public');
        }
    }

    /**
     * Add directory to zip archive recursively.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPrefix, array $excludeDirs = []): void
    {
        $files = File::allFiles($dir);
        
        foreach ($files as $file) {
            $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            
            // Skip excluded directories
            $shouldExclude = false;
            foreach ($excludeDirs as $excludeDir) {
                if (str_starts_with($relativePath, $excludeDir)) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if (!$shouldExclude) {
                $zip->addFile($file->getPathname(), $zipPrefix . '/' . $relativePath);
            }
        }
    }

    /**
     * Upload backup to cloud storage.
     */
    protected function uploadToCloud(Backup $backup, string $storageType): void
    {
        $localPath = storage_path('app/' . $backup->file_path);
        
        if (!File::exists($localPath)) {
            throw new \Exception('Backup file not found locally');
        }

        switch ($storageType) {
            case 's3':
                $this->uploadToS3($backup, $localPath);
                break;
            case 'google_drive':
            case 'dropbox':
                // These would require additional packages and API setup
                // For now, we'll just log that cloud upload is not yet implemented
                Log::info("Cloud storage type {$storageType} not yet implemented");
                break;
        }
    }

    /**
     * Upload backup to S3.
     */
    protected function uploadToS3(Backup $backup, string $localPath): void
    {
        $s3Path = 'backups/' . $backup->file_name;
        Storage::disk('s3')->put($s3Path, File::get($localPath));
        
        $backup->update([
            'file_path' => $s3Path,
            'storage_type' => 's3',
        ]);
    }

    /**
     * Restore a backup.
     */
    public function restoreBackup(Backup $backup): void
    {
        if (!$backup->isCompleted()) {
            throw new \Exception('Cannot restore an incomplete backup');
        }

        // Download from cloud if needed
        $localPath = $this->ensureLocalCopy($backup);

        if (!File::exists($localPath)) {
            throw new \Exception('Backup file not found: ' . $localPath);
        }

        $zip = new ZipArchive();
        $zipResult = $zip->open($localPath);
        if ($zipResult !== true) {
            throw new \Exception('Failed to open backup archive. Error code: ' . $zipResult);
        }

        // Extract to temporary directory
        $tempDir = storage_path('app/restore_' . now()->timestamp);
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }
        File::makeDirectory($tempDir, 0755, true);
        
        if ($zip->extractTo($tempDir) !== true) {
            $zip->close();
            File::deleteDirectory($tempDir);
            throw new \Exception('Failed to extract backup archive');
        }
        $zip->close();

        try {
            // Restore database (backups and backup_schedules tables are excluded to preserve current records)
            $dbFile = $tempDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (File::exists($dbFile)) {
                Log::info('Restoring database from backup', ['file' => $dbFile]);
                $this->restoreDatabase($dbFile);
            } else {
                Log::warning('Database SQL file not found in backup', ['temp_dir' => $tempDir]);
            }

            // Restore storage files (excluding backups directory to avoid overwriting current backups)
            $storageDir = $tempDir . DIRECTORY_SEPARATOR . 'storage';
            if (File::exists($storageDir)) {
                Log::info('Restoring storage files from backup', ['source' => $storageDir]);
                $this->restoreStorage($storageDir);
            }

            // Restore public files
            $publicDir = $tempDir . DIRECTORY_SEPARATOR . 'public';
            if (File::exists($publicDir)) {
                Log::info('Restoring public files from backup', ['source' => $publicDir]);
                $this->restorePublicFiles($publicDir);
            }
        } catch (\Exception $e) {
            Log::error('Restore failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            // Cleanup
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Ensure we have a local copy of the backup file.
     */
    protected function ensureLocalCopy(Backup $backup): string
    {
        if ($backup->storage_type === 'local') {
            return storage_path('app/' . $backup->file_path);
        }

        // Download from cloud storage
        $localPath = storage_path('app/backups/temp_' . $backup->file_name);
        $contents = Storage::disk($backup->storage_type)->get($backup->file_path);
        File::put($localPath, $contents);

        return $localPath;
    }

    /**
     * Restore database from SQL file.
     */
    protected function restoreDatabase(string $sqlFile): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        // Disable foreign key checks before restore
        try {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to disable foreign key checks', ['error' => $e->getMessage()]);
        }

        try {
            switch ($driver) {
                case 'mysql':
                case 'mariadb':
                    $this->restoreMySQL($database, $sqlFile);
                    break;
                case 'pgsql':
                    $this->restorePostgreSQL($database, $sqlFile);
                    break;
                case 'sqlite':
                    $this->restoreSQLite($database, $sqlFile);
                    break;
                default:
                    throw new \Exception("Unsupported database driver: {$driver}");
            }
        } finally {
            // Re-enable foreign key checks after restore
            try {
                if ($driver === 'mysql' || $driver === 'mariadb') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = ON');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to re-enable foreign key checks', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Restore MySQL database.
     */
    protected function restoreMySQL(string $database, string $sqlFile): void
    {
        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $username = $config['username'];
        $password = $config['password'];

        if (!File::exists($sqlFile)) {
            throw new \Exception('SQL file not found: ' . $sqlFile);
        }

        // Check if mysql command is available
        $mysqlCheck = PHP_OS_FAMILY === 'Windows' 
            ? 'where mysql 2>nul'
            : 'which mysql 2>/dev/null';
        exec($mysqlCheck, $checkOutput, $checkReturn);
        
        if ($checkReturn !== 0) {
            // Fallback to Laravel DB method
            Log::info('mysql command not found, using Laravel DB fallback method for restore');
            $this->restoreMySQLFallback($database, $sqlFile);
            return;
        }

        // Build mysql command - use proper input redirection
        $sqlContent = File::get($sqlFile);
        
        // Exclude backups and backup_schedules tables from restore to preserve current backup records
        // Use more robust patterns that handle multi-line statements
        $sqlContent = preg_replace('/DROP\s+TABLE\s+IF\s+EXISTS\s+[`"]?backups[`"]?.*?;/is', '', $sqlContent);
        $sqlContent = preg_replace('/CREATE\s+TABLE\s+[`"]?backups[`"]?.*?ENGINE.*?;/is', '', $sqlContent);
        $sqlContent = preg_replace('/INSERT\s+(IGNORE\s+)?INTO\s+[`"]?backups[`"]?.*?;/is', '', $sqlContent);
        $sqlContent = preg_replace('/DROP\s+TABLE\s+IF\s+EXISTS\s+[`"]?backup_schedules[`"]?.*?;/is', '', $sqlContent);
        $sqlContent = preg_replace('/CREATE\s+TABLE\s+[`"]?backup_schedules[`"]?.*?ENGINE.*?;/is', '', $sqlContent);
        $sqlContent = preg_replace('/INSERT\s+(IGNORE\s+)?INTO\s+[`"]?backup_schedules[`"]?.*?;/is', '', $sqlContent);
        
        // Prepend foreign key disable and SQL mode settings
        // Also add commands to handle duplicates
        $prependSql = "SET FOREIGN_KEY_CHECKS=0;\n";
        $prependSql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $prependSql .= "SET UNIQUE_CHECKS=0;\n";
        $prependSql .= "SET AUTOCOMMIT=0;\n";
        
        // Replace INSERT INTO with INSERT IGNORE INTO to skip duplicates
        $sqlContent = preg_replace('/INSERT\s+INTO/i', 'INSERT IGNORE INTO', $sqlContent);
        
        $appendSql = "\nCOMMIT;\n";
        $appendSql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $appendSql .= "SET UNIQUE_CHECKS=1;\n";
        $appendSql .= "SET AUTOCOMMIT=1;";
        $sqlContent = $prependSql . $sqlContent . $appendSql;
        
        // On Windows, we need to use a different approach for input redirection
        if (PHP_OS_FAMILY === 'Windows') {
            // Write SQL to a temp file and use it
            $tempSqlFile = storage_path('app/restore_temp_' . uniqid() . '.sql');
            File::put($tempSqlFile, $sqlContent);
            
            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s < %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($tempSqlFile)
            );
            
            exec($command . ' 2>&1', $output, $returnCode);
            
            // Cleanup temp file
            if (File::exists($tempSqlFile)) {
                File::delete($tempSqlFile);
            }
        } else {
            // On Unix-like systems, use process pipes
            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database)
            );
            
            $descriptorspec = [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes);
            
            if (is_resource($process)) {
                fwrite($pipes[0], $sqlContent);
                fclose($pipes[0]);
                
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $returnCode = proc_close($process);
                
                if (!empty($errors)) {
                    $output = array_merge(explode("\n", $output), explode("\n", $errors));
                } else {
                    $output = explode("\n", $output);
                }
            } else {
                throw new \Exception('Failed to open mysql process');
            }
        }

        if ($returnCode !== 0) {
            $errorMessage = !empty($output) ? implode("\n", array_filter($output)) : 'Unknown error';
            
            // Try fallback method
            Log::warning('mysql command failed, trying Laravel DB fallback method', [
                'error' => $errorMessage,
                'return_code' => $returnCode,
            ]);
            
            try {
                $this->restoreMySQLFallback($database, $sqlFile);
                return;
            } catch (\Exception $e) {
                throw new \Exception('Failed to restore MySQL database: ' . $errorMessage . ' | Fallback also failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Restore MySQL database using Laravel DB facade (fallback method).
     */
    protected function restoreMySQLFallback(string $database, string $sqlFile): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        
        try {
            $sqlContent = File::get($sqlFile);
            
            // Exclude backups and backup_schedules tables from restore to preserve current backup records
            // Use more robust patterns that handle multi-line statements
            $sqlContent = preg_replace('/DROP\s+TABLE\s+IF\s+EXISTS\s+[`"]?backups[`"]?.*?;/is', '', $sqlContent);
            $sqlContent = preg_replace('/CREATE\s+TABLE\s+[`"]?backups[`"]?.*?ENGINE.*?;/is', '', $sqlContent);
            $sqlContent = preg_replace('/INSERT\s+(IGNORE\s+)?INTO\s+[`"]?backups[`"]?.*?;/is', '', $sqlContent);
            $sqlContent = preg_replace('/DROP\s+TABLE\s+IF\s+EXISTS\s+[`"]?backup_schedules[`"]?.*?;/is', '', $sqlContent);
            $sqlContent = preg_replace('/CREATE\s+TABLE\s+[`"]?backup_schedules[`"]?.*?ENGINE.*?;/is', '', $sqlContent);
            $sqlContent = preg_replace('/INSERT\s+(IGNORE\s+)?INTO\s+[`"]?backup_schedules[`"]?.*?;/is', '', $sqlContent);
            
            // Split SQL into individual statements
            // Remove comments and split by semicolons
            $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
            $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
            
            // Split SQL by semicolons, but keep multi-line statements together
            // Use a more robust method: split by semicolon but check for balanced parentheses
            $statements = [];
            $currentStatement = '';
            $parenCount = 0;
            $inString = false;
            $stringChar = '';
            $prevChar = '';
            
            $chars = str_split($sqlContent);
            foreach ($chars as $char) {
                $currentStatement .= $char;
                
                // Track string boundaries (handle escaped quotes)
                if (($char === '"' || $char === "'" || $char === '`') && $prevChar !== '\\') {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($char === $stringChar) {
                        $inString = false;
                        $stringChar = '';
                    }
                }
                
                // Track parentheses (but not inside strings)
                if (!$inString) {
                    if ($char === '(') {
                        $parenCount++;
                    } elseif ($char === ')') {
                        $parenCount--;
                    }
                }
                
                // Split on semicolon when not inside parentheses or strings
                if ($char === ';' && $parenCount === 0 && !$inString) {
                    $statement = trim($currentStatement);
                    if (!empty($statement) && strlen($statement) > 5) {
                        $statements[] = $statement;
                    }
                    $currentStatement = '';
                }
                
                $prevChar = $char;
            }
            
            // Add any remaining statement
            if (!empty(trim($currentStatement))) {
                $statements[] = trim($currentStatement);
            }
            
            // Execute statements
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strlen($statement) < 10) {
                    continue;
                }
                
                // Skip SET statements as we handle them separately
                if (stripos($statement, 'SET ') === 0) {
                    continue;
                }
                
                // Skip statements related to backups and backup_schedules tables
                if (preg_match('/\b(backups|backup_schedules)\b/i', $statement)) {
                    continue;
                }
                
                try {
                    // For INSERT statements, use INSERT IGNORE to skip duplicates
                    if (stripos($statement, 'INSERT INTO') === 0) {
                        $statement = str_ireplace('INSERT INTO', 'INSERT IGNORE INTO', $statement);
                    }
                    
                    // For DROP TABLE, use IF EXISTS
                    if (stripos($statement, 'DROP TABLE') === 0 && stripos($statement, 'IF EXISTS') === false) {
                        $statement = str_ireplace('DROP TABLE', 'DROP TABLE IF EXISTS', $statement);
                    }
                    
                    DB::unprepared($statement);
                } catch (\Exception $e) {
                    // Log but continue - some statements might fail
                    Log::warning('SQL statement failed during restore', [
                        'statement' => substr($statement, 0, 200),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Restore PostgreSQL database.
     */
    protected function restorePostgreSQL(string $database, string $sqlFile): void
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'];
        $port = $config['port'] ?? 5432;
        $username = $config['username'];
        $password = $config['password'];

        putenv("PGPASSWORD={$password}");
        
        $command = sprintf(
            'psql --host=%s --port=%s --username=%s --dbname=%s --file=%s --no-password',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($sqlFile)
        );

        exec($command, $output, $returnCode);
        putenv('PGPASSWORD=');

        if ($returnCode !== 0) {
            throw new \Exception('Failed to restore PostgreSQL database');
        }
    }

    /**
     * Restore SQLite database.
     */
    protected function restoreSQLite(string $database, string $sqlFile): void
    {
        File::copy($sqlFile, $database);
    }

    /**
     * Restore storage files.
     */
    protected function restoreStorage(string $sourceDir): void
    {
        $targetDir = storage_path('app');
        
        // Get all files and directories from source, excluding backups
        $items = File::allFiles($sourceDir);
        
        foreach ($items as $item) {
            $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
            
            // Skip backups directory to avoid overwriting current backups
            if (str_starts_with($relativePath, 'backups')) {
                continue;
            }
            
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            $targetFileDir = dirname($targetPath);
            
            if (!File::exists($targetFileDir)) {
                File::makeDirectory($targetFileDir, 0755, true);
            }
            
            File::copy($item->getPathname(), $targetPath);
        }
    }

    /**
     * Restore public files.
     */
    protected function restorePublicFiles(string $sourceDir): void
    {
        $targetDir = public_path('storage');
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }
        File::copyDirectory($sourceDir, $targetDir);
    }

    /**
     * Clean up old backups based on retention policy.
     */
    public function cleanupOldBackups(int $retentionDays = 30): void
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        $oldBackups = Backup::where('created_at', '<', $cutoffDate)
            ->where('status', 'completed')
            ->get();

        foreach ($oldBackups as $backup) {
            $this->deleteBackup($backup);
        }
    }

    /**
     * Delete a backup and its files.
     */
    public function deleteBackup(Backup $backup): void
    {
        if ($backup->storage_type === 'local') {
            $filePath = storage_path('app/' . $backup->file_path);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        } else {
            Storage::disk($backup->storage_type)->delete($backup->file_path);
        }

        $backup->delete();
    }
}

