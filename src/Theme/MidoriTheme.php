<?php

namespace App\Theme;

class MidoriTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            id: 'midori',
            displayName: 'Midori',
            description: 'Forest ink. Matcha green on a deep moss base.',
            accentPrimary: '#A8D870',
            accentGlow: '#C7E89B',
            accentDim: '#7AC74F',
            accentSecondary: '#4ADE80',
            accentSecondaryRgb: '74,222,128',
            slateBase: '#1A1E1B',
            slateSurface: '#2A3028',
            slateDeep: '#0E110E',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '168,216,112',
            slateBaseRgb: '26,30,27',
            bodyGradient: 'linear-gradient(180deg, #1A1E1B 0%, #0E110E 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(168,216,112,0.14), transparent 40%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(122,199,79,0.06), transparent 25%)',
            fontFamily: '"Segoe UI", Inter, Geist, system-ui, sans-serif',
        );
    }
}
