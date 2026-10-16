# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repo state

Not a git repo. No build system, package manager, tests, or linter. Whole project = one self-contained static file: `Nexova IT Solutions.html` (marketing site for Nexova IT Solutions). Filename has spaces — quote paths in shell.

View: open the file in a browser (`open "Nexova IT Solutions.html"`). Only external dependency is Google Fonts (Plus Jakarta Sans, Source Serif 4) via `<link>`.

## Structure of the HTML

- **Head/CSS**: one inline `<style>` block. Design tokens in `:root` (`--navy`, `--sky`, `--ink`, `--soft`, etc.). Light-only (`color-scheme:light`). Honors `env(safe-area-inset-*)` and `prefers-reduced-motion`.
- **Sections** (anchor ids): sticky `header` → hero (`#top`) → `#services` → `#products` (tabbed showcase) → `#process` → `#about` → unnamed section → `#contact` → `footer`.
- **One inline `<script>` at end**, no libraries:
  - `products` array is the single source of truth for the product showcase. Each entry: `id`, optional `url`, `mark`/`markBg`/`markClass` (fallback badge), optional `logo` (base64 data URI), `nameHtml`/`name`, `cat`, `desc`, `f` (feature list). Entries without `url` (`pmo`, `clinic`) have no external link and open the in-page demo flow instead.
  - From that array JS builds: the tab buttons in `#tabs`, the detail panel `#pdetail` (`show(i)`, ARIA tabs with arrow-key nav), footer product links `#foot-products`, and `#f-service` dropdown options (`Product demo: <name>`).
  - Clicking a `[data-p]` link in the detail panel preselects that product in the contact form's service dropdown.
  - Contact form `#contact-form` is preview-only: submit is `preventDefault`ed, validates name + email, shows a "Preview mode" message in `#form-msg`. Not wired to any backend.

## Editing notes

- Adding/changing a product: edit the `products` array only; tabs, footer, and form dropdown follow automatically.
- File is ~300 KB because product logos are inline base64 data URIs inside `products`. Lines are very long — use targeted `grep -o`/`cut -c` or Edit with small unique strings rather than dumping the file.
- Dynamic content is built via `innerHTML`; user-facing strings from `products` go through `esc()` (escapes `&` and `<` only).
