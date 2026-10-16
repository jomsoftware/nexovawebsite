<?php
namespace App\Support;

use PDO;

final class Db
{
    public static function dataDir(): string
    {
        $dir = $_ENV['DATA_DIR'] ?? __DIR__ . '/../../data';
        if (!is_dir($dir . '/uploads')) {
            mkdir($dir . '/uploads', 0775, true);
        }
        return realpath($dir);
    }

    public static function connect(): PDO
    {
        $pdo = new PDO('sqlite:' . self::dataDir() . '/app.db', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
        $pdo->exec(file_get_contents(__DIR__ . '/../../database/schema.sql'));
        return $pdo;
    }
}
