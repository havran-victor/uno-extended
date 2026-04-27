<?php
declare(strict_types=1);

namespace Uno\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Uno\Database\Exceptions\ValidationException;
use Uno\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $body  = (array) $req->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $pass  = (string) ($body['password'] ?? '');

        if ($email === '' || $pass === '') {
            throw new ValidationException('email and password required', 'missing_fields');
        }

        $result = $this->auth->login($email, $pass);
        $res->getBody()->write(json_encode($result));
        return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
