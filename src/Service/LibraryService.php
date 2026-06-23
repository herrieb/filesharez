<?php

namespace App\Service;

use App\Entity\LibraryItem;
use App\Entity\LibrarySource;
use App\Entity\SavedTransferToken;
use App\Entity\Transfer;
use App\Entity\TransferFile;
use App\Entity\User;
use App\Message\SendTransferEmail;
use App\Repository\LibraryItemRepository;
use App\Repository\LibrarySourceRepository;
use App\Repository\SavedTransferTokenRepository;
use App\Storage\LibraryStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class LibraryService
{
    public function __construct(
        private LibrarySourceRepository $sourceRepository,
        private LibraryItemRepository $itemRepository,
        private SavedTransferTokenRepository $savedTokenRepository,
        private EntityManagerInterface $entityManager,
        private LibraryStorage $libraryStorage,
        private MessageBusInterface $messageBus,
        private int $defaultExpiryDays,
        private int $defaultMaxDownloads,
        private int $scanDepth,
        private string $maxUploadSize,
    ) {
    }

    private function maxUploadSizeBytes(): int
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $value = trim($this->maxUploadSize);
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;
        return $cache = match ($unit) {
            'g' => $num * 1073741824,
            'm' => $num * 1048576,
            'k' => $num * 1024,
            default => $num,
        };
    }

    public function createSource(User $owner, string $name, string $path): LibrarySource
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException('Library path does not exist or is not a directory: ' . $path);
        }

        $source = new LibrarySource();
        $source->setOwner($owner)
            ->setName($name)
            ->setPath($real)
            ->setIsActive(true);

        $this->libraryStorage->addAllowedRoot($real);
        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->rescanSource($source);
        return $source;
    }

    public function rescanSource(LibrarySource $source, ?int $depth = null): int
    {
        if (!$source->isActive()) {
            return 0;
        }

        $depth = $depth ?? $this->scanDepth;
        $entries = $this->libraryStorage->scanRecursive($source->getPath(), $depth);

        $existingByPath = [];
        foreach ($source->getItems() as $item) {
            $existingByPath[$item->getPath()] = $item;
        }

        $seen = [];
        $totalSize = 0;
        foreach ($entries as $entry) {
            $path = $entry['path'];
            $seen[$path] = true;
            $totalSize += $entry['size_bytes'];

            if (isset($existingByPath[$path])) {
                $item = $existingByPath[$path];
                $item->setName($entry['name'])
                    ->setParentPath($entry['parent_path'])
                    ->setSizeBytes($entry['size_bytes'])
                    ->setIsDirectory($entry['is_directory'])
                    ->setMimeType($entry['mime_type'])
                    ->setRelativePath($entry['relative_path']);
            } else {
                $item = new LibraryItem();
                $item->setSource($source)
                    ->setPath($path)
                    ->setName($entry['name'])
                    ->setParentPath($entry['parent_path'])
                    ->setRelativePath($entry['relative_path'])
                    ->setSizeBytes($entry['size_bytes'])
                    ->setIsDirectory($entry['is_directory'])
                    ->setMimeType($entry['mime_type']);
                $this->entityManager->persist($item);
            }
        }

        foreach ($source->getItems() as $item) {
            if (!isset($seen[$item->getPath()])) {
                $this->entityManager->remove($item);
            }
        }

        $source->setItemCount(count($entries))
            ->setTotalSizeBytes($totalSize)
            ->setLastScannedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
        return count($entries);
    }

    public function deleteSource(LibrarySource $source): void
    {
        $this->entityManager->remove($source);
        $this->entityManager->flush();
    }

    public function assertOwnsSource(LibrarySource $source, User $user, bool $adminAllowed = false): void
    {
        if ($source->getOwner()->getId() !== $user->getId() && !($adminAllowed && in_array('ROLE_ADMIN', $user->getRoles(), true))) {
            throw new \RuntimeException('Access denied');
        }
    }

    /**
     * Browse a folder inside a source. $relativePath is absolute-style ('/a/b/c')
     * relative to the source root; pass '/' for the root. Returns the children
     * of that folder plus breadcrumb segments.
     *
     * @return array{source: LibrarySource, current: array{absolute:string,relative:string,name:string,is_directory:bool}, breadcrumb: array<int,array{name:string,relative:string}>, parent_relative: ?string, children: array<int,LibraryItem|array>, saved_token: ?SavedTransferToken, show_hidden: bool}
     */
    public function browsePath(LibrarySource $source, string $relativePath, User $user, bool $includeHidden = true, int $page = 1, int $perPage = 200): array
    {
        $relativePath = '/' . ltrim($relativePath, '/');
        if ($relativePath !== '/' && str_contains($relativePath, '..')) {
            throw new \RuntimeException('Invalid path');
        }

        $currentAbsolute = $this->libraryStorage->resolveUnderSource($source->getPath(), $relativePath);
        $isDir = is_dir($currentAbsolute);

        $breadcrumb = $this->buildBreadcrumb($source, $relativePath);

        $parentRelative = null;
        if ($relativePath !== '/') {
            $parentRelative = dirname($relativePath);
            if ($parentRelative === '.' || $parentRelative === '') {
                $parentRelative = '/';
            }
        }

        $children = [];
        $currentItem = null;
        if ($isDir) {
            $diskEntries = $this->libraryStorage->listDirectory($currentAbsolute, $source->getPath());
            foreach ($diskEntries as $entry) {
                if (!$includeHidden && str_starts_with($entry['name'], '.')) {
                    continue;
                }
                $children[] = $this->ensureItemForEntry($source, $entry);
            }

            $total = count($children);
            $children = array_slice($children, max(0, ($page - 1) * $perPage), $perPage);
            $currentItem = [
                'absolute' => $currentAbsolute,
                'relative' => $relativePath,
                'name' => basename($currentAbsolute) ?: $source->getName(),
                'is_directory' => true,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
            ];
        } else {
            $entry = [
                'path' => $currentAbsolute,
                'name' => basename($currentAbsolute),
                'parent_path' => dirname($currentAbsolute),
                'relative_path' => ltrim($relativePath, '/'),
                'size_bytes' => is_file($currentAbsolute) ? filesize($currentAbsolute) : 0,
                'is_directory' => false,
                'mime_type' => $this->libraryStorage->mimeType($currentAbsolute),
            ];
            $currentItem = [
                'absolute' => $currentAbsolute,
                'relative' => $relativePath,
                'name' => $entry['name'],
                'is_directory' => false,
                'item' => $this->ensureItemForEntry($source, $entry),
            ];
        }

        $savedToken = $this->savedTokenRepository->findOneByUserAndRelativePath(
            $user,
            $source,
            $this->normalizeRelative($relativePath)
        );

        return [
            'source' => $source,
            'current' => $currentItem,
            'breadcrumb' => $breadcrumb,
            'parent_relative' => $parentRelative,
            'children' => $children,
            'saved_token' => $savedToken,
            'show_hidden' => $includeHidden,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Persist a LibraryItem for a disk entry if we don't already have one.
     * Used both during rescan and lazy creation during browse.
     */
    public function ensureItemForEntry(LibrarySource $source, array $entry): LibraryItem
    {
        $existing = $this->itemRepository->findOneBy([
            'source' => $source->getId(),
            'path' => $entry['path'],
        ]);
        if ($existing) {
            $existing->setName($entry['name'])
                ->setParentPath($entry['parent_path'] ?? null)
                ->setSizeBytes($entry['size_bytes'])
                ->setIsDirectory($entry['is_directory'])
                ->setMimeType($entry['mime_type'] ?? null)
                ->setRelativePath($entry['relative_path'] ?? null);
            return $existing;
        }

        $item = new LibraryItem();
        $item->setSource($source)
            ->setPath($entry['path'])
            ->setName($entry['name'])
            ->setParentPath($entry['parent_path'] ?? null)
            ->setRelativePath($entry['relative_path'] ?? null)
            ->setSizeBytes($entry['size_bytes'])
            ->setIsDirectory($entry['is_directory'])
            ->setMimeType($entry['mime_type'] ?? null);
        $this->entityManager->persist($item);
        return $item;
    }

    /**
     * Build breadcrumb segments for a path. Each segment has 'name' and
     * 'relative' (absolute-style under the source root). First segment is the
     * source root itself.
     */
    public function buildBreadcrumb(LibrarySource $source, string $relativePath): array
    {
        $segments = [
            ['name' => $source->getName(), 'relative' => '/'],
        ];
        $relativePath = trim($relativePath, '/');
        if ($relativePath === '') {
            return $segments;
        }

        $parts = explode('/', $relativePath);
        $acc = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $acc = $acc === '' ? $part : $acc . '/' . $part;
            $segments[] = ['name' => $part, 'relative' => '/' . $acc];
        }
        return $segments;
    }

    private function normalizeRelative(string $relativePath): string
    {
        $normalized = '/' . ltrim($relativePath, '/');
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }
        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * Share a single item by relative path. Lazily persists a LibraryItem if
     * the entry is past the eager-scan depth.
     */
    public function sharePath(
        LibrarySource $source,
        string $relativePath,
        User $owner,
        int $maxDownloads = null,
        int $expiryDays = null,
        ?string $password = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): Transfer {
        $entry = $this->resolveEntryForShare($source, $relativePath);
        return $this->buildShareFromEntries($owner, [$entry], $maxDownloads, $expiryDays, $password, $recipientEmail, $message);
    }

    /**
     * Share a selection of paths under one source. Each path is resolved
     * against the source root; any single entry may be a file or directory.
     * Returns one Transfer with one TransferFile per entry, all backed by
     * absolute library paths (no copies).
     *
     * @param array<int,string> $relativePaths
     */
    public function shareSelection(
        LibrarySource $source,
        array $relativePaths,
        User $owner,
        int $maxDownloads = null,
        int $expiryDays = null,
        ?string $password = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): Transfer {
        if (count($relativePaths) === 0) {
            throw new \InvalidArgumentException('No paths selected');
        }

        $entries = [];
        $seenAbs = [];
        foreach ($relativePaths as $rel) {
            $entry = $this->resolveEntryForShare($source, $rel);
            if (isset($seenAbs[$entry['absolute']])) {
                continue;
            }
            $seenAbs[$entry['absolute']] = true;
            $entries[] = $entry;
        }

        return $this->buildShareFromEntries($owner, $entries, $maxDownloads, $expiryDays, $password, $recipientEmail, $message);
    }

    /**
     * Validate + resolve a relative path under $source, returning the disk
     * entry shape used to build a TransferFile. Lazy-creates a LibraryItem
     * row so the share can later be recovered.
     */
    private function resolveEntryForShare(LibrarySource $source, string $relativePath): array
    {
        $relativePath = '/' . ltrim($relativePath, '/');
        if ($relativePath !== '/' && str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException('Invalid path');
        }
        if (strlen($relativePath) > 1024) {
            throw new \InvalidArgumentException('Path too long');
        }

        $absolute = $this->libraryStorage->resolveUnderSource($source->getPath(), $relativePath);
        if (!file_exists($absolute)) {
            throw new \InvalidArgumentException('Path does not exist: ' . $relativePath);
        }

        $isDir = is_dir($absolute);
        $size = $isDir ? $this->dirSizeSafe($absolute) : filesize($absolute);

        if (!$isDir && $size > $this->maxUploadSizeBytes()) {
            throw new \InvalidArgumentException('Item exceeds maximum size: ' . $relativePath);
        }

        $entry = [
            'absolute' => $absolute,
            'path' => $absolute,
            'name' => basename($absolute),
            'parent_path' => dirname($absolute),
            'relative_path' => ltrim($relativePath, '/'),
            'size_bytes' => $size,
            'is_directory' => $isDir,
            'mime_type' => $isDir ? 'application/zip' : $this->libraryStorage->mimeType($absolute),
        ];

        $this->ensureItemForEntry($source, $entry);

        return $entry;
    }

    private function dirSizeSafe(string $path): int
    {
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            $total = 0;
            foreach ($iter as $f) {
                if ($f->isLink() || !$f->isFile()) {
                    continue;
                }
                $total += (int) $f->getSize();
            }
            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function buildShareFromEntries(
        User $owner,
        array $entries,
        ?int $maxDownloads,
        ?int $expiryDays,
        ?string $password,
        ?string $recipientEmail,
        ?string $message,
    ): Transfer {
        $maxDownloads = $maxDownloads ?? $this->defaultMaxDownloads;
        $expiryDays = $expiryDays ?? $this->defaultExpiryDays;

        $totalSize = 0;
        foreach ($entries as $e) {
            $totalSize += $e['size_bytes'];
        }
        if ($totalSize > $this->maxUploadSizeBytes()) {
            throw new \InvalidArgumentException(sprintf(
                'Total selection size (%s) exceeds maximum (%s)',
                $this->formatBytes($totalSize),
                $this->formatBytes($this->maxUploadSizeBytes())
            ));
        }

        $rawToken = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $rawToken);

        $transfer = new Transfer();
        $transfer->setUser($owner)
            ->setTokenHash($tokenHash)
            ->setRawToken($rawToken)
            ->setMaxDownloads($maxDownloads)
            ->setExpiresAt(new \DateTimeImmutable("+{$expiryDays} days"))
            ->setRecipientEmail($recipientEmail)
            ->setMessage($message)
            ->setIsFromLibrary(true)
            ->setTotalSizeBytes($totalSize);

        if ($password) {
            $transfer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
        }

        if (count($entries) === 1) {
            $single = $entries[0];
            $item = $this->itemRepository->findOneBy([
                'source' => $this->getSourceFromPath($owner, $single['path']),
                'path' => $single['path'],
            ]);
            if ($item) {
                $transfer->setLibraryItem($item);
            }
        }

        foreach ($entries as $entry) {
            $file = new TransferFile();
            $file->setTransfer($transfer)
                ->setOriginalFilename($entry['name'])
                ->setStoredFilename($entry['path'])
                ->setMimeType($entry['mime_type'] ?? 'application/octet-stream')
                ->setSizeBytes($entry['size_bytes'])
                ->setIsText(false);
            $this->entityManager->persist($file);
            $transfer->addFile($file);
        }

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->saveTokenForOwner($transfer, $owner, $entries);

        if ($recipientEmail) {
            $this->messageBus->dispatch(new SendTransferEmail(
                $transfer->getId(),
                $recipientEmail,
                $rawToken,
                $message
            ));
        }

        return $transfer;
    }

    private function getSourceFromPath(User $owner, string $absolutePath): ?string
    {
        $real = realpath($absolutePath);
        if ($real === false) {
            return null;
        }
        foreach ($this->sourceRepository->findBy(['owner' => $owner->getId()]) as $src) {
            $rootReal = realpath($src->getPath());
            if ($rootReal && ($real === $rootReal || str_starts_with($real, $rootReal . '/'))) {
                return $src->getId();
            }
        }
        return null;
    }

    private function saveTokenForOwner(Transfer $transfer, User $owner, array $entries): void
    {
        $rawToken = $transfer->getRawToken();
        if ($rawToken === null) {
            return;
        }

        $relativePath = '/';
        if (count($entries) === 1) {
            $relativePath = '/' . ltrim((string) ($entries[0]['relative_path'] ?? ''), '/');
            if ($relativePath === '/' || $relativePath === '') {
                $relativePath = '/' . ltrim($entries[0]['name'], '/');
            }
        }

        $existing = $this->savedTokenRepository->findOneByUserAndRelativePath($owner, null, $this->normalizeRelative($relativePath));
        if ($existing && $existing->getTransfer() !== null && $existing->getTransfer()->getId() === $transfer->getId()) {
            return;
        }

        $saved = new SavedTransferToken();
        $saved->setUser($owner)
            ->setTransfer($transfer)
            ->setRawToken($rawToken)
            ->setRelativePath($this->normalizeRelative($relativePath));
        $this->entityManager->persist($saved);
        $this->entityManager->flush();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Legacy entry point kept for backward compatibility. Resolves the
     * LibraryItem and routes through the share pipeline.
     */
    public function shareItem(
        LibraryItem $item,
        User $owner,
        int $maxDownloads = null,
        int $expiryDays = null,
        ?string $password = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): Transfer {
        $relative = '/' . ltrim((string) $item->getRelativePath(), '/');
        if ($relative === '/' || $relative === '') {
            $relative = '/' . ltrim($item->getName(), '/');
        }
        return $this->sharePath(
            $item->getSource(),
            $relative,
            $owner,
            $maxDownloads,
            $expiryDays,
            $password,
            $recipientEmail,
            $message,
        );
    }
}