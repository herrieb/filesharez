<?php

namespace App\Theme;

abstract class AbstractTheme
{
    public function __construct(
        public readonly string $id,
        public readonly string $displayName,
        public readonly string $description,
        public readonly string $accentPrimary,
        public readonly string $accentGlow,
        public readonly string $accentDim,
        public readonly ?string $accentSecondary = null,
        public readonly ?string $accentSecondaryRgb = null,
        public readonly string $slateBase,
        public readonly string $slateSurface,
        public readonly string $slateDeep,
        public readonly string $success,
        public readonly string $danger,
        public readonly string $warn,
        public readonly string $accentRgb,
        public readonly string $slateBaseRgb,
        public readonly string $bodyGradient,
        public readonly string $ambientGlow1,
        public readonly string $ambientGlow2,
        public readonly string $fontFamily,
        public readonly string $extraCss = '',
    ) {
    }

    public function cssVariables(): string
    {
        $vars = [
            '--accent-primary' => $this->accentPrimary,
            '--accent-glow' => $this->accentGlow,
            '--accent-dim' => $this->accentDim,
            '--slate-base' => $this->slateBase,
            '--slate-surface' => $this->slateSurface,
            '--slate-deep' => $this->slateDeep,
            '--success' => $this->success,
            '--danger' => $this->danger,
            '--warn' => $this->warn,
            '--accent-rgb' => $this->accentRgb,
            '--slate-base-rgb' => $this->slateBaseRgb,
            '--body-gradient' => $this->bodyGradient,
            '--ambient-glow-1' => $this->ambientGlow1,
            '--ambient-glow-2' => $this->ambientGlow2,
            '--font-family' => $this->fontFamily,
        ];
        if ($this->accentSecondary !== null) {
            $vars['--accent-secondary'] = $this->accentSecondary;
        }
        if ($this->accentSecondaryRgb !== null) {
            $vars['--accent-secondary-rgb'] = $this->accentSecondaryRgb;
        }
        $out = '';
        foreach ($vars as $k => $v) {
            $out .= "$k:$v;";
        }
        return $out;
    }
}
