<?php
declare(strict_types=1);

namespace Uno\Services;

use Ramsey\Uuid\Uuid;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\GameSettingsRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\UserRepository;

final class GameService
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly GameSettingsRepository $settings,
        private readonly PlayerRepository $players,
        private readonly UserRepository $users
    ) {}

    public function create(string $hostUserId, array $body): array
    {
        $name        = trim((string) ($body['name'] ?? ''));
        $maxPlayers  = (int) ($body['maxPlayers']  ?? 4);
        $pointsToWin = (int) ($body['pointsToWin'] ?? 500);

        if ($name === '') {
            throw new ValidationException('name is required', 'invalid_name');
        }
        if ($maxPlayers < 2 || $maxPlayers > 10) {
            throw new ValidationException('maxPlayers must be between 2 and 10', 'invalid_max_players');
        }
        if ($pointsToWin < 1) {
            throw new ValidationException('pointsToWin must be positive', 'invalid_points_to_win');
        }

        $host = $this->users->findById($hostUserId);
        if ($host === null) {
            throw new NotFoundException('host user not found', 'user_not_found');
        }

        $gameId = Uuid::uuid4()->toString();
        $this->games->insert([
            'id'            => $gameId,
            'name'          => $name,
            'max_players'   => $maxPlayers,
            'points_to_win' => $pointsToWin,
        ]);
        $this->settings->insert($gameId, (array) ($body['settings'] ?? []));

        $playerId = Uuid::uuid4()->toString();
        $this->players->insert([
            'id'           => $playerId,
            'game_id'      => $gameId,
            'user_id'      => $hostUserId,
            'display_name' => $host['display_name'],
            'position'     => 0,
        ]);
        $this->games->setHost($gameId, $playerId);

        return $this->loadGame($gameId);
    }

    public function loadGame(string $gameId): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        $settings = $this->settings->find($gameId);
        return ['game' => $game, 'settings' => $settings];
    }

    public function findOrFail(string $gameId): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        return $game;
    }
}
