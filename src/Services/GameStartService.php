<?php
declare(strict_types=1);

namespace Uno\Services;

use PDO;
use Uno\Database\Exceptions\ForbiddenException;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\RoundRepository;
use Uno\Repositories\TurnRepository;

final class GameStartService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly RoundRepository $rounds,
        private readonly TurnRepository $turns,
        private readonly DeckService $deck,
        private readonly TurnService $turnService
    ) {}

    public function start(string $gameId, string $userId): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        if ($game['phase'] !== 'lobby') {
            throw new ValidationException('game is not in lobby', 'invalid_phase');
        }

        $hostPlayer = $game['host_player_id'] ? $this->players->findById($game['host_player_id']) : null;
        if ($hostPlayer === null || $hostPlayer['user_id'] !== $userId) {
            throw new ForbiddenException('only host can start the game', 'not_host');
        }

        $playerCount = $this->players->countInGame($gameId);
        if ($playerCount < 2) {
            throw new ValidationException('not enough players (min 2)', 'not_enough_players');
        }

        $this->pdo->beginTransaction();
        try {
            $deal = $this->deck->dealRound($gameId);
            $top  = $deal['topCard'];
            $activeColor = $deal['activeColor'];

            $playersList = $this->players->listForGame($gameId);
            $first = $playersList[0];
            $currentTurnPlayerId = $first['id'];
            $direction = 'clockwise';
            $pendingDraw = 0;
            $pendingColor = 0;

            switch ($top['type'] ?? '') {
                case 'skip':
                    $currentTurnPlayerId = $this->turnService->nextPlayerId($gameId, $first['id'], $direction);
                    break;
                case 'reverse':
                    if ($playerCount === 2) {
                        $currentTurnPlayerId = $this->turnService->nextPlayerId($gameId, $first['id'], $direction);
                    } else {
                        $direction = 'counter_clockwise';
                    }
                    break;
                case 'draw_two':
                    $pendingDraw = 2;
                    break;
                case 'wild':
                    $pendingColor = 1;
                    break;
            }

            $this->games->update($gameId, [
                'phase'                  => 'playing',
                'current_round'          => 1,
                'current_turn_player_id' => $currentTurnPlayerId,
                'play_direction'         => $direction,
                'active_color'           => $activeColor,
                'pending_draw_count'     => $pendingDraw,
                'pending_color_choice'   => $pendingColor,
                'has_acted_this_turn'    => 0,
            ]);

            $this->rounds->start($gameId, 1);

            if ($top !== null) {
                $this->turns->record($gameId, 1, 0, $hostPlayer['id'], 'play_card', $top['id'], [
                    'note' => 'initial top card',
                    'topCardType' => $top['type'],
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'currentTurnPlayerId' => $currentTurnPlayerId,
            'hostPlayerId'        => $hostPlayer['id'],
        ];
    }
}
