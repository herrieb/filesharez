<?php

namespace App\Theme;

class SunsetTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            id: 'sunset',
            displayName: 'Sunset Mesa',
            description: 'Warm desert dusk. Ember orange on a charred brown base.',
            accentPrimary: '#FF8A4C',
            accentGlow: '#FFB07A',
            accentDim: '#C25A2A',
            accentSecondary: '#F87171',
            accentSecondaryRgb: '248,113,113',
            slateBase: '#2A1F18',
            slateSurface: '#3A2B22',
            slateDeep: '#1A110A',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '255,138,76',
            slateBaseRgb: '42,31,24',
            bodyGradient: 'linear-gradient(180deg, #2A1F18 0%, #1A110A 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(255,138,76,0.18), transparent 40%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(255,176,122,0.08), transparent 25%)',
            fontFamily: '"Segoe UI", Inter, Geist, system-ui, sans-serif',
        );
    }
}
