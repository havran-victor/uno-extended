<?php
declare(strict_types=1);

namespace Uno\Middleware;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;
use Uno\Services\AuthService;

final class JwtMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $this->unauthorized('missing_token', 'Authorization Bearer header required');
        }

        try {
            $claims = $this->auth->decode($m[1]);
        } catch (ExpiredException $e) {
            return $this->unauthorized('token_expired', 'token has expired');
        } catch (SignatureInvalidException $e) {
            return $this->unauthorized('invalid_token', 'token signature invalid');
        } catch (Throwable $e) {
            return $this->unauthorized('invalid_token', 'token could not be decoded');
        }

        $request = $request
            ->withAttribute('userId', $claims['userId'])
            ->withAttribute('email',  $claims['email']);

        return $handler->handle($request);
    }

    private function unauthorized(string $code, string $message): ResponseInterface
    {
        $res = (new ResponseFactory())->createResponse(401);
        $res->getBody()->write(json_encode(['code' => $code, 'message' => $message]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
