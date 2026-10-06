# ProTor design system

Files: `public/css/protor/design-system.css` (tokens, components, skin; loads last) and
`public/css/protor/theme.css` (legacy structure). Blade components live in `resources/views/components/pt/`.

## Principles
- **Readable first**: body 15px, line-height 1.55, muted text never below 4.5:1 contrast. Dark mode is high contrast.
- **Light = white + blush peach, dark = high-contrast aubergine.** One accent (`--ds-accent`, orchid purple); stage colours are the only other hues. One typeface (Plus Jakarta Sans) for everything, codes included.
- **No hard-coded colours in components.** Everything reads a token; change a token, change the product.
- **Bento layout**: a 12-column grid (`.pt-bento`), tiles span 2 / 4 columns (and 2 rows for the hero) and reflow at 1100px and 640px.

## Tokens (`:root` / `.dark`)
| Group | Tokens |
|---|---|
| Surface | `--ds-bg`, `--ds-surface`, `--ds-surface-2`, `--ds-surface-3`, `--ds-tint` |
| Lines | `--ds-border`, `--ds-border-strong`, `--ds-track` |
| Text | `--ds-text`, `--ds-text-muted`, `--ds-text-faint` |
| Accent | `--ds-accent`, `--ds-accent-hover`, `--ds-accent-soft`, `--ds-on-accent`, `--ds-focus` |
| Status | `--ds-success`, `--ds-warning`, `--ds-danger`, `--ds-critical`, `--ds-info` |
| Stages | `--ds-stage-1` Pre-Design (peach), `--ds-stage-2` Design (purple), `--ds-stage-3` Post-Design (pink) |
| Scale | `--ds-text-xs…3xl`, `--ds-space-1…8` (4px base), `--ds-radius-sm/md/lg` |

## Components
| Blade | Purpose | Props |
|---|---|---|
| `<x-pt.tile>` | Bento tile (stat, hero, alert) | `tone` neutral/brand/s1/s2/s3/alert/good · `size` sm/md/lg · `as` div/button/a · `label` `hint` `code` `click` · slots `visual`, `footer` |
| `<x-pt.panel>` | Titled surface for widgets | `eyebrow` `title` · slot `actions` |
| `<x-pt.badge>` | Status pill | `tone` success/warning/danger/critical/info/muted/primary |
| `<x-pt.progress-ring>` | Circular progress | `value` `size` `tone` `label` |

CSS-only primitives: `.pt-chip`, `.pt-bar` (`--xs`, `--lg`), `.pt-avatar`, `.pt-code`, `.pt-status`.

## Adding something new
1. Need a colour? Add a token to both `:root` and `.dark`.
2. Need a new card? Compose `<x-pt.panel>` / `<x-pt.tile>` first; only add CSS if those can't express it.
3. Check both themes and the 1100px / 640px breakpoints.
