<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Uno\Bootstrap\ContainerFactory;
use Uno\Bootstrap\RoutesFactory;
use Uno\Middleware\ErrorMiddleware;

$rootDir = dirname(__DIR__);
require $rootDir . '/vendor/autoload.php';

if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

$container = ContainerFactory::build($rootDir);
AppFactory::setContainer($container);
$app = AppFactory::create();

// Detect base path automatically (works under /uno_extended/public on Apache)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($basePath !== '' && $basePath !== '.') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->add(new ErrorMiddleware($container->get('config.debug')));
$app->addRoutingMiddleware();

RoutesFactory::register($app);

$app->run();
