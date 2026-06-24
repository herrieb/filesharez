<?php

namespace App\Theme;

class BrutalistTheme extends Theme
{
    public function __construct()
    {
        $extraCss = <<<CSS
[data-theme="brutalist"] .sidebar {
    background: #F5F1E8;
    border-right: 4px solid #000;
    box-shadow: 8px 0 0 #000;
    color: #000;
}
[data-theme="brutalist"] .sidebar-orb {
    background: #FFE600;
    border: 3px solid #000;
    border-radius: 0;
    box-shadow: 6px 6px 0 #000;
}
[data-theme="brutalist"] .sidebar-orb svg path { color: #000; }
[data-theme="brutalist"] .sidebar-brand {
    color: #000 !important;
    font-weight: 900;
    letter-spacing: -0.02em;
    text-transform: uppercase;
}
[data-theme="brutalist"] .sidebar-item {
    color: #000;
    border-left: 6px solid transparent;
    border-radius: 0;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 12px;
}
[data-theme="brutalist"] .sidebar-item:hover {
    background: #FFE600;
    color: #000;
    transform: translate(4px, 0);
}
[data-theme="brutalist"] .sidebar-item.active {
    background: #FF3366;
    color: #FFF;
    border-left-color: #000;
    border-top: 3px solid #000;
    border-bottom: 3px solid #000;
    font-weight: 900;
    box-shadow: inset -6px 0 0 #000;
}
[data-theme="brutalist"] .sidebar-item.active svg { color: #FFF; }
[data-theme="brutalist"] .sidebar-divider {
    background: #000;
    height: 3px;
}
[data-theme="brutalist"] .sidebar-section-label {
    color: #000 !important;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    background: #000;
    color: #FFE600 !important;
    padding: 4px 8px;
    display: inline-block;
}
[data-theme="brutalist"] .sidebar-item svg { color: #000; }
[data-theme="brutalist"] .sidebar-signout {
    background: #FF3366;
    color: #FFF !important;
    border-top: 3px solid #000;
    border-bottom: 3px solid #000;
    font-weight: 900 !important;
}
[data-theme="brutalist"] .text-white\\/90, [data-theme="brutalist"] .text-white\\/85,
[data-theme="brutalist"] .text-white\\/80, [data-theme="brutalist"] .text-white\\/70,
[data-theme="brutalist"] .text-white\\/60, [data-theme="brutalist"] .text-white\\/55,
[data-theme="brutalist"] .text-white\\/50, [data-theme="brutalist"] .text-white\\/45,
[data-theme="brutalist"] .text-white\\/40, [data-theme="brutalist"] .text-white\\/35,
[data-theme="brutalist"] .text-white\\/30, [data-theme="brutalist"] .text-white\\/25,
[data-theme="brutalist"] .text-white\\/20, [data-theme="brutalist"] .text-white\\/15,
[data-theme="brutalist"] .text-white\\/10 {
    color: #000 !important;
}
[data-theme="brutalist"] .glass, [data-theme="brutalist"] .glass-light, [data-theme="brutalist"] .glass-panel {
    background: #FFF !important;
    border: 3px solid #000 !important;
    border-radius: 0 !important;
    box-shadow: 6px 6px 0 #000 !important;
    color: #000 !important;
    backdrop-filter: none !important;
}
[data-theme="brutalist"] .window-header {
    background: #FFE600 !important;
    border-bottom: 3px solid #000 !important;
    border-radius: 0 !important;
    color: #000 !important;
}
[data-theme="brutalist"] .form-input {
    background: #FFF !important;
    border: 3px solid #000 !important;
    border-radius: 0 !important;
    color: #000 !important;
    box-shadow: 4px 4px 0 #000;
}
[data-theme="brutalist"] .form-input::placeholder { color: rgba(0,0,0,0.4); }
[data-theme="brutalist"] .aero-btn, [data-theme="brutalist"] .aero-btn-primary {
    background: #FFE600 !important;
    color: #000 !important;
    border: 3px solid #000 !important;
    border-radius: 0 !important;
    box-shadow: 5px 5px 0 #000 !important;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.04em;
}
[data-theme="brutalist"] .aero-btn:hover, [data-theme="brutalist"] .aero-btn-primary:hover {
    background: #FF3366 !important;
    color: #FFF !important;
    transform: translate(2px, 2px);
    box-shadow: 3px 3px 0 #000 !important;
}
[data-theme="brutalist"] .aero-btn-primary {
    background: #FF3366 !important;
    color: #FFF !important;
}
[data-theme="brutalist"] .progress-bar-track {
    background: #FFF !important;
    border: 3px solid #000 !important;
    border-radius: 0 !important;
    height: 14px;
}
[data-theme="brutalist"] .progress-bar-fill {
    background: #FF3366 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}
[data-theme="brutalist"] .theme-card {
    background: #FFF !important;
    border: 3px solid #000 !important;
    border-radius: 0 !important;
    box-shadow: 4px 4px 0 #000;
}
[data-theme="brutalist"] .theme-card.active {
    background: #FFE600 !important;
    border-color: #000 !important;
    box-shadow: 4px 4px 0 #FF3366 !important;
}
[data-theme="brutalist"] .status-badge { border-radius: 0 !important; font-weight: 700; }
[data-theme="brutalist"] h1, [data-theme="brutalist"] h2, [data-theme="brutalist"] h3 { font-weight: 900; letter-spacing: -0.01em; }
CSS;

        parent::__construct(
            id: 'brutalist',
            displayName: 'Brutalist',
            description: 'Yellow on black. Hard borders, no rounded corners, side menu in a sidebar that punches out of the page.',
            accentPrimary: '#FFE600',
            accentGlow: '#FF3366',
            accentDim: '#A0A0A0',
            accentSecondary: '#FF3366',
            accentSecondaryRgb: '255,51,102',
            slateBase: '#F5F1E8',
            slateSurface: '#FFF',
            slateDeep: '#FFFFFF',
            success: '#10B981',
            danger: '#FF3366',
            warn: '#FFE600',
            accentRgb: '255,230,0',
            slateBaseRgb: '245,241,232',
            bodyGradient: 'linear-gradient(180deg, #F5F1E8 0%, #FFFFFF 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(255,230,0,0.18), transparent 45%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(255,51,102,0.10), transparent 40%)',
            fontFamily: '"Inter", "Helvetica Neue", system-ui, sans-serif',
            extraCss: $extraCss,
            layout: 'sidebar',
        );
    }
}
