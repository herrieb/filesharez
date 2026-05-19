<?php

namespace App\Service;

use App\Entity\FileRequest;
use App\Entity\User;
use App\Repository\FileRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

class FileRequestService
{
    public function __construct(
        private FileRequestRepository $fileRequestRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createRequest(
        User $user,
        string $name,
        ?string $description = null,
        int $maxFiles = 10,
        int $maxFileSizeBytes = 1073741824,
        int $maxTotalSizeBytes = 5368709120,
        ?string $password = null,
        ?int $expiryDays = null,
    ): FileRequest {
        $rawToken = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $rawToken);

        $request = new FileRequest();
        $request->setUser($user)
            ->setTokenHash($tokenHash)
            ->setRawToken($rawToken)
            ->setName($name)
            ->setDescription($description)
            ->setMaxFiles($maxFiles)
            ->setMaxFileSizeBytes($maxFileSizeBytes)
            ->setMaxTotalSizeBytes($maxTotalSizeBytes)
            ->setExpiresAt(new \DateTimeImmutable('+' . ($expiryDays ?? 30) . ' days'));

        if ($password) {
            $request->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
        }

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function findByToken(string $token): ?FileRequest
    {
        $tokenHash = hash('sha256', $token);
        return $this->fileRequestRepository->findByTokenHash($tokenHash);
    }

    public function deactivateRequest(FileRequest $request): void
    {
        $request->setIsActive(false);
        $this->entityManager->flush();
    }

    public function activateRequest(FileRequest $request): void
    {
        $request->setIsActive(true);
        $this->entityManager->flush();
    }

    public function deleteRequest(FileRequest $request): void
    {
        $this->entityManager->remove($request);
        $this->entityManager->flush();
    }
}