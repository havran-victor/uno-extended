<?php
declare(strict_types=1);

namespace Uno\Services;

use Ramsey\Uuid\Uuid;
use Uno\Database\Exceptions\ConflictException;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\GameRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Repositories\UserRepository;

final class PlayerService
{
    public function __construct(
        private readonly GameRepository $games,
        private readonly PlayerRepository $players,
        private readonly UserRepository $users
    ) {}

    public function join(string $gameId, string $userId): array
    {
        $game = $this->games->findById($gameId);
        if ($game === null) {
            throw new NotFoundException('game not found', 'game_not_found');
        }
        if ($game['phase'] !== 'lobby') {
            throw new ValidationException('game already started or finished', 'game_not_in_lobby');
        }

        if ($this->players->findByGameAndUser($gameId, $userId) !== null) {
            throw new ConflictException('user already in this game', 'already_joined');
        }

        if ($this->players->countInGame($gameId) >= (int) $game['max_players']) {
            throw new ValidationException('game is full', 'game_full');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new NotFoundException('user not found', 'user_not_found');
        }

        $playerId = Uuid::uuid4()->toString();
        $this->players->insert([
            'id'           => $playerId,
            'game_id'      => $gameId,
            'user_id'      => $userId,
            'display_name' => $user['display_name'],
            'position'     => $this->players->nextPosition($gameId),
        ]);

        return $this->players->findById($playerId);
    }
}
