<?php

namespace App\Theme;

class AquarelleTheme extends Theme
{
    public function __construct()
    {
        $extraCss = <<<CSS
[data-theme="aquarelle"] .sidebar {
    background: linear-gradient(180deg, rgba(245,235,222,0.92), rgba(231,222,210,0.94));
    border-right: 1px solid rgba(120,90,60,0.18);
    box-shadow: 4px 0 30px rgba(120,90,60,0.12), inset -1px 0 0 rgba(255,255,255,0.4);
    color: #3A2F1F;
}
[data-theme="aquarelle"] .sidebar-orb {
    background: linear-gradient(135deg, rgba(194,116,90,0.6), rgba(154,182,140,0.5));
    border: 1px solid rgba(120,90,60,0.4);
    box-shadow: 0 0 12px rgba(120,90,60,0.15), inset 0 1px 0 rgba(255,255,255,0.4);
}
[data-theme="aquarelle"] .sidebar-orb svg path { color: #FFF8EE; }
[data-theme="aquarelle"] .sidebar-brand {
    color: #3A2F1F !important;
    text-shadow: 0 1px 0 rgba(255,255,255,0.4);
}
[data-theme="aquarelle"] .sidebar-item {
    color: rgba(58,47,31,0.7);
    border-left: 2px solid transparent;
    border-radius: 0;
}
[data-theme="aquarelle"] .sidebar-item:hover {
    background: rgba(194,116,90,0.12);
    color: #5C3A1F;
}
[data-theme="aquarelle"] .sidebar-item.active {
    background: linear-gradient(90deg, rgba(194,116,90,0.25), transparent 70%);
    border-left-color: #C2745A;
    color: #3A2F1F;
    font-weight: 600;
}
[data-theme="aquarelle"] .sidebar-divider {
    background: linear-gradient(90deg, transparent, rgba(120,90,60,0.25), transparent);
    height: 1px;
}
[data-theme="aquarelle"] .sidebar-section-label {
    color: rgba(120,90,60,0.55);
    font-size: 10px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}
[data-theme="aquarelle"] .sidebar-item svg {
    color: rgba(120,90,60,0.7);
}
[data-theme="aquarelle"] .sidebar-item.active svg {
    color: #C2745A;
}
[data-theme="aquarelle"] .sidebar-signout {
    color: #B04848 !important;
}
[data-theme="aquarelle"] .text-white\/90, [data-theme="aquarelle"] .text-white\/85,
[data-theme="aquarelle"] .text-white\/80, [data-theme="aquarelle"] .text-white\/70,
[data-theme="aquarelle"] .text-white\/60, [data-theme="aquarelle"] .text-white\/55,
[data-theme="aquarelle"] .text-white\/50 {
    color: #3A2F1F !important;
}
[data-theme="aquarelle"] .text-white\/45 { color: rgba(58,47,31,0.7) !important; }
[data-theme="aquarelle"] .text-white\/40 { color: rgba(58,47,31,0.65) !important; }
[data-theme="aquarelle"] .text-white\/35 { color: rgba(58,47,31,0.6) !important; }
[data-theme="aquarelle"] .text-white\/30 { color: rgba(58,47,31,0.5) !important; }
[data-theme="aquarelle"] .text-white\/25 { color: rgba(58,47,31,0.4) !important; }
[data-theme="aquarelle"] .text-white\/20 { color: rgba(58,47,31,0.35) !important; }
[data-theme="aquarelle"] .text-white\/15 { color: rgba(58,47,31,0.3) !important; }
[data-theme="aquarelle"] .text-white\/10 { color: rgba(58,47,31,0.25) !important; }
[data-theme="aquarelle"] .glass {
    background: rgba(255,250,240,0.55) !important;
    border: 1px solid rgba(120,90,60,0.18) !important;
    color: #3A2F1F !important;
}
[data-theme="aquarelle"] .glass-light {
    background: rgba(255,250,240,0.4) !important;
    border: 1px solid rgba(120,90,60,0.12) !important;
    color: #3A2F1F !important;
}
[data-theme="aquarelle"] .glass-panel {
    background: rgba(255,250,240,0.5) !important;
    border: 1px solid rgba(120,90,60,0.15) !important;
    color: #3A2F1F !important;
}
[data-theme="aquarelle"] .form-input {
    background: rgba(255,250,240,0.6) !important;
    border: 1px solid rgba(120,90,60,0.2) !important;
    color: #3A2F1F !important;
}
[data-theme="aquarelle"] .form-input::placeholder { color: rgba(58,47,31,0.4); }
[data-theme="aquarelle"] .aero-btn {
    background: linear-gradient(180deg, rgba(255,250,240,0.7), rgba(255,250,240,0.4)) !important;
    color: #3A2F1F !important;
    border: 1px solid rgba(120,90,60,0.25) !important;
}
[data-theme="aquarelle"] .aero-btn-primary {
    background: linear-gradient(180deg, rgba(194,116,90,0.4), rgba(194,116,90,0.18)) !important;
    color: #5C2A14 !important;
    border: 1px solid rgba(194,116,90,0.45) !important;
}
[data-theme="aquarelle"] .progress-bar-track {
    background: rgba(120,90,60,0.12) !important;
    border: 1px solid rgba(120,90,60,0.2);
}
CSS;

        parent::__construct(
            id: 'aquarelle',
            displayName: 'Aquarelle',
            description: 'Watercolor paper. Warm earth tones, hand-painted side menu.',
            accentPrimary: '#C2745A',
            accentGlow: '#D89B7E',
            accentDim: '#9AB68C',
            accentSecondary: '#9AB68C',
            accentSecondaryRgb: '154,182,140',
            slateBase: '#F5EBDE',
            slateSurface: '#E7DED2',
            slateDeep: '#D8C9B6',
            success: '#6B8E5A',
            danger: '#B04848',
            warn: '#C28B3A',
            accentRgb: '194,116,90',
            slateBaseRgb: '245,235,222',
            bodyGradient: 'linear-gradient(180deg, #F5EBDE 0%, #E7DED2 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(194,116,90,0.18), transparent 50%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(154,182,140,0.16), transparent 45%)',
            fontFamily: '"Georgia", "Iowan Old Style", "Palatino", serif',
            extraCss: $extraCss,
            layout: 'sidebar',
        );
    }
}
