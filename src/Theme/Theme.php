<?php

namespace App\Theme;

/**
 * Concrete theme value object. The 6 built-in themes (Longhorn, Sunset, ...)
 * each have a dedicated subclass so they can have hand-tuned extra CSS, but
 * user-uploaded themes use this class directly. Both flavors are
 * interchangeable to the rest of the system.
 */
class Theme extends AbstractTheme
{
}
