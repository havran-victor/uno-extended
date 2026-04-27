<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\ForbiddenException;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Models\GameSerializer;
use Uno\Repositories\GameRepository;
use Uno\Repositories\HandRepository;
use Uno\Repositories\PlayerRepository;

final class HandController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly HandRepository $hands
    ) {}

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $player = $this->players->findInGame($args['gameId'], $args['playerId']);
        if ($player === null) {
            throw new NotFoundException('player not found in game', 'player_not_found');
        }

        $userId = (string) $req->getAttribute('userId');
        if ($player['user_id'] !== $userId) {
            throw new ForbiddenException('cannot view another player\'s hand', 'not_own_hand');
        }

        $cards = $this->hands->listForPlayer($player['id']);
        $payload = [
            'cards' => array_map(fn($c) => GameSerializer::cardToApi($c), $cards),
            'count' => count($cards),
        ];
        return UserController::json($res, 200, $payload);
    }
}
