<?php
declare(strict_types=1);

namespace Uno\Database;

use PDO;

final class PdoFactory
{
    public static function create(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'] ?? 'localhost',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? 'uno_extended'
        );

        return new PDO($dsn, $config['user'] ?? 'root', $config['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
