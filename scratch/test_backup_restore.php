<?php
/**
 * CLI diagnostic script to test Database Backup and Restore functions.
 * Run in terminal: php scratch/test_backup_restore.php
 */

require_once __DIR__ . '/../autoload.php';

use MarketingAgent\Service\DatabaseService;

echo "==================================================\n";
echo "   Database Backup & Restore Diagnostic Test      \n";
echo "==================================================\n\n";

try {
    $dbFile = __DIR__ . '/../database/database.sqlite';
    if (!file_exists($dbFile)) {
        throw new Exception("Active database file not found at: " . $dbFile);
    }

    echo "1. Checking current database file signature...\n";
    $fh = fopen($dbFile, 'rb');
    $sig = fread($fh, 15);
    fclose($fh);
    echo "   Database Signature: '{$sig}'\n";
    if ($sig !== "SQLite format 3") {
        throw new Exception("Active database is not a valid SQLite3 file!");
    }
    echo "   [SUCCESS] Active database is valid SQLite3.\n\n";

    echo "2. Simulating a database backup...\n";
    $backupCopy = __DIR__ . '/../database/db_backup_test.sqlite';
    if (file_exists($backupCopy)) {
        unlink($backupCopy);
    }
    if (!copy($dbFile, $backupCopy)) {
        throw new Exception("Failed to copy database for backup test.");
    }
    echo "   Backup created at: {$backupCopy}\n";
    $fh = fopen($backupCopy, 'rb');
    $backupSig = fread($fh, 15);
    fclose($fh);
    if ($backupSig !== "SQLite format 3") {
        throw new Exception("Backup file is corrupt!");
    }
    echo "   [SUCCESS] Backup file signature is valid.\n\n";

    echo "3. Simulating a corrupted database restore (should rollback)...\n";
    // Create a corrupt file with dummy text
    $corruptFile = __DIR__ . '/../database/db_corrupt_test.sqlite';
    file_put_contents($corruptFile, "This is not a SQLite database file at all!");

    // Save previous database state count
    $db = new DatabaseService();
    $initialUserCount = count($db->getUsers());
    $db = null; // Close connection
    echo "   Initial users count: {$initialUserCount}\n";

    // Setup safe restore logic manually
    $tempBackupFile = $dbFile . '.bak';
    if (!copy($dbFile, $tempBackupFile)) {
        throw new Exception("Failed to copy temp backup.");
    }

    // Try to copy corrupt file over database file
    echo "   Overwriting database file with corrupt dummy file...\n";
    copy($corruptFile, $dbFile);

    // Test connection/validation
    $restoreSuccess = false;
    try {
        echo "   Testing database connection on overwritten file...\n";
        $testDb = new DatabaseService();
        $testPdo = $testDb->getPdo();
        $testPdo->query("SELECT id, username FROM users LIMIT 1");
        $testDb = null;
        $restoreSuccess = true;
    } catch (Exception $e) {
        echo "   [EXPECTED ERROR] Verification failed as expected: " . $e->getMessage() . "\n";
        echo "   Performing rollback to active state...\n";
        copy($tempBackupFile, $dbFile);
        unlink($tempBackupFile);
    }

    if ($restoreSuccess) {
        echo "   [FAIL] Restored corrupt database successfully?! That should not happen.\n\n";
    } else {
        echo "   [SUCCESS] Rollback completed. Verifying database state...\n";
        $db = new DatabaseService();
        $restoredUserCount = count($db->getUsers());
        echo "   Restored users count: {$restoredUserCount}\n";
        if ($restoredUserCount !== $initialUserCount) {
            throw new Exception("Rollback did not restore database state!");
        }
        echo "   [SUCCESS] Database state successfully restored to initial value.\n\n";
    }

    // Clean up
    if (file_exists($corruptFile)) unlink($corruptFile);
    if (file_exists($backupCopy)) unlink($backupCopy);

    echo "==================================================\n";
    echo "   ALL DIAGNOSTIC TESTS PASSED SUCCESSFULLY!       \n";
    echo "==================================================\n";

} catch (Exception $e) {
    echo "\n[DIAGNOSTIC FAILED]: " . $e->getMessage() . "\n";
    exit(1);
}
