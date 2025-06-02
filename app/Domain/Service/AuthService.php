<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AlertGenerator $alertGenerator, // Inject AlertGenerator
    ) {}

    public function register(string $username, string $password): User
    {
        // Check that a user with the same username does not exist
        if ($this->users->findByUsername($username)) {
            throw new \RuntimeException('Username already exists.');
        }
       
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Create new user and persist
        $user = new User(null, $username, $hashedPassword, new \DateTimeImmutable());
        $this->users->save($user);

        return $user;
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->getPassword())) {
            return false;
        }

        // Store user ID and email in session
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getUsername();

        return true;

    }
    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_email']);
        $_SESSION['alert'] = $this->alertGenerator->createAlert(
            'success',
            'You have been logged out.'
        );
    }
}
