# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ai_playwright` — a Drupal 11 module (`drupal/ai_playwright`, maintained by Vardot). Gives Drupal AI Agents a look at a rendered page: an **AI Agent tool** that drives a real headless browser (Playwright / Chromium, via a bundled Node script) to a page of the site and returns a screenshot (saved as a managed file), the page title, the visible text, and any console errors — so the Drupal Canvas AI assistant can see and verify what it builds. Config-driven, framework-agnostic.

The idea was lifted out of a `display_builder_ai` plugin (`BrowserPreview`) into this standalone module for **Drupal Canvas** and **Drupal Canvas AI Agents**, stripped of all display_builder coupling (no `page_layout`, no display-builder tempstore or permission).

## Commands

PHP is a Drupal module — installed into a Drupal 11 site, not run standalone.

```bash
# Enable + browser runtime
ddev drush en ai_playwright -y
cd web/modules/contrib/ai_playwright && npm install playwright && npx playwright install --with-deps chromium

# Coding standards (matches CI)
phpcs -n --standard=Drupal,DrupalPractice \
  --extensions=php,module,inc,install,profile,theme,yml \
  --ignore="*/node_modules/*,*/vendor/*" .

# Unit test
vendor/bin/phpunit -c web/core/phpunit.xml.dist --filter testResolveUrl path/to/module/tests/src/Unit/ResolveUrlTest.php
```

CI (`.gitlab-ci.yml`) uses the drupal.org GitLabCI template: composer, PHPCS (blocking), PHPStan (blocking), PHPUnit, cspell (advisory), and a `pages` job that builds `docs/` with MkDocs and publishes to `https://project.pages.drupalcode.org/ai_playwright/`.

## Architecture

**`PlaywrightRunner`** (`src/PlaywrightRunner.php`, service `ai_playwright.runner`) — the whole browser integration. `capture()` resolves a safe URL, gives the browser a one-time auto-login as the current user (`user.reset.login`), shells out via Symfony `Process` (argv array, never a shell string) to the bundled Node script, saves the screenshot as a managed file, and returns title/text/console-errors/fid. `resolveUrl()` is the security boundary: relative paths resolve against the configured `internal_base`; absolute URLs are allowed only when `allow_external_urls` is on and only for http(s).

**`BrowserPreview`** (`src/Plugin/AiFunctionCall/BrowserPreview.php`) — the `#[FunctionCall]` AI Agent tool `ai_playwright:browser_preview` (group `information_tools`). A thin wrapper over the runner; gates on `use ai playwright` / `administer ai agents` / `use Drupal Canvas AI`.

**`scripts/browser-capture.mjs`** — the Node + Playwright script: navigate (optional login first) → screenshot + title + innerText + console errors → one JSON line on stdout. No LLM here; the screenshot is understood back in Drupal via the vision provider's `describe_image`.

**Config** (`config/install/ai_playwright.settings.yml`, schema in `config/schema/`): `internal_base`, `node_binary`, `timeout`, `screenshot_scheme`, `allow_external_urls`. `SettingsForm` exposes them plus a "Test browser capture" button.

## Conventions

- `declare(strict_types=1);` on every PHP file; constructor promotion; typed properties.
- Drupal coding standards (`Drupal,DrupalPractice`) enforced in CI.
- American English in comments and docs (color, summarize, defense).
- `core_version_requirement: ^11.2`; PHP `>=8.3`.
- Security-first: the tool runs a browser as the acting user — keep the argv-array process call, the permission gate, and the SSRF boundary in `resolveUrl()`.
