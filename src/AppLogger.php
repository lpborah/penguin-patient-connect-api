<?php
declare(strict_types=1);

namespace App;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class AppLogger
{
    private static $logger = null;

    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('api');

            // Log to file with rotation (keeps last 30 days)
            $handler = new RotatingFileHandler(__DIR__ . '/../logs/app.log', 30, Logger::DEBUG);
            
            // Custom format: [datetime] level: message context
            $formatter = new LineFormatter(
                "[%datetime%] %level_name%: %message% %context%\n",
                "Y-m-d H:i:s"
            );
            $handler->setFormatter($formatter);
            
            self::$logger->pushHandler($handler);
        }

        return self::$logger;
    }

    public static function info(string $user, string $message, array $context = []): void
    {
        self::getLogger()->info("[". $user ."] ". $message, $context);
    }

    public static function error(string $user, string $message, array $context = []): void
    {
        self::getLogger()->error("[". $user ."] ".$message, $context);
    }

    public static function warning(string $user, string $message, array $context = []): void
    {
        self::getLogger()->warning("[". $user ."] ".$message, $context);
    }

    public static function debug(string $user, string $message, array $context = []): void
    {
        self::getLogger()->debug("[". $user ."] ".$message, $context);
    }
}
