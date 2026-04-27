<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Models\GameSerializer;
use Uno\Repositories\GameRepository;
use Uno\Repositories\GameSettingsRepository;
use Uno\Services\GameService;

final class GameController
{
    public function __construct(
        private readonly GameService $service,
        private readonly GameRepository $games,
        private readonly GameSettingsRepository $settings
    ) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $q          = $req->getQueryParams();
        $status     = isset($q['status']) ? (string) $q['status'] : null;
        $maxPlayers = isset($q['maxPlayers']) && $q['maxPlayers'] !== '' ? (int) $q['maxPlayers'] : null;

        $rows = $this->games->search($status, $maxPlayers);
        $out  = [];
        foreach ($rows as $row) {
            $settings = $this->settings->find($row['id']);
            $out[]    = GameSerializer::toApi($row, $settings);
        }
        return UserController::json($res, 200, $out);
    }

    public function create(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $userId = (string) $req->getAttribute('userId');
        $body   = (array) $req->getParsedBody();
        $bundle = $this->service->create($userId, $body);
        return UserController::json($res, 201, GameSerializer::toApi($bundle['game'], $bundle['settings']));
    }

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $bundle = $this->service->loadGame($args['gameId']);
        return UserController::json($res, 200, GameSerializer::toApi($bundle['game'], $bundle['settings']));
    }

    public function settings(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->games->findById($args['gameId']) === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $row = $this->settings->find($args['gameId']);
        if ($row === null) {
            throw new NotFoundException('settings not found', 'settings_not_found');
        }
        return UserController::json($res, 200, GameSettingsRepository::toApi($row));
    }
}
