# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Marketing site for Nexova (industry-first, problem-led copy, no product pages) with a small CMS, converted from a single static HTML page (kept untouched at `_orig/Nexova IT Solutions.html`). Slim 4 + Twig + SQLite, single admin user, deployed to Railway via `Dockerfile`. No tests or linter configured.

## Commands

```
composer install
cp .env.example .env              # then set ADMIN_EMAIL / ADMIN_PASSWORD_HASH
php bin/hash-password.php "pw"    # prints the argon2id hash for ADMIN_PASSWORD_HASH
php bin/seed.php                  # seed industries/steps/copy from database/seed/ (idempotent; bump COPY_REV in the script to overwrite page text, industries and steps in existing DBs once)
php bin/seed.php --force          # WIPES industries/steps and reseeds. Dev only, never on Railway.
composer start                    # php -S localhost:8080, router is public/index.php
```

In `.env`, the hash must be double-quoted with each `$` escaped as `\$` (dotenv would otherwise expand `$argon2id...`). On Railway paste it raw.

## Architecture

- **Single front controller** `public/index.php`: loads dotenv, starts the session, builds the PHP-DI container, registers Slim CSRF guard (persistent token mode) and all routes. Session save path is `$DATA_DIR/sessions`. CSRF token fields are exposed to every template as the Twig global `csrf`; all POST forms (public and admin) must include them.
- **Data dir** (`DATA_DIR`, default `./data`, `/data` on Railway) holds `app.db`, `uploads/` and `sessions/`. It must be a persistent volume in production, since the container filesystem is ephemeral. `Db::connect()` runs `database/schema.sql` (idempotent `CREATE IF NOT EXISTS`) on every connection, so adding a table means editing that file. There is no migration system, so changing an existing column needs a manual `ALTER`.
- **Uploads are not under `public/`**. They are served by `SiteController::upload` at `/uploads/{file}` with a content-type whitelist, `nosniff`, and a sandboxing CSP (matters for SVG). Admin accepts only PNG/JPG/WebP (validated with `getimagesizefromstring`, 2 MB); the seeded `pmo.svg` is the one trusted SVG.
- **Public pages**: `templates/base.twig` holds the head (title, description, canonical, Open Graph, JSON-LD), header, footer and cookie notice. `site.twig` is the home page, `industry.twig` is one page per industry at `/industries/{slug}` (slug derived from the industry title by `Content::slug`, no DB column), and `legal.twig` the legal pages. All extend `base`. `SiteController` builds the SEO data and also serves `/sitemap.xml` and `/robots.txt`. Copy comes from the `settings` table (`s.*`), lists from `industries`, `ai_services` and `process_steps`, all server-rendered. Each AI service shows `public/assets/ai/{slug}.svg` (a diagram, hand-drawn SVG) when a file with its title's slug exists, and no image otherwise. `SITE_URL` (default `https://nexovaph.com`) is the canonical host. Set `REDIRECT_TO_SITE_URL=1` to 301 other hosts (such as www) to it, only once that host resolves to the service. The `products` and `services` tables and admin code remain but are not shown. CSS is `public/assets/site.css` (Lexend and Source Sans 3, self-hosted in `public/assets/fonts`). The look is photography-led: the home hero and industry tiles and each industry page header use `public/assets/photos/{industry-slug}.jpg` (1600x900, named by slug, so a new industry needs a matching photo). Image and font credits are in `CREDITS.txt`.
- **Editable settings** are defined once in `src/Support/SettingsSchema.php`. To add an editable text, add it there, use `{{ s.key }}` in the template, and add a default to `database/seed/settings.json` (new keys are filled in automatically by `bin/seed.php` on the next start; changed wording for existing keys needs a `COPY_REV` bump).
- **Contact form** (`ContactController`): honeypot field `website`, per-email and global flood limit, saves to `messages`, then emails via Symfony Mailer if `MAIL_DSN` is set. A mail failure is logged and never fails the request. The page JS posts it with `fetch` and expects `{ok,message}` JSON.
- **Admin** (`src/Admin/`, routes under `/admin`): `AuthMiddleware` guards the group. Login is env-based (`ADMIN_EMAIL` + `ADMIN_PASSWORD_HASH`), with 5 failures per IP per 15 minutes tracked in `login_attempts` (client IP from `X-Forwarded-For` because Railway proxies). `ProductController` handles products, features and logo upload. `ListController` is one config-driven CRUD for industries, AI services, services and steps (`ListController::CONFIG`). The service icon field is raw SVG rendered unescaped on the public page, so it is validated to a single `<svg>` with no scripts or handlers. Ordering uses a `sort` column, swapped by `Controller::reorder`.

## Deploy (Railway)

Uses the `Dockerfile` (php:8.4-apache; `docker/start.sh` binds Apache to `$PORT`, seeds if empty, chowns the volume). Required: a Volume mounted at `/data`, and variables `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, plus `MAIL_DSN`/`MAIL_FROM`/`MAIL_TO` for email. `DATA_DIR=/data` and `APP_ENV=production` are baked into the image. The volume limits the service to one replica.

## One-time extraction scripts

`bin/extract-seed.mjs` and `bin/extract-template.mjs` generated `database/seed/*`, `templates/site.twig` and the CSS/logo from `_orig/`. They are not part of the runtime. Re-running `extract-template.mjs` overwrites `templates/site.twig`, so any hand edits there would be lost.
