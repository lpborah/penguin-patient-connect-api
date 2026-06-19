<?php
$dir = __DIR__ . '/../uploads/transaction-receipts/';
$real = realpath($dir);
echo "Resolved: " . ($real ? $real : "NOT FOUND (raw: $dir)") . "\n";
echo "is_dir: " . (is_dir($dir) ? "YES" : "NO") . "\n";
echo "is_writable: " . (is_writable($dir) ? "YES" : "NO") . "\n";
$test = @file_put_contents($dir . "test_write.txt", "test");
echo "Write test: " . ($test !== false ? "SUCCESS" : "FAILED - " . error_get_last()['message']) . "\n";
if ($test !== false) unlink($dir . "test_write.txt");
