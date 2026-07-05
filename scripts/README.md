# Browser tool (Playwright)

`browser-capture.mjs` backs the `ai_playwright:browser_preview` AI Agent tool. It
drives a real headless browser to a page on this site and returns a screenshot,
the page title, the visible text and any console errors, so a Drupal AI Agent
can see how a page renders.

## Requirements

The tool needs, on the server that runs Drupal:

- **Node.js 18+**
- **Playwright** with the Chromium browser, installed from this folder:

  ```
  cd <module>/ && npm install playwright && npx playwright install --with-deps chromium
  ```

If Node or Playwright is missing the tool fails gracefully with a clear message;
the rest of the site is unaffected. The Node binary and a capture timeout are
configurable at **Configuration → AI → AI Playwright**.

## Security

The tool opens an authenticated browser as the acting user (a one-time
auto-login link), so it sees the same content that user can. It is gated on the
*Use the AI Playwright browser tool* permission, targets this site only (unless
an administrator explicitly allows off-site URLs), and passes every argument as
an argv array — never an interpolated shell string. Treat enabling it as
granting the agent authenticated browser access to the site.
