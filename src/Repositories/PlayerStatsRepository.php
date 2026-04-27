<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class PlayerStatsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT user_id, games_played, games_won, total_points, cards_played,
                    uno_calls_made, challenges_won, challenges_lost
             FROM player_stats WHERE user_id = ?'
        );
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function ensureExists(string $userId): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO player_stats (user_id) VALUES (?)'
        )->execute([$userId]);
    }

    public function bumpAfterGame(string $userId, bool $won, int $pointsDelta): void
    {
        $this->ensureExists($userId);
        $this->pdo->prepare(
            'UPDATE player_stats
             SET games_played = games_played + 1,
                 games_won    = games_won + ?,
                 total_points = total_points + ?
             WHERE user_id = ?'
        )->execute([$won ? 1 : 0, $pointsDelta, $userId]);
    }

    public function bumpCardPlayed(string $userId): void
    {
        $this->ensureExists($userId);
        $this->pdo->prepare(
            'UPDATE player_stats SET cards_played = cards_played + 1 WHERE user_id = ?'
        )->execute([$userId]);
    }

    public function bumpUnoCall(string $userId): void
    {
        $this->ensureExists($userId);
        $this->pdo->prepare(
            'UPDATE player_stats SET uno_calls_made = uno_calls_made + 1 WHERE user_id = ?'
        )->execute([$userId]);
    }
}
