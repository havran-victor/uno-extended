<?php
declare(strict_types=1);

namespace Uno\Services;

use PDO;
use Uno\Database\Exceptions\ForbiddenException;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\CardRepository;
use Uno\Repositories\GameRepository;
use Uno\Repositories\GameSettingsRepository;
use Uno\Repositories\HandRepository;
use Uno\Repositories\PenaltyRepository;
use Uno\Repositories\PileRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\TurnRepository;

final class PlayCardService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GameRepository $games,
        private readonly GameSettingsRepository $settings,
        private readonly PlayerRepository $players,
        private readonly HandRepository $hands,
        private readonly PileRepository $piles,
        private readonly CardRepository $cards,
        private readonly TurnRepository $turns,
        private readonly PenaltyRepository $penalties,
        private readonly TurnService $turnService
    ) {}

    public function play(string $gameId, string $userId, string $cardId, ?string $chosenColor): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        if ($game['phase'] !== 'playing') {
            throw new ValidationException('game not in playing phase', 'invalid_phase');
        }
        if ((bool) $game['pending_color_choice']) {
            throw new ValidationException('color choice pending after wild', 'color_choice_pending');
        }

        $settings = $this->settings->find($gameId);

        $card = $this->cards->findById($cardId);
        if ($card === null || $card['game_id'] !== $gameId) {
            throw new NotFoundException('card not found in this game', 'card_not_found');
        }

        $current = $this->players->findById($game['current_turn_player_id']);
        if ($current === null) {
            throw new ValidationException('no current player', 'no_current_player');
        }

        $isCurrentTurn = $current['user_id'] === $userId;
        $isJumpIn = false;

        if (!$isCurrentTurn) {
            // jump-in permis doar daca house rule e activ si cartea e identica cu topul
            if (!(bool) $settings['allow_jump_in']) {
                throw new ForbiddenException('not your turn', 'not_your_turn');
            }
            $top = $this->piles->topDiscard($gameId);
            if ($top === null || $top['type'] !== $card['type'] || $top['color'] !== $card['color'] || $top['value'] !== $card['value']) {
                throw new ForbiddenException('not your turn', 'not_your_turn');
            }
            $caller = $this->players->findByGameAndUser($gameId, $userId);
            if ($caller === null) {
                throw new ForbiddenException('not in this game', 'not_in_game');
            }
            $current = $caller;
            $isJumpIn = true;
        }

        if (!$this->hands->has($current['id'], $card['id'])) {
            throw new ValidationException('card not in your hand', 'card_not_in_hand');
        }

        $top         = $this->piles->topDiscard($gameId);
        $activeColor = $game['active_color'];
        $pendingDraw = (int) $game['pending_draw_count'];

        if ($pendingDraw > 0) {
            // daca stacking nu e permis singura optiune e sa tragi cartile
            if (!(bool) $settings['allow_stacking']) {
                throw new ValidationException('must draw before playing (stacking disabled)', 'must_draw_first');
            }
            $stackTopType = $top['type']; // tipul cartii de pe stack
            $isCompatibleStack = ($stackTopType === 'draw_two' && $card['type'] === 'draw_two')
                || ($stackTopType === 'wild_draw_four' && $card['type'] === 'wild_draw_four');
            if (!$isCompatibleStack) {
                throw new ValidationException('only stack-compatible card may be played', 'invalid_stack');
            }
        } else {
            $matches = $this->cardMatches($card, $top, $activeColor);
            if (!$matches) {
                throw new ValidationException('card does not match top or active color', 'invalid_move');
            }
        }

        // wild-ul poate primi culoarea acum sau o cere printr-un pending_color_choice
        $isWild = in_array($card['type'], ['wild', 'wild_draw_four'], true);
        if ($isWild && $chosenColor !== null && !in_array($chosenColor, ['red','yellow','green','blue'], true)) {
            throw new ValidationException('chosenColor invalid', 'invalid_color');
        }

        $this->pdo->beginTransaction();
        try {
            $this->hands->remove($current['id'], $card['id']);
            $this->piles->pushDiscard($gameId, $card['id']);

            // said_uno se reseteaza daca jucatoru nu mai are exact o carte
            $remaining = PlayerRepository::cardCount($this->pdo, $current['id']);
            if ($remaining !== 1) {
                $this->players->setSaidUno($current['id'], false);
            }

            $direction      = $game['play_direction'];
            $skipNext       = 0;
            $newActiveColor = $activeColor;
            $newPendingDraw = $pendingDraw;
            $newPendingColor = false;

            switch ($card['type']) {
                case 'number':
                    $newActiveColor = $card['color'];
                    break;
                case 'skip':
                    $newActiveColor = $card['color'];
                    $skipNext = 1;
                    break;
                case 'reverse':
                    $newActiveColor = $card['color'];
                    if ($this->players->countInGame($gameId) === 2) {
                        $skipNext = 1;
                    } else {
                        $direction = $this->turnService->reverseDirection($direction);
                    }
                    break;
                case 'draw_two':
                    $newActiveColor = $card['color'];
                    $newPendingDraw = $pendingDraw + 2;
                    break;
                case 'wild':
                    if ($chosenColor !== null) {
                        $newActiveColor = $chosenColor;
                        $newPendingColor = false;
                    } else {
                        $newPendingColor = true;
                        $newActiveColor = null;
                    }
                    break;
                case 'wild_draw_four':
                    if ($chosenColor !== null) {
                        $newActiveColor = $chosenColor;
                        $newPendingColor = false;
                    } else {
                        $newPendingColor = true;
                        $newActiveColor = null;
                    }
                    $newPendingDraw = $pendingDraw + 4;
                    break;
            }

            $turnNumber = $this->turns->nextTurnNumber($gameId, (int) $game['current_round']);
            $this->turns->record($gameId, (int) $game['current_round'], $turnNumber, $current['id'], $isJumpIn ? 'jump_in' : 'play_card', $card['id'], [
                'chosenColor' => $chosenColor,
                'isJumpIn'    => $isJumpIn,
            ]);

            $roundEnded = false;
            if ($remaining === 0) {
                $roundEnded = true;
            }

            $update = [
                'play_direction'        => $direction,
                'active_color'          => $newActiveColor,
                'pending_draw_count'    => $newPendingDraw,
                'pending_color_choice'  => $newPendingColor ? 1 : 0,
                'has_acted_this_turn'   => 1,
            ];

            if (!$roundEnded && !$newPendingColor) {
                // avansam randul doar daca runda nu s-a terminat si nu asteptam culoare
                $nextPlayer = $this->turnService->nextPlayerId($gameId, $current['id'], $direction, $skipNext);
                $update['current_turn_player_id'] = $nextPlayer;
                $update['has_acted_this_turn']    = 0;
            }

            $this->games->update($gameId, $update);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'game'            => $this->games->findById($gameId),
            'card'            => $card,
            'roundEnded'      => $roundEnded,
            'currentPlayer'   => $current,
            'cardsInHand'     => $remaining,
        ];
    }

    private function cardMatches(array $card, ?array $top, ?string $activeColor): bool
    {
        if (in_array($card['type'], ['wild', 'wild_draw_four'], true)) {
            return true;
        }
        if ($top === null) return true;
        if ($card['color'] !== null && $card['color'] === $activeColor) return true;
        if ($top['type'] === 'number' && $card['type'] === 'number' && $top['value'] === $card['value']) return true;
        if ($top['type'] !== 'number' && $top['type'] === $card['type']) return true;
        return false;
    }
}
