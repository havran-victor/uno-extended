<?php
declare(strict_types=1);

namespace Uno\Bootstrap;

use DI\ContainerBuilder;
use PDO;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Uno\Database\PdoFactory;
use Uno\Middleware\JwtMiddleware;
use Uno\Repositories\UserRepository;
use Uno\Services\AuthService;

final class ContainerFactory
{
    public static function build(string $rootDir): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([
            'config.db' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'name' => $_ENV['DB_NAME'] ?? 'uno_extended',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'pass' => $_ENV['DB_PASS'] ?? '',
            ],
            'config.jwt' => [
                'secret'      => $_ENV['JWT_SECRET'] ?? 'change-me',
                'ttl_minutes' => (int) ($_ENV['JWT_TTL_MINUTES'] ?? 120),
            ],
            'config.debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),

            PDO::class => function (ContainerInterface $c): PDO {
                return PdoFactory::create($c->get('config.db'));
            },

            Twig::class => function () use ($rootDir): Twig {
                return Twig::create($rootDir . '/views', [
                    'cache' => false,
                    'debug' => true,
                ]);
            },

            AuthService::class => function (ContainerInterface $c): AuthService {
                return new AuthService($c->get(UserRepository::class), $c->get('config.jwt'));
            },

            JwtMiddleware::class => function (ContainerInterface $c): JwtMiddleware {
                return new JwtMiddleware($c->get(AuthService::class));
            },
        ]);

        return $builder->build();
    }
}
