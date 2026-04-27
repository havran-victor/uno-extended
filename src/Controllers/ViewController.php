<?php
declare(strict_types=1);

namespace Uno\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Uno\Models\GameSerializer;
use Uno\Repositories\GameRepository;
use Uno\Repositories\GameSettingsRepository;
use Uno\Repositories\LeaderboardRepository;
use Uno\Repositories\PlayerRepository;
use Uno\Services\GameService;
use Uno\Services\GameStartService;
use Uno\Services\PlayerService;

final class ViewController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly PDO $pdo,
        private readonly GameRepository $games,
        private readonly GameSettingsRepository $settings,
        private readonly PlayerRepository $players,
        private readonly LeaderboardRepository $leaderboard,
        private readonly GameService $gameService,
        private readonly PlayerService $playerService,
        private readonly GameStartService $startService
    ) {}

    public function gamesList(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $rows  = $this->games->search(null, null);
        $games = array_map(fn($r) => GameSerializer::toApi($r), $rows);

        $defaultUserId = $this->pdo->query("SELECT id FROM users ORDER BY created_at ASC LIMIT 1")->fetchColumn() ?: '';

        return $this->twig->render($res, 'games/list.twig', [
            'games'         => $games,
            'basePath'      => $this->basePath($req),
            'defaultUserId' => $defaultUserId,
        ]);
    }

    public function gameDetails(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $bundle = $this->gameService->loadGame($args['gameId']);
        $game   = GameSerializer::toApi($bundle['game'], $bundle['settings']);

        $rows = $this->players->listForGame($args['gameId']);
        $playersOut = [];
        foreach ($rows as $p) {
            $playersOut[] = GameSerializer::playerToApi($p, PlayerRepository::cardCount($this->pdo, $p['id']));
        }

        return $this->twig->render($res, 'games/details.twig', [
            'game'     => $game,
            'players'  => $playersOut,
            'basePath' => $this->basePath($req),
        ]);
    }

    public function gameCreate(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $body = (array) $req->getParsedBody();
        $hostUserId = (string) ($body['hostUserId'] ?? '');

        $settings = [];
        foreach (['allowStacking', 'sevenZeroRule', 'allowJumpIn'] as $k) {
            if (isset($body[$k])) $settings[$k] = true;
        }

        $bundle = $this->gameService->create($hostUserId, [
            'name'        => $body['name']        ?? '',
            'maxPlayers'  => (int) ($body['maxPlayers']  ?? 4),
            'pointsToWin' => (int) ($body['pointsToWin'] ?? 500),
            'settings'    => $settings,
        ]);

        return $res
            ->withHeader('Location', $this->basePath($req) . '/games-view/' . $bundle['game']['id'])
            ->withStatus(303);
    }

    public function gameJoin(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $body = (array) $req->getParsedBody();
        $this->playerService->join($args['gameId'], (string) ($body['userId'] ?? ''));
        return $res
            ->withHeader('Location', $this->basePath($req) . '/games-view/' . $args['gameId'])
            ->withStatus(303);
    }

    public function gameStart(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $body   = (array) $req->getParsedBody();
        $userId = (string) ($body['userId'] ?? '');
        $this->startService->start($args['gameId'], $userId);
        return $res
            ->withHeader('Location', $this->basePath($req) . '/games-view/' . $args['gameId'])
            ->withStatus(303);
    }

    public function leaderboard(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $rows = $this->leaderboard->fetchPage('wins', 1, 50);
        $entries = [];
        foreach ($rows as $i => $r) {
            $entries[] = [
                'rank'        => $i + 1,
                'displayName' => $r['display_name'],
                'gamesWon'    => (int) $r['games_won'],
                'winRate'     => (float) $r['win_rate'],
                'totalPoints' => (int) $r['total_points'],
            ];
        }

        return $this->twig->render($res, 'leaderboard/index.twig', [
            'entries'    => $entries,
            'totalCount' => $this->leaderboard->totalCount(),
            'basePath'   => $this->basePath($req),
        ]);
    }

    private function basePath(ServerRequestInterface $req): string
    {
        return RouteContext::fromRequest($req)->getBasePath();
    }
}
