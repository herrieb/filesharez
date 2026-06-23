<?php

namespace App\Theme;

class AuroraTheme extends AbstractTheme
{
    public function __construct()
    {
        $extraCss = <<<CSS
@keyframes aurora-drift-1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(80px, -50px) scale(1.1); }
    66% { transform: translate(-40px, 60px) scale(0.95); }
}
@keyframes aurora-drift-2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-70px, -30px) scale(1.15); }
}
@keyframes aurora-shimmer {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
[data-theme="aurora"] .glass,
[data-theme="aurora"] .glass-light,
[data-theme="aurora"] .glass-panel {
    background: linear-gradient(135deg, rgba(124,58,237,0.10), rgba(34,211,238,0.06)) !important;
    border: 1px solid rgba(124,58,237,0.25) !important;
    box-shadow: 0 0 24px rgba(124,58,237,0.1) inset, 0 8px 32px rgba(0,0,0,0.4) !important;
}
[data-theme="aurora"] .aero-btn-primary {
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-glow)) !important;
    background-size: 200% 200% !important;
    animation: aurora-shimmer 6s ease infinite !important;
    color: #fff !important;
    border: 1px solid rgba(124,58,237,0.3) !important;
    box-shadow: 0 0 18px rgba(124,58,237,0.4) !important;
}
[data-theme="aurora"] .progress-bar-fill {
    background: linear-gradient(90deg, var(--accent-primary), var(--accent-glow), var(--accent-primary)) !important;
    background-size: 200% 100% !important;
    animation: aurora-shimmer 4s ease infinite !important;
}
[data-theme="aurora"] .glow-1,
[data-theme="aurora"] .ambient-glow.glow-1 {
    animation: aurora-drift-1 25s ease-in-out infinite !important;
    background: radial-gradient(circle, rgba(124,58,237,0.18), transparent 60%) !important;
}
[data-theme="aurora"] .glow-2,
[data-theme="aurora"] .ambient-glow.glow-2 {
    animation: aurora-drift-2 30s ease-in-out infinite !important;
    background: radial-gradient(circle, rgba(34,211,238,0.12), transparent 60%) !important;
}
CSS;

        parent::__construct(
            id: 'aurora',
            displayName: 'Aurora',
            description: 'Cold sky. Violet-cyan gradient accent with drifting aurora blobs.',
            accentPrimary: '#7C3AED',
            accentGlow: '#22D3EE',
            accentDim: '#5B21B6',
            accentSecondary: '#F472B6',
            accentSecondaryRgb: '244,114,182',
            slateBase: '#0B1226',
            slateSurface: '#161E3A',
            slateDeep: '#04081A',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '124,58,237',
            slateBaseRgb: '11,18,38',
            bodyGradient: 'linear-gradient(180deg, #0B1226 0%, #04081A 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(124,58,237,0.20), transparent 45%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(34,211,238,0.12), transparent 35%)',
            fontFamily: '"Segoe UI", Inter, Geist, system-ui, sans-serif',
            extraCss: $extraCss,
        );
    }
}
