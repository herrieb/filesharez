<?php

namespace App\Theme;

class RoseQuartzTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            id: 'rose-gold',
            displayName: 'Rosé Quartz',
            description: 'Soft luxury. Dusty rose on a deep aubergine base.',
            accentPrimary: '#F2A0B2',
            accentGlow: '#F8C4D0',
            accentDim: '#D08AA0',
            accentSecondary: '#C4A0F2',
            accentSecondaryRgb: '196,160,242',
            slateBase: '#1E1320',
            slateSurface: '#2E1F30',
            slateDeep: '#120A14',
            success: '#4ADE80',
            danger: '#F87171',
            warn: '#FBBF24',
            accentRgb: '242,160,178',
            slateBaseRgb: '30,19,32',
            bodyGradient: 'linear-gradient(180deg, #1E1320 0%, #120A14 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(242,160,178,0.16), transparent 40%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(208,138,160,0.06), transparent 25%)',
            fontFamily: '"Segoe UI", Inter, Geist, system-ui, sans-serif',
        );
    }
}
