<?php
declare(strict_types=1);

namespace Uno\Bootstrap;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Uno\Controllers\ActionController;
use Uno\Controllers\AuthController;
use Uno\Controllers\GameController;
use Uno\Controllers\HandController;
use Uno\Controllers\LeaderboardController;
use Uno\Controllers\PenaltyController;
use Uno\Controllers\PileController;
use Uno\Controllers\PlayerController;
use Uno\Controllers\RoundController;
use Uno\Controllers\ScoreboardController;
use Uno\Controllers\TurnController;
use Uno\Controllers\UserController;
use Uno\Controllers\ViewController;
use Uno\Middleware\JwtMiddleware;

final class RoutesFactory
{
    public static function register(App $app): void
    {
        $app->get('/health', function (ServerRequestInterface $req, ResponseInterface $res): ResponseInterface {
            $pdo = $this->get(PDO::class);
            $ok = (bool) $pdo->query('SELECT 1')->fetchColumn();
            $res->getBody()->write(json_encode([
                'status' => $ok ? 'ok' : 'fail',
                'db'     => $ok,
                'time'   => date(DATE_ATOM),
            ]));
            return $res->withHeader('Content-Type', 'application/json');
        });

        $app->post('/auth/login', [AuthController::class, 'login']);
        $app->get('/leaderboard', [LeaderboardController::class, 'index']);

        // rute twig pentru UI
        $app->get('/games-view',                          [ViewController::class, 'gamesList']);
        $app->post('/games-view/create',                  [ViewController::class, 'gameCreate']);
        $app->get('/games-view/{gameId}',                 [ViewController::class, 'gameDetails']);
        $app->post('/games-view/{gameId}/join',           [ViewController::class, 'gameJoin']);
        $app->post('/games-view/{gameId}/start',          [ViewController::class, 'gameStart']);
        $app->get('/leaderboard-view',                    [ViewController::class, 'leaderboard']);

        // rute protejate cu JWT
        $app->group('', function (RouteCollectorProxy $g): void {
            $g->get('/me', function (ServerRequestInterface $req, ResponseInterface $res): ResponseInterface {
                $payload = [
                    'userId' => $req->getAttribute('userId'),
                    'email'  => $req->getAttribute('email'),
                ];
                $res->getBody()->write(json_encode($payload));
                return $res->withHeader('Content-Type', 'application/json');
            });

            $g->get('/users/{userId}',       [UserController::class, 'show']);
            $g->get('/users/{userId}/stats', [UserController::class, 'stats']);

            $g->get('/games',                       [GameController::class, 'index']);
            $g->post('/games',                      [GameController::class, 'create']);
            $g->get('/games/{gameId}',              [GameController::class, 'show']);
            $g->get('/games/{gameId}/settings',     [GameController::class, 'settings']);

            $g->get('/games/{gameId}/players',                  [PlayerController::class, 'index']);
            $g->post('/games/{gameId}/players',                 [PlayerController::class, 'join']);
            $g->get('/games/{gameId}/players/{playerId}',       [PlayerController::class, 'show']);

            $g->get('/games/{gameId}/players/{playerId}/hand',  [HandController::class, 'show']);
            $g->get('/games/{gameId}/discard-pile',             [PileController::class, 'discard']);
            $g->get('/games/{gameId}/draw-pile',                [PileController::class, 'draw']);

            $g->get('/games/{gameId}/turns/current', [TurnController::class, 'current']);
            $g->get('/games/{gameId}/turns',         [TurnController::class, 'index']);

            $g->get('/games/{gameId}/rounds',                       [RoundController::class, 'index']);
            $g->get('/games/{gameId}/rounds/{roundNumber}',         [RoundController::class, 'show']);
            $g->get('/games/{gameId}/scoreboard',                   [ScoreboardController::class, 'index']);
            $g->get('/games/{gameId}/penalties',                    [PenaltyController::class, 'index']);

            $g->post('/games/{gameId}/actions/start',           [ActionController::class, 'start']);
            $g->post('/games/{gameId}/actions/play-card',       [ActionController::class, 'playCard']);
            $g->post('/games/{gameId}/actions/draw-card',       [ActionController::class, 'drawCard']);
            $g->post('/games/{gameId}/actions/say-uno',         [ActionController::class, 'sayUno']);
            $g->post('/games/{gameId}/actions/choose-color',    [ActionController::class, 'chooseColor']);
            $g->post('/games/{gameId}/actions/end-turn',        [ActionController::class, 'endTurn']);
        })->add(JwtMiddleware::class);
    }
}
