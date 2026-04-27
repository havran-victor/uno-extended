<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class HandRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function add(string $playerId, string $cardId): void
    {
        $this->pdo->prepare('INSERT INTO player_hands (player_id, card_id) VALUES (?, ?)')
            ->execute([$playerId, $cardId]);
    }

    public function remove(string $playerId, string $cardId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM player_hands WHERE player_id = ? AND card_id = ?');
        $st->execute([$playerId, $cardId]);
        return $st->rowCount() > 0;
    }

    public function has(string $playerId, string $cardId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM player_hands WHERE player_id = ? AND card_id = ? LIMIT 1');
        $st->execute([$playerId, $cardId]);
        return (bool) $st->fetchColumn();
    }

    public function listForPlayer(string $playerId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.* FROM player_hands ph JOIN cards c ON c.id = ph.card_id WHERE ph.player_id = ? ORDER BY c.color, c.type, c.value'
        );
        $st->execute([$playerId]);
        return $st->fetchAll();
    }

    public function clearForGame(string $gameId): void
    {
        $this->pdo->prepare(
            'DELETE ph FROM player_hands ph JOIN players p ON p.id = ph.player_id WHERE p.game_id = ?'
        )->execute([$gameId]);
    }
}
