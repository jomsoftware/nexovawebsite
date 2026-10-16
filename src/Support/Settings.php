<?php
namespace App\Support;

use PDO;

final class Settings
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function save(array $values): void
    {
        $st = $this->db->prepare('INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
        foreach ($values as $k => $v) {
            $st->execute([$k, $v]);
        }
    }
}
