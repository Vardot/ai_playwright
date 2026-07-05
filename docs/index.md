# AI Playwright

Gives Drupal AI Agents eyes on a rendered page. It opens a page of this site in
a real headless browser (Playwright / Chromium) and hands the AI Agent a
screenshot, the page title, the visible text, and any console errors — so the
**Drupal Canvas AI assistant can look at the page it builds** and verify or
refine it, instead of building blind.

The idea started as a plugin inside Display Builder AI; this module lifts it out
into a small, standalone tool for **Drupal Canvas** and **Drupal Canvas AI
Agents**. Config-driven and framework-agnostic.

## How it fits together

```
Drupal Canvas AI Agent
   │  "open / check / verify how this page renders"
   ▼
ai_playwright:browser_preview  (AI Agent tool)
   │
   ▼
PlaywrightRunner (PHP)  ──▶  headless Chromium (Node + Playwright)
   │                              captures screenshot + title + text + errors
   ▼
screenshot saved as a managed file  ──▶  describe_image  ──▶  the agent "sees" it
```

1. The Canvas AI Agent calls **Browser preview (Playwright)** with a path (or
   nothing, for the front page).
2. `PlaywrightRunner` resolves a safe URL on this site, gives the browser a
   one-time login as the acting user, and runs the bundled Node script.
3. The screenshot is saved as a managed file; the tool returns its file id plus
   the title, visible text, and console errors.
4. The agent passes the file id to `describe_image` to actually see the page,
   then answers or refines its build.

## Next steps

- [Installation](installation.md) — install the module and the browser runtime.
- [Configuration](configuration.md) — the settings page and security options.
- [AI Agent tool](ai-agent-tool.md) — the `ai_playwright:browser_preview`
  reference and how to add it to the Canvas AI assistant.

## Requirements

- Drupal **^11.2**
- `ai` (Drupal AI) and `ai_agents`
- Drupal core `file`
- On the server: **Node.js 18+** and **Playwright** with Chromium
