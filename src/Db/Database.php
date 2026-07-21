<?php
declare(strict_types=1);

namespace TSL\Db;

final class Database
{
    private static ?\PDO $pdo = null;
    private static ?array $config = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $db = self::config()['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
            self::$pdo = new \PDO($dsn, $db['user'], $db['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function config(): array
    {
        if (self::$config === null) {
            $path = __DIR__ . '/../../config/config.php';
            if (!is_file($path)) {
                throw new \RuntimeException('config/config.php not found — copy config/config.sample.php and fill in real values.');
            }
            self::$config = require $path;
        }
        return self::$config;
    }
}
