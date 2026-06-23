<?php

namespace App\Twig;

use App\Theme\AbstractTheme;
use App\Theme\ThemeRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeTwigExtension extends AbstractExtension
{
    public function __construct(
        private ThemeRegistry $registry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_color', [$this, 'themeColor'], ['is_safe' => ['html']]),
            new TwigFunction('theme_alpha', [$this, 'themeAlpha'], ['is_safe' => ['html']]),
            new TwigFunction('theme_block', [$this, 'themeBlock'], ['is_safe' => ['html']]),
            new TwigFunction('theme_choice', [$this, 'themeChoice'], ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'theme_registry' => $this->registry,
        ];
    }

    public function themeColor(AbstractTheme|string $theme, string $token, ?string $fallback = null): string
    {
        if (is_string($theme)) {
            $theme = $this->registry->get($theme) ?? $this->registry->default();
        }
        $value = match ($token) {
            'primary', 'accent-primary' => $theme->accentPrimary,
            'glow', 'accent-glow' => $theme->accentGlow,
            'dim', 'accent-dim' => $theme->accentDim,
            'slate-base' => $theme->slateBase,
            'slate-surface' => $theme->slateSurface,
            'slate-deep' => $theme->slateDeep,
            'success' => $theme->success,
            'danger' => $theme->danger,
            'warn' => $theme->warn,
            'body-gradient' => $theme->bodyGradient,
            'ambient-glow-1' => $theme->ambientGlow1,
            'ambient-glow-2' => $theme->ambientGlow2,
            'font-family' => $theme->fontFamily,
            default => $fallback ?? $theme->accentPrimary,
        };
        return $value;
    }

    public function themeAlpha(AbstractTheme|string $theme, string $token, float $alpha): string
    {
        $rgb = match ($token) {
            'primary', 'accent', 'accent-primary' => is_string($theme)
                ? ($this->registry->get($theme)?->accentRgb ?? '108,178,255')
                : $theme->accentRgb,
            'slate', 'slate-base' => is_string($theme)
                ? ($this->registry->get($theme)?->slateBaseRgb ?? '27,30,36')
                : $theme->slateBaseRgb,
            default => is_string($theme)
                ? ($this->registry->get($theme)?->accentRgb ?? '108,178,255')
                : $theme->accentRgb,
        };
        return "rgba($rgb,$alpha)";
    }

    public function themeBlock(): string
    {
        return '<style>' . $this->registry->cssBlock() . '</style>';
    }

    public function themeChoice(): string
    {
        $out = '';
        foreach ($this->registry->all() as $theme) {
            $out .= sprintf(
                '<div class="theme-card" data-theme-id="%s" style="%s">'
                . '<div class="theme-card-name">%s</div>'
                . '<div class="theme-card-desc">%s</div>'
                . '<div class="theme-card-swatches">'
                . '<span class="swatch" style="background:%s"></span>'
                . '<span class="swatch" style="background:%s"></span>'
                . '<span class="swatch" style="background:%s"></span>'
                . '</div>'
                . '</div>',
                $theme->id,
                '--t1:' . $theme->accentPrimary . ';--t2:' . $theme->accentGlow . ';--t3:' . $theme->slateBase . ';--font-family:' . $theme->fontFamily,
                htmlspecialchars($theme->displayName),
                htmlspecialchars($theme->description),
                $theme->accentPrimary,
                $theme->accentGlow,
                $theme->slateBase,
            );
        }
        return $out;
    }
}
