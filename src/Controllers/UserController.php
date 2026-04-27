<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\NotFoundException;
use Uno\Repositories\PlayerStatsRepository;
use Uno\Repositories\UserRepository;

final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PlayerStatsRepository $stats
    ) {}

    public function show(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $user = $this->users->findById($args['userId']);
        if ($user === null) {
            throw new NotFoundException('user not found', 'user_not_found');
        }

        $payload = [
            'id'          => $user['id'],
            'email'       => $user['email'],
            'displayName' => $user['display_name'],
            'avatarUrl'   => $user['avatar_url'],
            'createdAt'   => self::iso($user['created_at']),
        ];

        return self::json($res, 200, $payload);
    }

    public function stats(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        if ($this->users->findById($args['userId']) === null) {
            throw new NotFoundException('user not found', 'user_not_found');
        }

        $row = $this->stats->findByUserId($args['userId']) ?? [
            'user_id'         => $args['userId'],
            'games_played'    => 0,
            'games_won'       => 0,
            'total_points'    => 0,
            'cards_played'    => 0,
            'uno_calls_made'  => 0,
            'challenges_won'  => 0,
            'challenges_lost' => 0,
        ];

        $played = (int) $row['games_played'];
        $won    = (int) $row['games_won'];

        $payload = [
            'userId'         => $row['user_id'],
            'gamesPlayed'    => $played,
            'gamesWon'       => $won,
            'winRate'        => $played > 0 ? round($won / $played, 4) : 0.0,
            'totalPoints'    => (int) $row['total_points'],
            'cardsPlayed'    => (int) $row['cards_played'],
            'unoCallsMade'   => (int) $row['uno_calls_made'],
            'challengesWon'  => (int) $row['challenges_won'],
            'challengesLost' => (int) $row['challenges_lost'],
        ];

        return self::json($res, 200, $payload);
    }

    public static function json(ResponseInterface $res, int $status, mixed $payload): ResponseInterface
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function iso(string $dt): string
    {
        return date(DATE_ATOM, strtotime($dt));
    }
}
