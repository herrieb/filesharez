<?php

namespace App\Entity;

use App\Repository\SavedTransferTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SavedTransferTokenRepository::class)]
#[ORM\Table(name: 'saved_transfer_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_user_transfer', columns: ['user_id', 'transfer_id'])]
class SavedTransferToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Transfer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Transfer $transfer;

    #[ORM\Column(type: 'string', length: 255)]
    private string $rawToken;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getTransfer(): Transfer { return $this->transfer; }
    public function setTransfer(Transfer $transfer): static { $this->transfer = $transfer; return $this; }

    public function getRawToken(): string { return $this->rawToken; }
    public function setRawToken(string $rawToken): static { $this->rawToken = $rawToken; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
