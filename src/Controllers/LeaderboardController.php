<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Repositories\LeaderboardRepository;

final class LeaderboardController
{
    private const ALLOWED_SORT = ['wins', 'winRate', 'totalPoints'];

    public function __construct(private readonly LeaderboardRepository $repo) {}

    public function index(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $q         = $req->getQueryParams();
        $requested = (string) ($q['sortBy'] ?? 'wins');
        $sortBy    = in_array($requested, self::ALLOWED_SORT, true) ? $requested : 'wins';
        $page      = max(1, (int) ($q['page']     ?? 1));
        $pageSize  = min(100, max(1, (int) ($q['pageSize'] ?? 20)));

        $rows = $this->repo->fetchPage($sortBy, $page, $pageSize);
        $startRank = ($page - 1) * $pageSize + 1;

        $entries = [];
        foreach ($rows as $i => $row) {
            $entries[] = [
                'userId'      => $row['user_id'],
                'displayName' => $row['display_name'],
                'gamesWon'    => (int) $row['games_won'],
                'winRate'     => round((float) $row['win_rate'], 4),
                'totalPoints' => (int) $row['total_points'],
                'rank'        => $startRank + $i,
            ];
        }

        return UserController::json($res, 200, [
            'entries'    => $entries,
            'totalCount' => $this->repo->totalCount(),
            'page'       => $page,
            'pageSize'   => $pageSize,
        ]);
    }
}
