# AI Playwright

[![pipeline status](https://git.drupalcode.org/project/ai_playwright/badges/1.0.x/pipeline.svg)](https://git.drupalcode.org/project/ai_playwright/-/pipelines)

Gives Drupal AI Agents eyes on a rendered page. It opens a page of this site in
a real headless browser (Playwright / Chromium) and hands the AI Agent a
screenshot, the page title, the visible text, and any console errors — so the
Drupal Canvas AI assistant can see and verify what it builds, instead of
building blind.

The idea started as a plugin inside Display Builder AI; this module lifts it out
into a small, standalone tool for **Drupal Canvas** and **Drupal Canvas AI
Agents**. Config-driven and framework-agnostic.

## What it provides

- an **AI Agent tool** — *Browser preview (Playwright)* — that captures a page
  and returns a screenshot file id (pass it to `describe_image` to "see" it),
  the title, text, and console errors, and
- a **settings page** for the internal base URL, the Node binary, the timeout,
  and whether off-site URLs are allowed.

## Requirements

- Drupal **^11.2**
- `ai` (Drupal AI) and `ai_agents`
- Drupal core `file`
- On the server: **Node.js 18+** and **Playwright** with Chromium

## Install

```bash
composer require drupal/ai_playwright
ddev drush en ai_playwright -y
```

Then install the browser runtime on the server, from the module folder:

```bash
cd web/modules/contrib/ai_playwright
npm install playwright && npx playwright install --with-deps chromium
```

If Node or Playwright is missing, the tool fails gracefully with a clear message
and the rest of the site is unaffected. The status report
(`/admin/reports/status`) shows whether Node.js is found.

## The AI Agent tool

**Browser preview (Playwright)**

- Plugin id: `ai_playwright:browser_preview`
- Function name: `ai_playwright_browser_preview`
- Group: `information_tools`
- Inputs (both optional): `url` (a path on this site, e.g. `/` or `/about`;
  empty means the front page) and `task` (a note of what to check, echoed back).
- Output: the opened URL, the page title, a screenshot saved as a managed file
  (its id can be passed to `describe_image`), the console errors, and the
  visible text.

Add the tool to the Canvas AI assistant's tool list, then ask it to open, check,
or verify how a page renders — it captures the page and can then describe the
screenshot to confirm or refine the build.

## Settings

**Configuration → AI → AI Playwright** (`/admin/config/ai/playwright`):

- **Internal base URL** — how the browser reaches this site from the server
  (usually the local loopback). Validated as a plain http(s) URL.
- **Node.js binary** — `node` from PATH, or an absolute path.
- **Capture timeout** — seconds before a capture is killed.
- **Allow opening absolute off-site URLs** — off by default; when off, the tool
  only ever opens paths on this site.
- **Test browser capture** — runs one capture of the front page with the saved
  settings.

## Security

The tool opens an authenticated browser as the acting user (a one-time
auto-login link), so it sees the same content that user can. It is gated on the
*Use the AI Playwright browser tool* permission, targets this site only unless an
administrator enables off-site URLs, and passes every argument as an argv array —
never an interpolated shell string. Treat enabling it as granting the agent
authenticated browser access to the site.

## Documentation

Full documentation: <https://project.pages.drupalcode.org/ai_playwright/>
