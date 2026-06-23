<?php

namespace App\Theme;

use App\Entity\User;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

/**
 * Manages the on-disk theme library at var/themes/.
 * Each theme is a directory containing a theme.xml descriptor and an optional
 * assets/ subfolder with extra CSS / fonts / preview.
 */
class ThemeStore
{
    public const THEMES_DIR = 'var/themes';

    public function __construct(
        private string $projectDir,
        private ThemeRegistry $registry,
    ) {
    }

    public function themesDir(): string
    {
        return $this->projectDir . '/' . self::THEMES_DIR;
    }

    public function themeDir(string $id): string
    {
        return $this->themesDir() . '/' . $id;
    }

    public function ensureThemesDir(): void
    {
        $dir = $this->themesDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /**
     * Build a zip for either a built-in or custom theme. For built-ins we
     * include the same theme.xml schema so the user can edit it and re-upload.
     */
    public function buildZip(AbstractTheme $theme): string
    {
        $this->ensureThemesDir();
        $tmp = tempnam(sys_get_temp_dir(), 'theme-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->writeDescriptor($zip, $theme);

        $dir = $this->themeDir($theme->id);
        if (is_dir($dir)) {
            $this->addDirectoryToZip($zip, $dir, '');
        }

        $zip->close();
        return $tmp;
    }

    /**
     * Install a theme from a zip upload. Returns the Theme that was created
     * (or null if the theme is one of the built-ins — in that case the
     * zip is rejected as a duplicate).
     */
    public function installFromZip(string $zipPath, ?User $user = null): Theme
    {
        $this->ensureThemesDir();

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Not a valid zip file');
        }

        $tmpDir = sys_get_temp_dir() . '/theme-install-' . bin2hex(random_bytes(8));
        @mkdir($tmpDir, 0775, true);
        $zip->extractTo($tmpDir);
        $zip->close();

        $descriptorPath = $tmpDir . '/theme.xml';
        if (!file_exists($descriptorPath)) {
            $this->rrmdir($tmpDir);
            throw new \RuntimeException('Theme zip is missing theme.xml');
        }

        $descriptor = $this->parseDescriptor($descriptorPath);

        if (isset($descriptor['id'])) {
            $id = (string) $descriptor['id'];
        } else {
            throw new \RuntimeException('theme.xml must include <id>');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $id)) {
            $this->rrmdir($tmpDir);
            throw new \RuntimeException('Invalid theme id: must be lowercase letters, digits, dashes (max 42 chars)');
        }

        if ($this->registry->get($id) !== null) {
            $this->rrmdir($tmpDir);
            throw new \RuntimeException("Theme id '{$id}' already exists. Pick a unique id in theme.xml.");
        }

        $target = $this->themeDir($id);
        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        $this->rcopy($tmpDir, $target);
        $this->rrmdir($tmpDir);

        return $this->loadFromDir($target, $id);
    }

    public function loadFromDir(string $dir, string $id): Theme
    {
        $descriptorPath = $dir . '/theme.xml';
        if (!file_exists($descriptorPath)) {
            throw new \RuntimeException("Theme dir '{$id}' is missing theme.xml");
        }
        $data = $this->parseDescriptor($descriptorPath);

        $assetsDir = $dir . '/assets';
        $assets = [];
        if (is_dir($assetsDir)) {
            foreach (scandir($assetsDir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = $assetsDir . '/' . $f;
                if (is_file($path)) {
                    $assets[$f] = $path;
                }
            }
        }

        $extraCssPath = $dir . '/assets/styles.css';
        $extraCss = is_file($extraCssPath) ? file_get_contents($extraCssPath) : '';

        $required = ['displayName', 'accentPrimary', 'accentGlow', 'accentRgb', 'slateBase', 'slateBaseRgb', 'bodyGradient', 'ambientGlow1', 'ambientGlow2', 'fontFamily'];
        foreach ($required as $k) {
            if (!isset($data[$k]) || $data[$k] === '') {
                throw new \RuntimeException("theme.xml is missing required field <{$k}>");
            }
        }

        $theme = new Theme(
            id: $id,
            displayName: (string) $data['displayName'],
            description: (string) ($data['description'] ?? ''),
            accentPrimary: (string) $data['accentPrimary'],
            accentGlow: (string) $data['accentGlow'],
            accentDim: (string) ($data['accentDim'] ?? $data['accentPrimary']),
            accentSecondary: isset($data['accentSecondary']) ? (string) $data['accentSecondary'] : null,
            accentSecondaryRgb: isset($data['accentSecondaryRgb']) ? (string) $data['accentSecondaryRgb'] : null,
            slateBase: (string) $data['slateBase'],
            slateSurface: (string) ($data['slateSurface'] ?? $data['slateBase']),
            slateDeep: (string) ($data['slateDeep'] ?? $data['slateBase']),
            success: (string) ($data['success'] ?? '#10B981'),
            danger: (string) ($data['danger'] ?? '#EF4444'),
            warn: (string) ($data['warn'] ?? '#F59E0B'),
            accentRgb: (string) $data['accentRgb'],
            slateBaseRgb: (string) $data['slateBaseRgb'],
            bodyGradient: (string) $data['bodyGradient'],
            ambientGlow1: (string) $data['ambientGlow1'],
            ambientGlow2: (string) $data['ambientGlow2'],
            fontFamily: (string) $data['fontFamily'],
            extraCss: $extraCss,
        );

        return $theme;
    }

    public function delete(string $id): void
    {
        $dir = $this->themeDir($id);
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }
    }

