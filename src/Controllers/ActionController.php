<?php
declare(strict_types=1);

namespace Uno\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\ConflictException;
use Uno\Database\Exceptions\ForbiddenException;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\HandRepository;
use Uno\Repositories\PenaltyRepository;
use Uno\Repositories\PileRepository;
use Uno\Repositories\PlayerStatsRepository;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\RoundRepository;
use Uno\Repositories\TurnRepository;
use Uno\Services\DeckService;
use Uno\Services\GameStartService;
use Uno\Services\PlayCardService;
use Uno\Services\ScoringService;
use Uno\Services\TurnService;

final class ActionController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly RoundRepository $rounds,
        private readonly TurnRepository $turns,
        private readonly DeckService $deck,
        private readonly TurnService $turnService,
        private readonly PlayCardService $playCard,
        private readonly ScoringService $scoring,
        private readonly HandRepository $hands,
        private readonly PileRepository $piles,
        private readonly PenaltyRepository $penaltiesRepo,
        private readonly PlayerStatsRepository $stats,
        private readonly GameStartService $startService
    ) {}

    public function drawCard(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $gameId = $args['gameId'];
        $userId = (string) $req->getAttribute('userId');

        $game = $this->games->findById($gameId);
        if ($game === null) throw new NotFoundException('game not found', 'game_not_found');
        if ($game['phase'] !== 'playing') throw new ValidationException('game not in playing phase', 'invalid_phase');

        $current = $this->players->findById($game['current_turn_player_id']);
        if ($current === null || $current['user_id'] !== $userId) {
            throw new ForbiddenException('not your turn', 'not_your_turn');
        }

        $count = max(1, (int) $game['pending_draw_count']);
        $drawn = [];
        for ($i = 0; $i < $count; $i++) {
            $card = $this->deck->drawOne($gameId);
            if ($card === null) break;
            $this->hands->add($current['id'], $card['id']);
            $drawn[] = $card;
        }

        $turnNumber = $this->turns->nextTurnNumber($gameId, (int) $game['current_round']);
        $this->turns->record($gameId, (int) $game['current_round'], $turnNumber, $current['id'], 'draw_card', null, [
            'count' => count($drawn),
        ]);

        // la pending draw turnu trece automat dupa ce tragi cartile
        $update = ['has_acted_this_turn' => 1, 'pending_draw_count' => 0];
        if ((int) $game['pending_draw_count'] > 0) {
            $next = $this->turnService->nextPlayerId($gameId, $current['id'], $game['play_direction']);
            $update['current_turn_player_id'] = $next;
            $update['has_acted_this_turn']    = 0;
        }
        $this->games->update($gameId, $update);

        $latest = $this->games->findById($gameId);
        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'draw_card',
            'nextTurnPlayerId'  => $latest['current_turn_player_id'],
            'penalties'         => [],
            'cardsDrawn'        => count($drawn),
        ]);
    }

    public function sayUno(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $game = $this->games->findById($args['gameId']);
        if ($game === null) throw new NotFoundException('game not found', 'game_not_found');

        $player = $this->players->findByGameAndUser($args['gameId'], $userId);
        if ($player === null) throw new ForbiddenException('not in this game', 'not_in_game');

        $count = \Uno\Repositories\PlayerRepository::cardCount($this->pdo, $player['id']);
        if ($count !== 1) {
            throw new ValidationException('UNO can only be declared with exactly one card in hand', 'invalid_uno');
        }
        $this->players->setSaidUno($player['id'], true);
        $this->stats->bumpUnoCall($userId);

        $turnNumber = $this->turns->nextTurnNumber($args['gameId'], (int) $game['current_round']);
        $this->turns->record($args['gameId'], (int) $game['current_round'], $turnNumber, $player['id'], 'say_uno');

        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'say_uno',
            'nextTurnPlayerId'  => $game['current_turn_player_id'],
            'penalties'         => [],
        ]);
    }

    public function chooseColor(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $body   = (array) $req->getParsedBody();
        $color  = (string) ($body['color'] ?? '');

        if (!in_array($color, ['red', 'yellow', 'green', 'blue'], true)) {
            throw new ValidationException('color is invalid', 'invalid_color');
        }

        $game = $this->games->findById($args['gameId']);
        if ($game === null) throw new NotFoundException('game not found', 'game_not_found');
        if (!(bool) $game['pending_color_choice']) {
            throw new ValidationException('no color choice pending', 'no_color_pending');
        }

        $current = $this->players->findById($game['current_turn_player_id']);
        if ($current === null || $current['user_id'] !== $userId) {
            throw new ForbiddenException('only current player can choose color', 'not_your_turn');
        }

        $next = $this->turnService->nextPlayerId(
            $args['gameId'],
            $current['id'],
            $game['play_direction']
        );

        $this->games->update($args['gameId'], [
            'active_color'           => $color,
            'pending_color_choice'   => 0,
            'current_turn_player_id' => $next,
            'has_acted_this_turn'    => 0,
        ]);

        $turnNumber = $this->turns->nextTurnNumber($args['gameId'], (int) $game['current_round']);
        $this->turns->record($args['gameId'], (int) $game['current_round'], $turnNumber, $current['id'], 'choose_color', null, [
            'color' => $color,
        ]);

        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'choose_color',
            'nextTurnPlayerId'  => $next,
            'penalties'         => [],
        ]);
    }

    public function endTurn(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $game = $this->games->findById($args['gameId']);
        if ($game === null) throw new NotFoundException('game not found', 'game_not_found');

        $current = $this->players->findById($game['current_turn_player_id']);
        if ($current === null || $current['user_id'] !== $userId) {
            throw new ForbiddenException('not your turn', 'not_your_turn');
        }

        if (!(bool) $game['has_acted_this_turn']) {
            throw new ConflictException('must draw or play first', 'must_act_first');
        }

        $next = $this->turnService->nextPlayerId(
            $args['gameId'], $current['id'], $game['play_direction']
        );

        $this->games->update($args['gameId'], [
            'current_turn_player_id' => $next,
            'has_acted_this_turn'    => 0,
        ]);

        $turnNumber = $this->turns->nextTurnNumber($args['gameId'], (int) $game['current_round']);
        $this->turns->record($args['gameId'], (int) $game['current_round'], $turnNumber, $current['id'], 'end_turn');

        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'end_turn',
            'nextTurnPlayerId'  => $next,
            'penalties'         => [],
        ]);
    }

    public function playCard(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $body  = (array) $req->getParsedBody();
        $cardId = (string) ($body['cardId'] ?? '');
        if ($cardId === '') {
            throw new ValidationException('cardId required', 'missing_card_id');
        }
        $userId = (string) $req->getAttribute('userId');
        $chosenColor = isset($body['chosenColor']) && $body['chosenColor'] !== '' ? (string) $body['chosenColor'] : null;

        $result = $this->playCard->play($args['gameId'], $userId, $cardId, $chosenColor);

        $penalties = [];
        if ($result['roundEnded']) {
            $penalties = $this->scoring->finishRoundFromHands($args['gameId'], $result['currentPlayer']['id']);
        }

        $game = $this->games->findById($args['gameId']);
        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'play_card',
            'nextTurnPlayerId'  => $game['current_turn_player_id'],
            'penalties'         => $penalties,
        ]);
    }

    public function start(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $info = $this->startService->start($args['gameId'], $userId);

        return UserController::json($res, 200, [
            'success'           => true,
            'action'            => 'start',
            'nextTurnPlayerId'  => $info['currentTurnPlayerId'],
            'penalties'         => [],
        ]);
    }
}
