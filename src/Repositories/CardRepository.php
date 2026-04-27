<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class CardRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findById(string $cardId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM cards WHERE id = ?');
        $st->execute([$cardId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function bulkInsertForGame(string $gameId, array $cards): void
    {
        $sql  = 'INSERT INTO cards (id, game_id, color, type, value) VALUES (?, ?, ?, ?, ?)';
        $st   = $this->pdo->prepare($sql);
        foreach ($cards as $c) {
            $st->execute([$c['id'], $gameId, $c['color'], $c['type'], $c['value']]);
        }
    }
}
