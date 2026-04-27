<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;
use Ramsey\Uuid\Uuid;

final class TurnRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function record(string $gameId, int $roundNumber, int $turnNumber, string $playerId, string $action, ?string $cardId = null, ?array $details = null): string
    {
        $id = Uuid::uuid4()->toString();
        $st = $this->pdo->prepare(
            'INSERT INTO turns (id, game_id, round_number, turn_number, player_id, action, card_id, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $id, $gameId, $roundNumber, $turnNumber, $playerId, $action, $cardId,
            $details === null ? null : json_encode($details, JSON_UNESCAPED_SLASHES),
        ]);
        return $id;
    }

    public function nextTurnNumber(string $gameId, int $roundNumber): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(turn_number), 0) + 1 FROM turns WHERE game_id = ? AND round_number = ?'
        );
        $st->execute([$gameId, $roundNumber]);
        return (int) $st->fetchColumn();
    }

    public function findFiltered(string $gameId, ?int $roundNumber, ?string $playerId, ?string $action): array
    {
        $where = ['game_id = ?'];
        $bind  = [$gameId];
        if ($roundNumber !== null) { $where[] = 'round_number = ?'; $bind[] = $roundNumber; }
        if ($playerId    !== null) { $where[] = 'player_id = ?';    $bind[] = $playerId; }
        if ($action      !== null) { $where[] = 'action = ?';       $bind[] = $action; }

        $sql = 'SELECT * FROM turns WHERE ' . implode(' AND ', $where) .
               ' ORDER BY round_number ASC, turn_number ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll();
    }
}
