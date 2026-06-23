<?php

namespace App\Entity;

use App\Repository\UploadSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UploadSessionRepository::class)]
#[ORM\Table(name: 'upload_sessions')]
class UploadSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 512)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes = 0;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $offsetBytes = 0;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $tempPath;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+24 hours');
    }

    public function getId(): ?string { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $name): static { $this->originalFilename = $name; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $type): static { $this->mimeType = $type; return $this; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $size): static { $this->sizeBytes = $size; return $this; }
    public function getOffsetBytes(): int { return $this->offsetBytes; }
    public function setOffsetBytes(int $offset): static { $this->offsetBytes = $offset; return $this; }
    public function addBytes(int $bytes): static { $this->offsetBytes += $bytes; return $this; }
    public function getTempPath(): string { return $this->tempPath; }
    public function setTempPath(string $path): static { $this->tempPath = $path; return $this; }
    public function getMetadata(): ?string { return $this->metadata; }
    public function setMetadata(?string $json): static { $this->metadata = $json; return $this; }
    public function getMetadataArray(): array
    {
        if (!$this->metadata) return [];
        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : [];
    }
    public function setMetadataArray(array $data): static
    {
        $this->metadata = json_encode($data, JSON_UNESCAPED_SLASHES);
        return $this;
    }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(\DateTimeImmutable $when): static { $this->expiresAt = $when; return $this; }
    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+24 hours');
        return $this;
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function isComplete(): bool { return $this->offsetBytes >= $this->sizeBytes; }
    public function isExpired(): bool { return $this->expiresAt < new \DateTimeImmutable(); }
}
