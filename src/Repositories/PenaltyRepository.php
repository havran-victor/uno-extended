<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;
use Ramsey\Uuid\Uuid;

final class PenaltyRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function record(string $gameId, string $playerId, string $type, int $cardsPenalized, ?int $roundNumber): array
    {
        $id = Uuid::uuid4()->toString();
        $this->pdo->prepare(
            'INSERT INTO penalties (id, game_id, player_id, type, cards_penalized, round_number)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$id, $gameId, $playerId, $type, $cardsPenalized, $roundNumber]);

        return [
            'id'              => $id,
            'playerId'        => $playerId,
            'type'            => $type,
            'cardsPenalized'  => $cardsPenalized,
            'roundNumber'     => $roundNumber,
            'timestamp'       => date(DATE_ATOM),
        ];
    }

    public function listForGame(string $gameId, ?string $playerId, ?string $type): array
    {
        $where = ['game_id = ?'];
        $bind  = [$gameId];
        if ($playerId !== null) { $where[] = 'player_id = ?'; $bind[] = $playerId; }
        if ($type     !== null) { $where[] = 'type = ?';      $bind[] = $type; }

        $sql = 'SELECT * FROM penalties WHERE ' . implode(' AND ', $where) . ' ORDER BY timestamp ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll();
    }
}
