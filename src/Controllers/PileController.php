<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Models\GameSerializer;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PileRepository;

final class PileController
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PileRepository $piles
    ) {}

    public function discard(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $game = $this->games->findById($args['gameId']);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $top = $this->piles->topDiscard($args['gameId']);
        $payload = [
            'topCard'     => $top !== null ? GameSerializer::cardToApi($top) : null,
            'activeColor' => $game['active_color'],
            'cardCount'   => $this->piles->discardCount($args['gameId']),
        ];
        return UserController::json($res, 200, $payload);
    }

    public function draw(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        return UserController::json($res, 200, [
            'cardCount' => $this->piles->drawCount($args['gameId']),
        ]);
    }
}
