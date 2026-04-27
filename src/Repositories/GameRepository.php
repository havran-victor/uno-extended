<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class GameRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(string $gameId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM games WHERE id = ?');
        $st->execute([$gameId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function search(?string $status, ?int $maxPlayers): array
    {
        $where = [];
        $bind  = [];
        if ($status !== null) {
            $where[] = 'phase = ?';
            $bind[]  = $status;
        }
        if ($maxPlayers !== null) {
            $where[] = 'max_players = ?';
            $bind[]  = $maxPlayers;
        }
        $sql = 'SELECT * FROM games' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC';
        $st  = $this->pdo->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll();
    }

    public function insert(array $data): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO games (id, name, max_players, points_to_win, phase) VALUES (?, ?, ?, ?, "lobby")'
        );
        $st->execute([$data['id'], $data['name'], $data['max_players'], $data['points_to_win']]);
    }

    public function setHost(string $gameId, string $playerId): void
    {
        $this->pdo->prepare('UPDATE games SET host_player_id = ? WHERE id = ?')
            ->execute([$playerId, $gameId]);
    }

    public function update(string $gameId, array $columns): void
    {
        if ($columns === []) return;
        $set  = [];
        $bind = [];
        foreach ($columns as $col => $val) {
            $set[]  = "$col = ?";
            $bind[] = $val;
        }
        $bind[] = $gameId;
        $this->pdo->prepare('UPDATE games SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($bind);
    }
}
