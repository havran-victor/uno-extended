<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class PileRepository
{
    public function __construct(private readonly PDO $pdo) {}

    // draw pile

    public function drawCount(string $gameId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM draw_pile_cards WHERE game_id = ?');
        $st->execute([$gameId]);
        return (int) $st->fetchColumn();
    }

    public function pushDraw(string $gameId, string $cardId, int $position): void
    {
        $this->pdo->prepare('INSERT INTO draw_pile_cards (game_id, card_id, position) VALUES (?, ?, ?)')
            ->execute([$gameId, $cardId, $position]);
    }

    public function popDraw(string $gameId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, dp.position FROM draw_pile_cards dp JOIN cards c ON c.id = dp.card_id
             WHERE dp.game_id = ? ORDER BY dp.position DESC LIMIT 1'
        );
        $st->execute([$gameId]);
        $row = $st->fetch();
        if ($row === false) return null;

        $this->pdo->prepare('DELETE FROM draw_pile_cards WHERE game_id = ? AND card_id = ?')
            ->execute([$gameId, $row['id']]);

        unset($row['position']);
        return $row;
    }

    public function clearDraw(string $gameId): void
    {
        $this->pdo->prepare('DELETE FROM draw_pile_cards WHERE game_id = ?')->execute([$gameId]);
    }

    // discard pile

    public function discardCount(string $gameId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM discard_pile_cards WHERE game_id = ?');
        $st->execute([$gameId]);
        return (int) $st->fetchColumn();
    }

    public function topDiscard(string $gameId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT c.* FROM discard_pile_cards dp JOIN cards c ON c.id = dp.card_id
             WHERE dp.game_id = ? ORDER BY dp.position DESC LIMIT 1'
        );
        $st->execute([$gameId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function pushDiscard(string $gameId, string $cardId): int
    {
        $st = $this->pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM discard_pile_cards WHERE game_id = ?');
        $st->execute([$gameId]);
        $position = (int) $st->fetchColumn();

        $this->pdo->prepare('INSERT INTO discard_pile_cards (game_id, card_id, position) VALUES (?, ?, ?)')
            ->execute([$gameId, $cardId, $position]);
        return $position;
    }

    public function clearDiscard(string $gameId): void
    {
        $this->pdo->prepare('DELETE FROM discard_pile_cards WHERE game_id = ?')->execute([$gameId]);
    }

    public function reshuffleDiscardIntoDraw(string $gameId): int
    {
        $top = $this->topDiscard($gameId);
        if ($top === null) return 0;

        $st = $this->pdo->prepare(
            'SELECT card_id FROM discard_pile_cards WHERE game_id = ? AND card_id <> ?'
        );
        $st->execute([$gameId, $top['id']]);
        $cardIds = array_column($st->fetchAll(), 'card_id');
        if ($cardIds === []) return 0;

        $this->pdo->prepare('DELETE FROM discard_pile_cards WHERE game_id = ? AND card_id <> ?')
            ->execute([$gameId, $top['id']]);

        // resetam pozitia top la 0 ca sa creasca logic
        $this->pdo->prepare('UPDATE discard_pile_cards SET position = 0 WHERE game_id = ?')
            ->execute([$gameId]);

        // active_color ramane setat pe game - nu resetam culoarea wildului

        shuffle($cardIds);

        $startPos = $this->drawCount($gameId);
        foreach ($cardIds as $i => $cardId) {
            $this->pushDraw($gameId, $cardId, $startPos + $i);
        }
        return count($cardIds);
    }

    public function clearAll(string $gameId): void
    {
        $this->clearDraw($gameId);
        $this->clearDiscard($gameId);
    }
}
