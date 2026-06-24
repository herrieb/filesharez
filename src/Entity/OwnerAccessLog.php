<?php

namespace App\Entity;

use App\Repository\OwnerAccessLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OwnerAccessLogRepository::class)]
#[ORM\Table(name: 'owner_access_logs')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_owner_log_user')]
#[ORM\Index(columns: ['source_id', 'created_at'], name: 'idx_owner_log_source')]
#[ORM\Index(columns: ['created_at'], name: 'idx_owner_log_created')]
class OwnerAccessLog
{
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_PREVIEW = 'preview';
    public const ACTION_ZIP = 'zip';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LibrarySource::class)]
    #[ORM\JoinColumn(name: 'source_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private LibrarySource $source;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $path;

    #[ORM\Column(type: 'string', length: 16)]
    private string $action;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $sizeBytes = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

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
    public function getSource(): LibrarySource { return $this->source; }
    public function setSource(LibrarySource $source): static { $this->source = $source; return $this; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): static { $this->path = $path; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getSizeBytes(): ?int { return $this->sizeBytes; }
    public function setSizeBytes(?int $bytes): static { $this->sizeBytes = $bytes; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): static { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): static { $this->userAgent = $ua; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
