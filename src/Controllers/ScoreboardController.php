<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\RoundRepository;

final class ScoreboardController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly RoundRepository $rounds
    ) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $players  = $this->players->listForGame($args['gameId']);
        $allRounds = $this->rounds->listForGame($args['gameId']);
        $allScores = $this->rounds->listScoresForGame($args['gameId']);

        $byPlayer = [];
        foreach ($players as $p) {
            $byPlayer[$p['id']] = [
                'playerId'    => $p['id'],
                'displayName' => $p['display_name'],
                'totalScore'  => (int) $p['total_score'],
                'roundScores' => array_fill(0, count($allRounds), 0),
                'team'        => $p['team'],
            ];
        }
        $roundIndex = [];
        foreach ($allRounds as $i => $r) {
            $roundIndex[(int) $r['round_number']] = $i;
        }
        foreach ($allScores as $row) {
            if (isset($byPlayer[$row['player_id']]) && isset($roundIndex[(int) $row['round_number']])) {
                $byPlayer[$row['player_id']]['roundScores'][$roundIndex[(int) $row['round_number']]] = (int) $row['score'];
            }
        }

        return UserController::json($res, 200, [
            'players' => array_values($byPlayer),
        ]);
    }
}
