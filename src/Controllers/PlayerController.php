<?php
declare(strict_types=1);

namespace Uno\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Models\GameSerializer;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Services\PlayerService;

final class PlayerController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PlayerService $service,
        private readonly GameRepository $games,
        private readonly PlayerRepository $players
    ) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $rows = $this->players->listForGame($args['gameId']);
        $out  = [];
        foreach ($rows as $row) {
            $out[] = GameSerializer::playerToApi($row, PlayerRepository::cardCount($this->pdo, $row['id']));
        }
        return UserController::json($res, 200, $out);
    }

    public function join(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $player = $this->service->join($args['gameId'], $userId);
        return UserController::json($res, 201, GameSerializer::playerToApi($player, 0));
    }

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $player = $this->players->findInGame($args['gameId'], $args['playerId']);
        if ($player === null) {
            throw new NotFoundException('player not found in game', 'player_not_found');
        }
        $cardCount = PlayerRepository::cardCount($this->pdo, $player['id']);
        return UserController::json($res, 200, GameSerializer::playerToApi($player, $cardCount));
    }
}
