<?php

namespace App\Theme;

class CrtTheme extends AbstractTheme
{
    public function __construct()
    {
        $extraCss = <<<CSS
[data-theme="crt"] body::after {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 9999;
    background: repeating-linear-gradient(
        0deg,
        rgba(74,222,128,0.04) 0px,
        rgba(74,222,128,0.04) 1px,
        transparent 1px,
        transparent 3px
    );
    mix-blend-mode: screen;
}
[data-theme="crt"] body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 9998;
    background: radial-gradient(ellipse at center, transparent 50%, rgba(0,0,0,0.45) 100%);
}
@keyframes crt-flicker {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.985; }
}
[data-theme="crt"] body {
    animation: crt-flicker 0.15s steps(2) infinite;
}
[data-theme="crt"] .glass,
[data-theme="crt"] .glass-light,
[data-theme="crt"] .glass-panel,
[data-theme="crt"] .glass-heavy {
    background: rgba(0, 20, 0, 0.85) !important;
    backdrop-filter: none !important;
    border: 1px solid rgba(74, 222, 128, 0.4) !important;
    box-shadow: 0 0 14px rgba(74, 222, 128, 0.1) inset, 0 0 24px rgba(74, 222, 128, 0.06) !important;
    border-radius: 2px !important;
}
[data-theme="crt"] .aero-btn-primary {
    background: rgba(74, 222, 128, 0.2) !important;
    color: #4ADE80 !important;
    border: 1px solid #4ADE80 !important;
    box-shadow: 0 0 12px rgba(74, 222, 128, 0.4) !important;
    border-radius: 2px !important;
}
[data-theme="crt"] .aero-btn {
    background: transparent !important;
    color: #4ADE80 !important;
    border: 1px solid rgba(74, 222, 128, 0.4) !important;
    border-radius: 2px !important;
}
[data-theme="crt"] .progress-bar-fill {
    background: #4ADE80 !important;
    box-shadow: 0 0 8px rgba(74, 222, 128, 0.7);
}
[data-theme="crt"] .progress-bar-track {
    background: rgba(74, 222, 128, 0.08) !important;
    border: 1px solid rgba(74, 222, 128, 0.2);
}
[data-theme="crt"] .form-input {
    background: rgba(0, 20, 0, 0.6) !important;
    border: 1px solid rgba(74, 222, 128, 0.3) !important;
    color: #4ADE80 !important;
    border-radius: 2px !important;
}
[data-theme="crt"] .form-input:focus {
    border-color: #4ADE80 !important;
    box-shadow: 0 0 0 2px rgba(74, 222, 128, 0.2) !important;
}
[data-theme="crt"] .window-header {
    background: rgba(74, 222, 128, 0.08) !important;
    border-bottom: 1px solid rgba(74, 222, 128, 0.3) !important;
}
[data-theme="crt"] .text-white\\/90,
[data-theme="crt"] .text-white\\/85,
[data-theme="crt"] .text-white\\/80,
[data-theme="crt"] .text-white\\/70,
[data-theme="crt"] .text-white\\/60,
[data-theme="crt"] .text-white\\/55,
[data-theme="crt"] .text-white\\/50,
[data-theme="crt"] .text-white\\/45,
[data-theme="crt"] .text-white\\/40,
[data-theme="crt"] .text-white\\/35,
[data-theme="crt"] .text-white\\/30,
[data-theme="crt"] .text-white\\/25,
[data-theme="crt"] .text-white\\/20,
[data-theme="crt"] .text-white\\/15,
[data-theme="crt"] .text-white\\/10,
[data-theme="crt"] .text-white\\/8,
[data-theme="crt"] .text-white\\/5,
[data-theme="crt"] .text-white\\/3 {
    color: rgba(74, 222, 128, var(--tw-text-opacity, 1)) !important;
}
CSS;

        parent::__construct(
            id: 'crt',
            displayName: 'CRT Terminal',
            description: 'Phosphor green on deep black with scanlines. Monospace hacker aesthetic.',
            accentPrimary: '#4ADE80',
            accentGlow: '#22D3EE',
            accentDim: '#15803D',
            accentSecondary: '#A78BFA',
            accentSecondaryRgb: '167,139,250',
            slateBase: '#08090B',
            slateSurface: '#0E1014',
            slateDeep: '#020203',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '74,222,128',
            slateBaseRgb: '8,9,11',
            bodyGradient: 'linear-gradient(180deg, #08090B 0%, #020203 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(74,222,128,0.12), transparent 40%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(34,211,238,0.05), transparent 25%)',
            fontFamily: '"Fira Code", "JetBrains Mono", "Cascadia Code", "Source Code Pro", Consolas, Monaco, monospace',
            extraCss: $extraCss,
        );
    }
}
