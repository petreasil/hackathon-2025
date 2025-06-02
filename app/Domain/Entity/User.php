<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public ?int $id,
        public string $username,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUsername(): string
    {
        return $this->username;
    }
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }
    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }
    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
    public function getPassword(): string
    {
        return $this->passwordHash;
    }
    public function setPassword(string $password): void
    {
        $this->passwordHash = $password;
    }
}
