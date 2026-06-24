# Theming System

9 built-in themes + the ability to upload your own. The system is theme-aware end-to-end: every color, font, and animation keyframe in the app is driven by CSS variables set by the active theme.

## How a theme is selected

1. The `users.theme` column stores the theme id (default `'longhorn'`)
2. On every request, `ThemeResolverListener` (kernel.request, priority 5) reads the user from the security token, looks up the theme in `ThemeRegistry`, and stores the theme object as `request.attributes._theme` plus the id as `_theme_id`
3. Public pages (no logged-in user) get the default theme from `app.theme_default` env var
4. `templates/base.html.twig` renders the entire CSS variable block from `theme_registry.cssBlock()` and applies them to the `<body>` (via `data-theme` attribute + inline `style`)

## CSS variable system

Every theme defines these tokens:

```css
:root, [data-theme="longhorn"] {
    --accent-primary: #6CB2FF;
    --accent-glow: #8EC5FF;
    --accent-dim: #3A7BD5;
    --accent-secondary: #A78BFA;
    --accent-secondary-rgb: 167,139,250;
    --slate-base: #1B1E24;
    --slate-surface: #2A2F38;
    --slate-deep: #101217;
    --success: #10B981;
    --danger: #EF4444;
    --warn: #F59E0B;
    --accent-rgb: 108,178,255;          /* for rgba() usage */
    --slate-base-rgb: 27,30,36;          /* for rgba() usage */
    --body-gradient: linear-gradient(180deg, #1B1E24 0%, #101217 100%);
    --ambient-glow-1: radial-gradient(circle at top left, rgba(120,170,255,0.12), transparent 35%);
    --ambient-glow-2: radial-gradient(circle at bottom right, rgba(255,255,255,0.04), transparent 25%);
    --font-family: "Segoe UI", Inter, Geist, system-ui, sans-serif;
}
```

Other themes override these on `[data-theme="<id>"]` selectors. The CRT, Aurora, Tokyo, Aquarelle, and Brutalist themes also inject `extraCss` — raw CSS that adds theme-specific keyframes, scanline overlays, font overrides, etc.

## The 9 built-in themes

| ID | Name | Layout | Accent | Feel |
|---|---|---|---|---|
| `longhorn` | **Longhorn** | taskbar | `#6CB2FF` (aero blue) | Dark slate, glass, default. |
| `sunset` | **Sunset Mesa** | taskbar | `#FF8A4C` (ember) | Warm desert dusk, dark brown base. |
| `midori` | **Midori** | taskbar | `#A8D870` (matcha) | Forest green, dark moss base. |
| `rose-gold` | **Rosé Quartz** | taskbar | `#F2A0B2` (dusty rose) | Soft luxury, aubergine base. |
| `crt` | **CRT Terminal** | taskbar | `#4ADE80` (phosphor green) | Black + green, monospace, scanlines, body flicker. |
| `aurora` | **Aurora** | taskbar | `#7C3AED` (violet → cyan gradient) | Cold sky, drifting ambient blobs. |
| `tokyo` | **Tokyo** | **sidebar** | `#FF52A8` (magenta) | Neon synthwave, dark navy glass sidebar. |
| `aquarelle` | **Aquarelle** | **sidebar** | `#C2745A` (terracotta) | Watercolor paper, serif font, warm. |
| `brutalist` | **Brutalist** | **sidebar** | `#FFE600` (yellow on black) | Hard 3px borders, no rounded corners, 6px hard shadows. |

The 6 longhorn/sunset/midori/rose-gold/crt/aurora themes use the **taskbar** layout (floating bottom bar in the desktop app shell). The 3 tokyo/aquarelle/brutalist themes use the **sidebar** layout (260px sticky left sidebar on desktop).

## Layout modes

Each theme declares its `layout` field (`taskbar` or `sidebar`). `templates/app_layout.html.twig` reads the theme's layout and renders the matching navigation:

- **taskbar layout:** the floating bottom bar (`/templates/app_layout.html.twig:120`) plus the mobile tab bar
- **sidebar layout:** a 260px sticky `<aside>` on the left with brand, section, items, and signout

The mobile tab bar and "More" sheet always render, so sidebar themes still work on phones.

## Custom themes

Users can upload a zip file that contains:

- `theme.xml` — the descriptor (creator, createdAt, all color tokens, fontFamily, bodyGradient, etc.)
- `assets/` (optional) — `styles.css` for extra CSS, fonts, preview images, anything

The zip is uploaded via `/account/profile/theme/upload`. `App\Theme\ThemeStore::installFromZip` validates, extracts to `var/themes/<id>/`, and registers a new `Theme` object with the registry.

The theme is then downloadable as a zip from `/account/profile/theme/{id}/download` — same `theme.xml` schema, plus any assets. The current built-ins' zips are produced by `ThemeStore::buildZip`.

### `theme.xml` schema

Required fields:

