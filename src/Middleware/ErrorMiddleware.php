<?php
declare(strict_types=1);

namespace Uno\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;
use Uno\Database\Exceptions\HttpException;

final class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly bool $debug = false) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            return $this->json($e->status, ['code' => $e->errorCode, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $payload = ['code' => 'internal_error', 'message' => 'unexpected server error'];
            if ($this->debug) {
                $payload['debug'] = [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                ];
            }
            return $this->json(500, $payload);
        }
    }

    private function json(int $status, array $payload): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
