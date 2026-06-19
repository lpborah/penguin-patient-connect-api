<?php
$dir = __DIR__ . '/../uploads/transaction-receipts/';
$real = realpath($dir);
$test = @file_put_contents($dir . 'test_write.txt', 'ok');
header('Content-Type: application/json');
echo json_encode([
    'dir_raw'      => $dir,
    'dir_resolved' => $real ?: 'NOT FOUND',
    'is_dir'       => is_dir($dir),
    'is_writable'  => is_writable($dir),
    'write_test'   => $test !== false ? 'SUCCESS' : 'FAILED',
    'write_error'  => $test === false ? (error_get_last()['message'] ?? 'unknown') : null,
    'php_ini'      => php_ini_loaded_file(),
    'open_basedir' => ini_get('open_basedir') ?: 'none',
    'process_user' => get_current_user(),
], JSON_PRETTY_PRINT);
if ($test !== false) @unlink($dir . 'test_write.txt');
