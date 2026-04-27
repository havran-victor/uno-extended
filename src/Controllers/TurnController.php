<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Models\GameSerializer;
use Uno\Repositories\CardRepository;
use Uno\Repositories\GameRepository;
use Uno\Repositories\TurnRepository;

final class TurnController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly TurnRepository $turns,
        private readonly CardRepository $cards
    ) {}

    public function current(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $game = $this->games->findById($args['gameId']);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $payload = [
            'currentPlayerId'   => $game['current_turn_player_id'],
            'playDirection'    => $game['play_direction'],
            'turnNumber'       => $this->turns->nextTurnNumber($args['gameId'], (int) $game['current_round']) - 1,
            'roundNumber'      => (int) $game['current_round'],
            'pendingDrawCount' => (int) $game['pending_draw_count'],
        ];
        return UserController::json($res, 200, $payload);
    }

    public function index(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $q = $req->getQueryParams();
        $rows = $this->turns->findFiltered(
            $args['gameId'],
            isset($q['roundNumber']) && $q['roundNumber'] !== '' ? (int) $q['roundNumber'] : null,
            isset($q['playerId'])    && $q['playerId']    !== '' ? (string) $q['playerId'] : null,
            isset($q['action'])      && $q['action']      !== '' ? (string) $q['action']   : null,
        );

        $out = [];
        foreach ($rows as $row) {
            $card = null;
            if ($row['card_id'] !== null) {
                $c = $this->cards->findById($row['card_id']);
                if ($c !== null) $card = GameSerializer::cardToApi($c);
            }
            $out[] = [
                'id'           => $row['id'],
                'roundNumber'  => (int) $row['round_number'],
                'turnNumber'   => (int) $row['turn_number'],
                'playerId'     => $row['player_id'],
                'action'       => $row['action'],
                'card'         => $card,
                'details'      => $row['details'] !== null ? json_decode($row['details'], true) : null,
                'timestamp'    => date(DATE_ATOM, strtotime($row['timestamp'])),
            ];
        }
        return UserController::json($res, 200, $out);
    }
}
