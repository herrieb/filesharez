<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', unique: true, length: 32)]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'bigint', options: ['default' => 10737418240])]
    private int $quotaBytes = 10737418240;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $reservedBytes = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 32, options: ['default' => 'longhorn'])]
    private string $theme = 'longhorn';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Transfer::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $transfers;

    #[ORM\OneToMany(targetEntity: FileRequest::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $fileRequests;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->transfers = new ArrayCollection();
        $this->fileRequests = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
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

    public function getQuotaBytes(): int
    {
        return $this->quotaBytes;
    }

    public function setQuotaBytes(int $quotaBytes): static
    {
        $this->quotaBytes = $quotaBytes;
        return $this;
    }

    public function getReservedBytes(): int
    {
        return $this->reservedBytes;
    }

    public function setReservedBytes(int $reservedBytes): static
    {
        $this->reservedBytes = max(0, $reservedBytes);
        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function reserveBytes(int $bytes): static
    {
        $this->reservedBytes = max(0, $this->reservedBytes + $bytes);
        return $this;
    }

    public function releaseBytes(int $bytes): static
    {
        $this->reservedBytes = max(0, $this->reservedBytes - $bytes);
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

    public function getFileRequests(): Collection
    {
        return $this->fileRequests;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles());
    }

    public function getUsedStorage(): int
    {
        $total = 0;
        foreach ($this->transfers as $transfer) {
            $total += $transfer->getTotalSizeBytes();
        }
        return $total;
    }

    public function getQuotaRemaining(): int
    {
        return max(0, $this->quotaBytes - $this->getUsedStorage() - $this->reservedBytes);
    }
}