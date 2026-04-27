<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class PlayerRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(string $playerId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM players WHERE id = ?');
        $st->execute([$playerId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findInGame(string $gameId, string $playerId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM players WHERE game_id = ? AND id = ?');
        $st->execute([$gameId, $playerId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByGameAndUser(string $gameId, string $userId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM players WHERE game_id = ? AND user_id = ?');
        $st->execute([$gameId, $userId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function listForGame(string $gameId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM players WHERE game_id = ? ORDER BY position ASC');
        $st->execute([$gameId]);
        return $st->fetchAll();
    }

    public function countInGame(string $gameId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM players WHERE game_id = ?');
        $st->execute([$gameId]);
        return (int) $st->fetchColumn();
    }

    public function nextPosition(string $gameId): int
    {
        $st = $this->pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM players WHERE game_id = ?');
        $st->execute([$gameId]);
        return (int) $st->fetchColumn();
    }

    public function insert(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO players (id, game_id, user_id, display_name, position) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['id'], $data['game_id'], $data['user_id'],
            $data['display_name'], $data['position'],
        ]);
    }

    public function setSaidUno(string $playerId, bool $value): void
    {
        $this->pdo->prepare('UPDATE players SET said_uno = ? WHERE id = ?')
            ->execute([$value ? 1 : 0, $playerId]);
    }

    public function bumpScore(string $playerId, int $delta): void
    {
        $this->pdo->prepare('UPDATE players SET total_score = total_score + ? WHERE id = ?')
            ->execute([$delta, $playerId]);
    }

    public static function cardCount(PDO $pdo, string $playerId): int
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM player_hands WHERE player_id = ?');
        $st->execute([$playerId]);
        return (int) $st->fetchColumn();
    }
}
