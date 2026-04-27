<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\RoundRepository;

final class RoundController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly RoundRepository $rounds
    ) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $rows = $this->rounds->listForGame($args['gameId']);
        $out = array_map(fn($r) => $this->serialize($args['gameId'], $r), $rows);
        return UserController::json($res, 200, $out);
    }

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $round = $this->rounds->find($args['gameId'], (int) $args['roundNumber']);
        if ($round === null) {
            throw new NotFoundException('round not found', 'round_not_found');
        }
        return UserController::json($res, 200, $this->serialize($args['gameId'], $round));
    }

    private function serialize(string $gameId, array $round): array
    {
        $scoresRows = $this->rounds->listScoresFor($gameId, (int) $round['round_number']);
        $scores = [];
        foreach ($scoresRows as $r) {
            $scores[$r['player_id']] = (int) $r['score'];
        }
        return [
            'roundNumber' => (int) $round['round_number'],
            'winnerId'    => $round['winner_id'],
            'scores'      => $scores,
            'startedAt'   => date(DATE_ATOM, strtotime($round['started_at'])),
            'endedAt'     => $round['ended_at'] !== null ? date(DATE_ATOM, strtotime($round['ended_at'])) : null,
        ];
    }
}
