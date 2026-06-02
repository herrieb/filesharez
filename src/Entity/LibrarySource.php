<?php

namespace App\Entity;

use App\Repository\LibrarySourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibrarySourceRepository::class)]
#[ORM\Table(name: 'library_sources')]
class LibrarySource
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $path;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastScannedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $itemCount = 0;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $totalSizeBytes = 0;

    #[ORM\OneToMany(targetEntity: LibraryItem::class, mappedBy: 'source', orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?string { return $this->id; }

    public function getOwner(): User { return $this->owner; }
    public function setOwner(User $owner): static { $this->owner = $owner; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastScannedAt(): ?\DateTimeImmutable { return $this->lastScannedAt; }
    public function setLastScannedAt(?\DateTimeImmutable $lastScannedAt): static { $this->lastScannedAt = $lastScannedAt; return $this; }

    public function getItemCount(): int { return $this->itemCount; }
    public function setItemCount(int $itemCount): static { $this->itemCount = $itemCount; return $this; }

    public function getTotalSizeBytes(): int { return $this->totalSizeBytes; }
    public function setTotalSizeBytes(int $totalSizeBytes): static { $this->totalSizeBytes = $totalSizeBytes; return $this; }

    public function getItems(): Collection { return $this->items; }
}
