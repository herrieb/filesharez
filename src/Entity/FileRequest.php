<?php

namespace App\Entity;

use App\Repository\FileRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRequestRepository::class)]
#[ORM\Table(name: 'file_requests')]
#[ORM\HasLifecycleCallbacks]
class FileRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', unique: true, length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'fileRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    private int $maxFiles = 10;

    #[ORM\Column(type: 'bigint', options: ['default' => 1073741824])]
    private int $maxFileSizeBytes = 1073741824;

    #[ORM\Column(type: 'bigint', options: ['default' => 5368709120])]
    private int $maxTotalSizeBytes = 5368709120;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Transfer::class, mappedBy: 'fileRequest')]
    private Collection $transfers;

    private ?string $rawToken = null;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->transfers = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getRawToken(): ?string
    {
        return $this->rawToken;
    }

    public function setRawToken(string $rawToken): static
    {
        $this->rawToken = $rawToken;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMaxFiles(): int
    {
        return $this->maxFiles;
    }

    public function setMaxFiles(int $maxFiles): static
    {
        $this->maxFiles = $maxFiles;
        return $this;
    }

    public function getMaxFileSizeBytes(): int
    {
        return $this->maxFileSizeBytes;
    }

    public function setMaxFileSizeBytes(int $maxFileSizeBytes): static
    {
        $this->maxFileSizeBytes = $maxFileSizeBytes;
        return $this;
    }

    public function getMaxTotalSizeBytes(): int
    {
        return $this->maxTotalSizeBytes;
    }

    public function setMaxTotalSizeBytes(int $maxTotalSizeBytes): static
    {
        $this->maxTotalSizeBytes = $maxTotalSizeBytes;
        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTransfers(): Collection
    {
        return $this->transfers;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAcceptingUploads(): bool
    {
        return $this->isActive && !$this->isExpired();
    }

    public function getUploadCount(): int
    {
        return $this->transfers->count();
    }

    public function getRemainingUploads(): int
    {
        return max(0, $this->maxFiles - $this->transfers->count());
    }
}