# AI Agent tool

The module registers one AI Agent tool (an `ai` / `ai_agents` function call).
It gives a Drupal AI Agent a look at a live rendered page — much as the
Playwright MCP does for an editor — but from inside Drupal, as an agent tool the
Drupal Canvas AI assistant can call.

## Browser preview (Playwright)

| | |
| --- | --- |
| **Plugin id** | `ai_playwright:browser_preview` |
| **Function name** | `ai_playwright_browser_preview` |
| **Group** | `information_tools` |
| **Permission** | *Use the AI Playwright browser tool* (`use ai playwright`), or `administer ai agents` / `use Drupal Canvas AI` |

### Inputs

Both are optional:

| Input | Description |
| --- | --- |
| `url` | A path on this site (e.g. `/` or `/about`). Empty means the front page. |
| `task` | A note of what to check on the page, echoed back for context. |

### Output

One text block with:

- the URL that was opened,
- the page **title**,
- a **screenshot** saved as a managed file — its id can be passed to
  `describe_image` so the agent actually sees the page,
- any **console errors**, and
- the page's **visible text** (trimmed).

This is the same signal the Playwright MCP surfaces from a snapshot — a
screenshot, the page text, and console errors — delivered to the agent in a
single call so it can verify what it built.

### Use it with the Canvas AI assistant

Add **Browser preview (Playwright)** to the Canvas AI assistant's tool list
(Configuration → AI → Agents → your orchestrator → Select tools). Then ask the
assistant to open, check, or verify a page. It calls the tool, gets the
screenshot file id, calls `describe_image` to see the render, and answers or
refines the build.

!!! note "Scope"
    This first release captures a page (navigate → screenshot → text → console
    errors), which is what an agent needs to *look at* a result. Interactive
    steps (click, type, multi-step flows) are out of scope for now.