```xml
<theme>
    <id>my-custom-theme</id>
    <displayName>My Custom Theme</displayName>
    <description>A short user-facing description.</description>
    <creator>Author name</creator>
    <createdAt>2026-06-23T12:00:00+00:00</createdAt>
    <accentPrimary>#FF00FF</accentPrimary>
    <accentGlow>#FF66FF</accentGlow>
    <accentDim>#CC00CC</accentDim>
    <accentSecondary>#00FFFF</accentSecondary>
    <accentSecondaryRgb>0,255,255</accentSecondaryRgb>
    <slateBase>#202020</slateBase>
    <slateSurface>#303030</slateSurface>
    <slateDeep>#101010</slateDeep>
    <success>#00FF00</success>
    <danger>#FF0000</danger>
    <warn>#FFFF00</warn>
    <accentRgb>255,0,255</accentRgb>
    <slateBaseRgb>32,32,32</slateBaseRgb>
    <bodyGradient><![CDATA[linear-gradient(180deg, #202020 0%, #101010 100%)]]></bodyGradient>
    <ambientGlow1><![CDATA[radial-gradient(circle at top left, rgba(255,0,255,0.2), transparent 40%)]]></ambientGlow1>
    <ambientGlow2><![CDATA[radial-gradient(circle at bottom right, rgba(0,255,255,0.1), transparent 25%)]]></ambientGlow2>
    <fontFamily><![CDATA["Courier New", monospace]]></fontFamily>
    <layout>taskbar</layout>      <!-- taskbar | sidebar (optional, default taskbar) -->
</theme>
```

ID rules:
- Lowercase
- 2-42 characters
- Letters, digits, dashes only
- Must be unique across built-in + custom themes

The validator in `ThemeStore::installFromZip` rejects:
- Missing or malformed `theme.xml`
- Invalid id
- Duplicate id
- Missing required color fields
- Files that don't extract cleanly

## Components

### `src/Theme/AbstractTheme.php` — the value object

Defines the shape of a theme. Constructed as a readonly value object — instances are immutable.

### `src/Theme/Theme.php` — concrete (for runtime / user-uploaded)

`class Theme extends AbstractTheme {}` — empty subclass, used for user-uploaded themes that don't have a dedicated class.

### The 9 built-in theme classes

`LonghornTheme.php`, `SunsetTheme.php`, `MidoriTheme.php`, `RoseQuartzTheme.php`, `CrtTheme.php`, `AuroraTheme.php`, `TokyoTheme.php`, `AquarelleTheme.php`, `BrutalistTheme.php` — each extends `AbstractTheme` directly with hand-tuned color tokens and (for CRT/Aurora/Tokyo/Aquarelle/Brutalist) `extraCss` with theme-specific keyframes and overrides.

### `src/Theme/ThemeRegistry.php` — the lookup table

Holds the 9 built-ins + all custom themes loaded from `var/themes/`. Public methods:

- `default(): AbstractTheme` — the registry's default
- `all(): array<string, AbstractTheme>` — keyed by id
- `get(string $id): ?AbstractTheme`
- `resolveOrDefault(?string $id): AbstractTheme` — falls back to default
- `cssBlock(): string` — the `<style>` block that defines all variables
- `reloadFromDisk(): void` — re-scan `var/themes/`, used after install/delete

### `src/Theme/ThemeStore.php` — the filesystem + zip layer

- `buildZip(AbstractTheme $theme): string` — write a zip for any theme (built-in or custom)
- `installFromZip(string $zipPath, ?User $user = null): Theme` — extract + validate + register
- `loadFromDir(string $dir, string $id): Theme` — read a theme from disk
- `delete(string $id): void`
- `listCustomThemeDirs(): array<int, string>` — directories under `var/themes/`

### `src/EventListener/ThemeResolverListener.php`

Runs on `kernel.request` at priority 5 — after the security firewall has loaded the user but before the controller runs. Sets `request.attributes._theme` and `_theme_id`.

### `src/Twig/ThemeTwigExtension.php`

Twig extension that exposes `theme_color(theme, token)` and `theme_alpha(theme, token, alpha)` functions, plus the `theme_registry` global. Currently unused by the templates (they use the CSS variables directly) but available for places where you need a hex string at render time.

### `src/Controller/ThemeAssetController.php`

`GET /themes/{id}/{file}` — serves static assets from `var/themes/<id>/assets/`. The path-traversal guard uses `realpath()` + prefix check.

## How the user switches themes

From `/account/profile`, the user clicks a theme card. A `POST` to `/account/profile/theme` sets `$user->setTheme($themeId)` and flushes. The next request renders with the new theme.

From the URL: `POST /account/profile/theme` with `theme=<id>` form data. The controller redirects back to `/account/profile`.

## Where the CSS variables are read

Everywhere in `templates/`, but most directly:

- `templates/base.html.twig` — the `<style>` block that defines all global classes (`.glass`, `.aero-btn`, `.progress-bar-fill`, etc.) uses `var(--accent-primary)` etc.
- `templates/landing/index.html.twig` — the same, in the landing-page-specific `<style>` block
- `templates/transfer/upload.html.twig` — the QR code reader uses `getComputedStyle(document.documentElement).getPropertyValue('--accent-glow')` at runtime so the QR follows the theme
- `templates/library/_upload_modal.html.twig` — uses `--accent-*` variables in inline styles

## What the rebuild should keep

- The 9 built-in themes as-is (they're tested in production)
- The CSS variable contract — every theme must define all 14 tokens
- The `layout` field — adding new themes that use the sidebar pattern is a one-liner
- The `theme.xml` schema — it's a stable contract
- The registry's `reloadFromDisk()` after install/delete

## What the rebuild should not do

- Don't inline hex colors in templates. Always use `var(--accent-primary)` or the appropriate token
- Don't add theme-specific logic in controllers — that's the registry's job
- Don't bypass `ThemeResolverListener` — every page that needs a theme should rely on the `_theme` request attribute
- Don't hardcode `data-theme` values in JS — read the CSS variable at runtime