    /**
     * @return array<int, string> absolute paths to theme directories on disk
     */
    public function listCustomThemeDirs(): array
    {
        $this->ensureThemesDir();
        $dirs = [];
        foreach (scandir($this->themesDir()) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $this->themesDir() . '/' . $entry;
            if (is_dir($path) && file_exists($path . '/theme.xml')) {
                $dirs[] = $path;
            }
        }
        return $dirs;
    }

    private function parseDescriptor(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Could not read theme.xml');
        }

        $prevUseErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_use_internal_errors($prevUseErrors);
        if ($xml === false) {
            throw new \RuntimeException('Invalid XML in theme.xml');
        }

        $data = [];
        foreach ($xml->children() as $key => $val) {
            $data[(string) $key] = (string) $val;
        }
        return $data;
    }

    private function writeDescriptor(ZipArchive $zip, AbstractTheme $theme): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= "<theme>\n";
        $xml .= "  <id>{$theme->id}</id>\n";
        $xml .= "  <displayName>" . htmlspecialchars($theme->displayName, ENT_XML1) . "</displayName>\n";
        $xml .= "  <description>" . htmlspecialchars($theme->description, ENT_XML1) . "</description>\n";
        $xml .= "  <creator>FileShareZ</creator>\n";
        $xml .= "  <createdAt>" . date('c') . "</createdAt>\n";
        $xml .= "  <accentPrimary>{$theme->accentPrimary}</accentPrimary>\n";
        $xml .= "  <accentGlow>{$theme->accentGlow}</accentGlow>\n";
        $xml .= "  <accentDim>{$theme->accentDim}</accentDim>\n";
        if ($theme->accentSecondary) {
            $xml .= "  <accentSecondary>{$theme->accentSecondary}</accentSecondary>\n";
        }
        if ($theme->accentSecondaryRgb) {
            $xml .= "  <accentSecondaryRgb>{$theme->accentSecondaryRgb}</accentSecondaryRgb>\n";
        }
        $xml .= "  <slateBase>{$theme->slateBase}</slateBase>\n";
        $xml .= "  <slateSurface>{$theme->slateSurface}</slateSurface>\n";
        $xml .= "  <slateDeep>{$theme->slateDeep}</slateDeep>\n";
        $xml .= "  <success>{$theme->success}</success>\n";
        $xml .= "  <danger>{$theme->danger}</danger>\n";
        $xml .= "  <warn>{$theme->warn}</warn>\n";
        $xml .= "  <accentRgb>{$theme->accentRgb}</accentRgb>\n";
        $xml .= "  <slateBaseRgb>{$theme->slateBaseRgb}</slateBaseRgb>\n";
        $xml .= "  <bodyGradient><![CDATA[{$theme->bodyGradient}]]></bodyGradient>\n";
        $xml .= "  <ambientGlow1><![CDATA[{$theme->ambientGlow1}]]></ambientGlow1>\n";
        $xml .= "  <ambientGlow2><![CDATA[{$theme->ambientGlow2}]]></ambientGlow2>\n";
        $xml .= "  <fontFamily><![CDATA[{$theme->fontFamily}]]></fontFamily>\n";
        $xml .= "</theme>\n";
        $zip->addFromString('theme.xml', $xml);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $f) {
            $relative = ltrim(substr($f->getPathname(), strlen($dir)), '/');
            $local = $prefix === '' ? $relative : ($prefix . '/' . $relative);
            $zip->addFile($f->getPathname(), $local);
        }
    }

    private function rcopy(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0775, true);
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $f) {
            $target = $dst . '/' . ltrim(substr($f->getPathname(), strlen($src)), '/');
            if ($f->isDir()) {
                if (!is_dir($target)) @mkdir($target, 0775, true);
            } else {
                copy($f->getPathname(), $target);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) {
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            } else {
                @unlink($f->getPathname());
            }
        }
        @rmdir($dir);
    }
}
