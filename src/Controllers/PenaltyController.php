<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PenaltyRepository;

final class PenaltyController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PenaltyRepository $repo
    ) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $q = $req->getQueryParams();
        $rows = $this->repo->listForGame(
            $args['gameId'],
            isset($q['playerId']) && $q['playerId'] !== '' ? (string) $q['playerId'] : null,
            isset($q['type'])     && $q['type']     !== '' ? (string) $q['type']     : null,
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'              => $r['id'],
                'playerId'        => $r['player_id'],
                'type'            => $r['type'],
                'cardsPenalized'  => (int) $r['cards_penalized'],
                'roundNumber'     => $r['round_number'] !== null ? (int) $r['round_number'] : null,
                'timestamp'       => date(DATE_ATOM, strtotime($r['timestamp'])),
            ];
        }
        return UserController::json($res, 200, $out);
    }
}
