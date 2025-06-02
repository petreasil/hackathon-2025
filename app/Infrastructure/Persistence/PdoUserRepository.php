<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;

class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * @throws Exception
     */
    public function find(mixed $id): ?User
    {
        $query = 'SELECT * FROM users WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at']),
        );
    }

    public function findByUsername(string $username): ?User
    {
        $query = 'SELECT * FROM users WHERE username = :username';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['username' => $username]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return new User(
            $data['id'],
            $data['username'],
            $data['password_hash'],
            new DateTimeImmutable($data['created_at']),
        );
        
    }

    public function save(User $user): void
    {
        if ($user->getId() === null) {
            // Insert new user
            $query = 'INSERT INTO users (username, password_hash, created_at) VALUES (:username, :password_hash, :created_at)';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'username' => $user->getUsername(),
                'password_hash' => $user->getPassword(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);
        } else {
            // Check if another user with the same username exists
            $query = 'SELECT id FROM users WHERE username = :username AND id != :id';
            $statement = $this->pdo->prepare($query);
            $statement->execute([
                'username' => $user->getUsername(),
                'id' => $user->getId(),
            ]);
            $existing = $statement->fetch();
            if ($existing) {
                throw new \RuntimeException('This username exists.');
            }
           
        }
    }
}
