<?php

namespace App\Entity;

use App\Repository\LibraryItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryItemRepository::class)]
#[ORM\Table(name: 'library_items')]
#[ORM\Index(columns: ['source_id', 'path'], name: 'idx_library_source_path')]
class LibraryItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: LibrarySource::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LibrarySource $source;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $path;

    #[ORM\Column(type: 'string', length: 512)]
    private string $name;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $parentPath = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $relativePath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $sizeBytes = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDirectory = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getSource(): LibrarySource { return $this->source; }
    public function setSource(LibrarySource $source): static { $this->source = $source; return $this; }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getRelativePath(): ?string { return $this->relativePath; }
    public function setRelativePath(?string $relativePath): static { $this->relativePath = $relativePath; return $this; }

    public function getParentPath(): ?string { return $this->parentPath; }
    public function setParentPath(?string $parentPath): static { $this->parentPath = $parentPath; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }

    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $sizeBytes): static { $this->sizeBytes = $sizeBytes; return $this; }

    public function isDirectory(): bool { return $this->isDirectory; }
    public function setIsDirectory(bool $isDirectory): static { $this->isDirectory = $isDirectory; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->sizeBytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
