# Installation

## Install the module

```bash
composer require drupal/ai_playwright
ddev drush en ai_playwright -y
```

This installs the dependencies `ai`, `ai_agents` and the core `file` module.

## Install the browser runtime

The tool drives a real browser, so the server that runs Drupal needs **Node.js
18+** and **Playwright** with Chromium. Install them from the module folder:

```bash
cd web/modules/contrib/ai_playwright
npm install playwright && npx playwright install --with-deps chromium
```

!!! note "Graceful when missing"
    If Node or Playwright is absent the tool fails with a clear message and the
    rest of the site is unaffected. The status report
    (`/admin/reports/status`) shows an **AI Playwright** line reporting the
    detected Node.js version, or a warning with a link to the settings page.

## Add the tool to the Canvas AI assistant

So the Drupal Canvas AI Agent can look at pages, add the tool to its assistant:

1. Edit your Canvas AI orchestrator agent (Configuration → AI → Agents).
2. Under **Select tools**, enable **Browser preview (Playwright)**.
3. Save.

The agent can now open, check, and verify how a page renders.

## Uninstall

```bash
ddev drush pmu ai_playwright -y
```
