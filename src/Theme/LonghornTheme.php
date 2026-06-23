<?php

namespace App\Theme;

class LonghornTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            id: 'longhorn',
            displayName: 'Longhorn',
            description: 'Dark slate with aero blue glass. The default FileShareZ aesthetic.',
            accentPrimary: '#6CB2FF',
            accentGlow: '#8EC5FF',
            accentDim: '#3A7BD5',
            accentSecondary: '#A78BFA',
            accentSecondaryRgb: '167,139,250',
            slateBase: '#1B1E24',
            slateSurface: '#2A2F38',
            slateDeep: '#101217',
            success: '#10B981',
            danger: '#EF4444',
            warn: '#F59E0B',
            accentRgb: '108,178,255',
            slateBaseRgb: '27,30,36',
            bodyGradient: 'linear-gradient(180deg, #1B1E24 0%, #101217 100%)',
            ambientGlow1: 'radial-gradient(circle at top left, rgba(120,170,255,0.12), transparent 35%)',
            ambientGlow2: 'radial-gradient(circle at bottom right, rgba(255,255,255,0.04), transparent 25%)',
            fontFamily: '"Segoe UI", Inter, Geist, system-ui, sans-serif',
        );
    }
}
