<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class LeaderboardRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function totalCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM player_stats')->fetchColumn();
    }

    public function fetchPage(string $sortBy, int $page, int $pageSize): array
    {
        $orderColumn = match ($sortBy) {
            'winRate'     => 'CASE WHEN ps.games_played = 0 THEN 0 ELSE ps.games_won / ps.games_played END',
            'totalPoints' => 'ps.total_points',
            default       => 'ps.games_won',
        };

        $offset = max(0, ($page - 1) * $pageSize);
        $sql = "
            SELECT ps.user_id, u.display_name, ps.games_played, ps.games_won,
                   ps.total_points,
                   CASE WHEN ps.games_played = 0 THEN 0 ELSE ps.games_won / ps.games_played END AS win_rate
            FROM player_stats ps
            JOIN users u ON u.id = ps.user_id
            ORDER BY {$orderColumn} DESC, u.display_name ASC
            LIMIT {$pageSize} OFFSET {$offset}
        ";
        return $this->pdo->query($sql)->fetchAll();
    }
}
