<?php

namespace App\Theme;

class ThemeRegistry
{
    /** @var array<string, Theme> */
    private array $themes = [];

    private AbstractTheme $default;

    private string $projectDir;

    public function __construct(
        string $projectDir,
        string $defaultId = 'longhorn',
    ) {
        $this->projectDir = $projectDir;
        $builtIn = [
            new LonghornTheme(),
            new SunsetTheme(),
            new MidoriTheme(),
            new RoseQuartzTheme(),
            new CrtTheme(),
            new AuroraTheme(),
            new TokyoTheme(),
            new AquarelleTheme(),
            new BrutalistTheme(),
        ];
        foreach ($builtIn as $theme) {
            $this->themes[$theme->id] = $theme;
        }

        $store = new ThemeStore($projectDir, $this);
        $store->ensureThemesDir();
        foreach ($store->listCustomThemeDirs() as $dir) {
            $id = basename($dir);
            if (isset($this->themes[$id])) continue;
            try {
                $this->themes[$id] = $store->loadFromDir($dir, $id);
            } catch (\Throwable) {
                // skip broken themes silently
            }
        }

        $this->default = $this->themes[$defaultId] ?? $builtIn[0];
    }

    public function default(): AbstractTheme
    {
        return $this->default;
    }

    /** @return array<string, Theme> */
    public function all(): array
    {
        return $this->themes;
    }

    public function get(string $id): ?AbstractTheme
    {
        return $this->themes[$id] ?? null;
    }

    public function resolveOrDefault(?string $id): AbstractTheme
    {
        if ($id !== null && isset($this->themes[$id])) {
            return $this->themes[$id];
        }
        return $this->default;
    }

    /**
     * Reload built-in + custom theme list. Used after a theme is installed
     * or deleted at runtime.
     */
    public function reload(): void
    {
        $reflection = new \ReflectionClass($this);
        $prop = $reflection->getProperty('themes');
        $prop->setAccessible(true);
        $builtIn = [
            new LonghornTheme(),
            new SunsetTheme(),
            new MidoriTheme(),
            new RoseQuartzTheme(),
            new CrtTheme(),
            new AuroraTheme(),
            new TokyoTheme(),
            new AquarelleTheme(),
            new BrutalistTheme(),
        ];
        $map = [];
        foreach ($builtIn as $theme) {
            $map[$theme->id] = $theme;
        }
        $this->themes = $map;
    }

    /**
     * Repopulate the registry from current built-ins + on-disk custom themes.
     * Called after a theme is installed or deleted.
     */
    public function rescanFromDisk(string $projectDir): void
    {
        $this->reload();
        $store = new ThemeStore($projectDir, $this);
        $store->ensureThemesDir();
        foreach ($store->listCustomThemeDirs() as $dir) {
            $id = basename($dir);
            if (isset($this->themes[$id])) continue;
            try {
                $this->themes[$id] = $store->loadFromDir($dir, $id);
            } catch (\Throwable) {
            }
        }
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function reloadFromDisk(): void
    {
        $this->rescanFromDisk($this->projectDir);
    }

    public function cssBlock(): string
    {
        $out = ":root, [data-theme=\"longhorn\"] {\n";
        $out .= "  " . $this->default->cssVariables() . "\n";
        $out .= "}\n";
        foreach ($this->themes as $theme) {
            if ($theme->id === 'longhorn') {
                continue;
            }
            $out .= "[data-theme=\"{$theme->id}\"] {\n";
            $out .= "  " . $theme->cssVariables() . "\n";
            $out .= "}\n";
            if (!empty($theme->extraCss)) {
                $out .= $theme->extraCss . "\n";
            }
        }
        return $out;
    }
}
