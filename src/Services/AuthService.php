<?php
declare(strict_types=1);

namespace Uno\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Uno\Database\Exceptions\ValidationException;
use Uno\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly array $config // ['secret' => string, 'ttl_minutes' => int]
    ) {}

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new ValidationException('invalid email or password', 'invalid_credentials');
        }

        $now = time();
        $payload = [
            'iss'    => 'uno-extended',
            'iat'    => $now,
            'exp'    => $now + ($this->config['ttl_minutes'] * 60),
            'sub'    => $user['id'],
            'email'  => $user['email'],
        ];

        $token = JWT::encode($payload, $this->config['secret'], 'HS256');

        return [
            'token'      => $token,
            'expiresAt'  => date(DATE_ATOM, $payload['exp']),
            'user'       => [
                'id'          => $user['id'],
                'email'       => $user['email'],
                'displayName' => $user['display_name'],
            ],
        ];
    }

    /** @return array{userId:string,email:string} */
    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->config['secret'], 'HS256'));
        return [
            'userId' => $decoded->sub,
            'email'  => $decoded->email,
        ];
    }
}
