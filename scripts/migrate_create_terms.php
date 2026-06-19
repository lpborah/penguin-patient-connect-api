<?php
declare(strict_types=1);

// Run from project root: php scripts/migrate_create_terms.php

require __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Load environment variables from project .env if present
if (file_exists(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
}

echo "Running migration: create_terms_and_conditions_master\n";

try {
    $sqlFile = __DIR__ . '/../migrations/create_terms_and_conditions_master.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("Migration file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException("Migration file is empty: $sqlFile");
    }

    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $pdo->commit();

    echo "Migration executed successfully.\n";
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
