<?php

namespace App\Entity;

use App\Repository\DownloadLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DownloadLogRepository::class)]
#[ORM\Table(name: 'download_logs')]
class DownloadLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', unique: true, length: 32)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Transfer::class, inversedBy: 'downloadLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Transfer $transfer;

    #[ORM\ManyToOne(targetEntity: TransferFile::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TransferFile $transferFile = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $downloadedAt;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(16));
        $this->downloadedAt = new \DateTimeImmutable();
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

    public function getTransferFile(): ?TransferFile
    {
        return $this->transferFile;
    }

    public function setTransferFile(?TransferFile $transferFile): static
    {
        $this->transferFile = $transferFile;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getDownloadedAt(): \DateTimeImmutable
    {
        return $this->downloadedAt;
    }
}