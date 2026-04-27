<?php
declare(strict_types=1);

namespace Uno\Repositories;

use PDO;

final class RoundRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function start(string $gameId, int $roundNumber): void
    {
        $this->pdo->prepare(
            'INSERT INTO rounds (game_id, round_number, started_at) VALUES (?, ?, NOW(3))'
        )->execute([$gameId, $roundNumber]);
    }

    public function end(string $gameId, int $roundNumber, string $winnerId): void
    {
        $this->pdo->prepare(
            'UPDATE rounds SET winner_id = ?, ended_at = NOW(3) WHERE game_id = ? AND round_number = ?'
        )->execute([$winnerId, $gameId, $roundNumber]);
    }

    public function listForGame(string $gameId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM rounds WHERE game_id = ? ORDER BY round_number ASC');
        $st->execute([$gameId]);
        return $st->fetchAll();
    }

    public function find(string $gameId, int $roundNumber): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM rounds WHERE game_id = ? AND round_number = ?');
        $st->execute([$gameId, $roundNumber]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function recordScore(string $gameId, int $roundNumber, string $playerId, int $score): void
    {
        $this->pdo->prepare(
            'INSERT INTO round_scores (game_id, round_number, player_id, score) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score)'
        )->execute([$gameId, $roundNumber, $playerId, $score]);
    }

    public function listScoresFor(string $gameId, int $roundNumber): array
    {
        $st = $this->pdo->prepare(
            'SELECT player_id, score FROM round_scores WHERE game_id = ? AND round_number = ?'
        );
        $st->execute([$gameId, $roundNumber]);
        return $st->fetchAll();
    }

    public function listScoresForGame(string $gameId): array
    {
        $st = $this->pdo->prepare(
            'SELECT round_number, player_id, score FROM round_scores WHERE game_id = ? ORDER BY round_number ASC'
        );
        $st->execute([$gameId]);
        return $st->fetchAll();
    }
}
