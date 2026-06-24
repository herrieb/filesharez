<?php

namespace App\Theme;

class TokyoTheme extends Theme
{
    public function __construct()
    {
        $extraCss = <<<CSS
[data-theme="tokyo"] .sidebar {
    background: linear-gradient(180deg, rgba(15,17,32,0.78), rgba(20,24,46,0.85));
    border-right: 1px solid rgba(255,82,168,0.18);
    box-shadow: 4px 0 28px rgba(0,0,0,0.4), inset -1px 0 0 rgba(255,255,255,0.04);
}
[data-theme="tokyo"] .sidebar-orb {
    background: linear-gradient(135deg, rgba(255,82,168,0.32), rgba(82,168,255,0.22));
    border: 1px solid rgba(255,82,168,0.32);
    box-shadow: 0 0 18px rgba(255,82,168,0.3), inset 0 1px 0 rgba(255,255,255,0.12);
}
[data-theme="tokyo"] .sidebar-item {
    color: rgba(255,255,255,0.55);
    border-left: 2px solid transparent;
    border-radius: 0;
}
[data-theme="tokyo"] .sidebar-item:hover {
    background: rgba(255,82,168,0.06);
    color: #FF9DC9;
}
[data-theme="tokyo"] .sidebar-item.active {
    background: linear-gradient(90deg, rgba(255,82,168,0.18), transparent 70%);
    border-left-color: #FF52A8;
    color: #FF52A8;
    box-shadow: inset 2px 0 14px rgba(255,82,168,0.18);
}
[data-theme="tokyo"] .sidebar-divider {
    background: linear-gradient(90deg, transparent, rgba(255,82,168,0.25), transparent);
    height: 1px;
}
[data-theme="tokyo"] .sidebar-section-label {
    color: rgba(255,82,168,0.55);
    font-size: 10px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}
CSS;

        parent::__construct(
            id: 'tokyo',
            displayName: 'Tokyo',
            description: 'Neon-lit side menu. Magenta on midnight, synthwave city nights.',
            accentPrimary: '#FF52A8',
            accentGlow: '#FF9DC9',
            accentDim: '#A82B6A',
            accentSecondary: '#52A8FF',
            accentSecondaryRgb: '82,168,255',
            slateBase: '#0F1120',
            slateSurface: '#181A2E',
            slateDeep: '#080A18',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '255,82,168',
            slateBaseRgb: '15,17,32',
            bodyGradient: 'linear-gradient(180deg, #0F1120 0%, #080A18 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(255,82,168,0.20), transparent 45%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(82,168,255,0.14), transparent 40%)',
            fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif',
            extraCss: $extraCss,
            layout: 'sidebar',
        );
    }
}
