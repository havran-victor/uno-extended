<?php
declare(strict_types=1);

namespace Uno\Services;

use PDO;
use Uno\Repositories\GameRepository;
use Uno\Repositories\HandRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\PlayerStatsRepository;
use Uno\Repositories\RoundRepository;

final class ScoringService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly HandRepository $hands,
        private readonly RoundRepository $rounds,
        private readonly PlayerStatsRepository $stats,
        private readonly DeckService $deck
    ) {}

    // scoru e suma cartilor ramase la ceilalti - daca se atinge points_to_win jocul se termina
    public function finishRoundFromHands(string $gameId, string $winnerPlayerId): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) return [];

        $round = (int) $game['current_round'];

        $players = $this->players->listForGame($gameId);
        $totalPoints = 0;
        foreach ($players as $p) {
            if ($p['id'] === $winnerPlayerId) continue;
            $cards = $this->hands->listForPlayer($p['id']);
            $sum = 0;
            foreach ($cards as $c) $sum += self::cardPoints($c);
            $this->rounds->recordScore($gameId, $round, $p['id'], $sum); // puncte donate castigatorului
            $totalPoints += $sum;
        }
        $this->rounds->recordScore($gameId, $round, $winnerPlayerId, 0);
        $this->players->bumpScore($winnerPlayerId, $totalPoints);
        $this->rounds->end($gameId, $round, $winnerPlayerId);

        $winner = $this->players->findById($winnerPlayerId);
        $finalScore = (int) $winner['total_score'];

        if ($finalScore >= (int) $game['points_to_win']) {
            $this->finishGame($gameId, $winnerPlayerId);
        } else {
            $this->startNextRound($gameId, $round + 1);
        }

        return [];
    }

    public static function cardPoints(array $card): int
    {
        return match ($card['type']) {
            'number'         => (int) $card['value'],
            'skip', 'reverse', 'draw_two' => 20,
            'wild', 'wild_draw_four', 'blank_wild' => 50,
            default => 0,
        };
    }

    private function finishGame(string $gameId, string $winnerPlayerId): void
    {
        $this->games->update($gameId, [
            'phase'                  => 'finished',
            'current_turn_player_id' => null,
            'pending_draw_count'     => 0,
        ]);

        foreach ($this->players->listForGame($gameId) as $p) {
            $won = $p['id'] === $winnerPlayerId;
            $this->stats->bumpAfterGame($p['user_id'], $won, (int) $p['total_score']);
        }
    }

    private function startNextRound(string $gameId, int $newRoundNumber): void
    {
        $this->games->update($gameId, [
            'phase'                 => 'playing',
            'current_round'         => $newRoundNumber,
            'pending_draw_count'    => 0,
            'pending_color_choice'  => 0,
            'has_acted_this_turn'   => 0,
        ]);

        $this->rounds->start($gameId, $newRoundNumber);

        // dealRound face si cleanup pe pile si maini
        $deal = $this->deck->dealRound($gameId);

        $players = $this->players->listForGame($gameId);
        $first   = $players[0];

        $top = $deal['topCard'];
        $direction = 'clockwise';
        $skip = 0;
        $pendingDraw = 0;
        $pendingColor = 0;

        if ($top !== null) {
            switch ($top['type']) {
                case 'skip':     $skip = 1; break;
                case 'reverse':
                    if (count($players) === 2) $skip = 1;
                    else $direction = 'counter_clockwise';
                    break;
                case 'draw_two': $pendingDraw = 2; break;
                case 'wild':     $pendingColor = 1; break;
            }
        }

        $turnService = new TurnService($this->players);
        $currentTurnPlayerId = $skip === 0
            ? $first['id']
            : $turnService->nextPlayerId($gameId, $first['id'], $direction);

        $this->games->update($gameId, [
            'current_turn_player_id' => $currentTurnPlayerId,
            'play_direction'         => $direction,
            'active_color'           => $deal['activeColor'],
            'pending_draw_count'     => $pendingDraw,
            'pending_color_choice'   => $pendingColor,
            'has_acted_this_turn'    => 0,
        ]);
    }
}
