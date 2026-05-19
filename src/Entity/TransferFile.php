<?php

namespace App\Entity;

use App\Repository\TransferFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferFileRepository::class)]
#[ORM\Table(name: 'transfer_files')]
class TransferFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', unique: true, length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Transfer::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Transfer $transfer;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 255)]
    private string $storedFilename;

    #[ORM\Column(type: 'string', length: 255)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isText = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $textContent = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $downloadCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTransfer(): Transfer
    {
        return $this->transfer;
    }

    public function setTransfer(Transfer $transfer): static
    {
        $this->transfer = $transfer;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(string $storedFilename): static
    {
        $this->storedFilename = $storedFilename;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function isText(): bool
    {
        return $this->isText;
    }

    public function setIsText(bool $isText): static
    {
        $this->isText = $isText;
        return $this;
    }

    public function getTextContent(): ?string
    {
        return $this->textContent;
    }

    public function setTextContent(?string $textContent): static
    {
        $this->textContent = $textContent;
        return $this;
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function incrementDownloadCount(): static
    {
        $this->downloadCount++;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->sizeBytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}