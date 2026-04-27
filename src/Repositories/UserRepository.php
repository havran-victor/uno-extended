<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(string $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, email, display_name, avatar_url, created_at FROM users WHERE id = ?'
        );
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, email, display_name, avatar_url, password_hash, created_at FROM users WHERE email = ?'
        );
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function insert(string $id, string $email, string $displayName, string $passwordHash, ?string $avatarUrl = null): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO users (id, email, display_name, password_hash, avatar_url) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$id, $email, $displayName, $passwordHash, $avatarUrl]);

        $this->pdo->prepare('INSERT INTO player_stats (user_id) VALUES (?)')->execute([$id]);
    }
}
