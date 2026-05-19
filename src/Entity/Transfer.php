<?php

namespace App\Entity;

use App\Repository\TransferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'transfers')]
#[ORM\HasLifecycleCallbacks]
class Transfer
{
    public const DEFAULT_MAX_DOWNLOADS = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'string', unique: true, length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'transfers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private int $totalSizeBytes = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $downloadCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $maxDownloads = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRevoked = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: TransferFile::class, mappedBy: 'transfer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $files;

    #[ORM\OneToMany(targetEntity: DownloadLog::class, mappedBy: 'transfer', orphanRemoval: true)]
    private Collection $downloadLogs;

    #[ORM\ManyToOne(targetEntity: FileRequest::class, inversedBy: 'transfers')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FileRequest $fileRequest = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $senderEmail = null;

    private ?string $rawToken = null;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->files = new ArrayCollection();
        $this->downloadLogs = new ArrayCollection();
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

    public function getTotalSizeBytes(): int
    {
        return $this->totalSizeBytes;
    }

    public function setTotalSizeBytes(int $totalSizeBytes): static
    {
        $this->totalSizeBytes = $totalSizeBytes;
        return $this;
    }

    public function recalculateTotalSize(): static
    {
        $total = 0;
        foreach ($this->files as $file) {
            $total += $file->getSizeBytes();
        }
        $this->totalSizeBytes = $total;
        return $this;
    }

    public function getDownloadCount(): int
    {
        return $this->downloadCount;
    }

    public function setDownloadCount(int $downloadCount): static
    {
        $this->downloadCount = $downloadCount;
        return $this;
    }

    public function incrementDownloadCount(): static
    {
        $this->downloadCount++;
        return $this;
    }

    public function getMaxDownloads(): int
    {
        return $this->maxDownloads;
    }

    public function setMaxDownloads(int $maxDownloads): static
    {
        $this->maxDownloads = $maxDownloads;
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

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;
        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function setIsRevoked(bool $isRevoked): static
    {
        $this->isRevoked = $isRevoked;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isExhausted(): bool
    {
        return $this->downloadCount >= $this->maxDownloads;
    }

    public function isDownloadable(): bool
    {
        return !$this->isExpired() && !$this->isExhausted() && !$this->isRevoked;
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

    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(TransferFile $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setTransfer($this);
        }
        return $this;
    }

    public function removeFile(TransferFile $file): static
    {
        if ($this->files->removeElement($file)) {
            if ($file->getTransfer() === $this) {
                $file->setTransfer(null);
            }
        }
        return $this;
    }

    public function getDownloadLogs(): Collection
    {
        return $this->downloadLogs;
    }

    public function getRemainingDownloads(): int
    {
        return max(0, $this->maxDownloads - $this->downloadCount);
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->totalSizeBytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    public function isText(): bool
    {
        foreach ($this->files as $file) {
            if ($file->isText()) {
                return true;
            }
        }
        return false;
    }

    public function getFileCount(): int
    {
        return $this->files->count();
    }

    public function getOriginalFilename(): string
    {
        if ($this->files->count() === 1) {
            return $this->files->first()->getOriginalFilename();
        }
        return $this->files->count() . ' files';
    }

    public function getFileRequest(): ?FileRequest
    {
        return $this->fileRequest;
    }

    public function setFileRequest(?FileRequest $fileRequest): static
    {
        $this->fileRequest = $fileRequest;
        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->senderName = $senderName;
        return $this;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(?string $senderEmail): static
    {
        $this->senderEmail = $senderEmail;
        return $this;
    }
}