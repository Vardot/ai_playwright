/**
 * @file
 * Playwright browser capture for the AI Playwright "browser_preview" tool.
 *
 * Gives a Drupal AI Agent eyes on a live, rendered page: it drives a real
 * headless Chromium to a URL, captures a screenshot plus the page title, the
 * visible text and any console errors, and prints a JSON result. The PHP side
 * (PlaywrightRunner) saves the screenshot as a managed file so the agent can
 * run describe_image on it.
 *
 * Usage:  node browser-capture.mjs <url> <output-png-path> [login-url]
 *   login-url (optional): a one-time auto-login link visited first so the
 *   browser sees the page as the acting user (e.g. authenticated content).
 * Output: a single JSON line on stdout:
 *   { "ok": true, "title": "...", "text": "...", "consoleErrors": [...], "screenshot": "<path>" }
 *   { "ok": false, "error": "..." }
 *
 * No network calls beyond the requested URL and the optional login link; no LLM
 * use here - the page is understood back in the Drupal agent via its vision tool.
 */

import { chromium } from 'playwright';

const url = process.argv[2];
const out = process.argv[3];
const loginUrl = process.argv[4];

const fail = (message) => {
  process.stdout.write(JSON.stringify({ ok: false, error: message }) + '\n');
  process.exit(0);
};

if (!url || !out) {
  fail('Usage: browser-capture.mjs <url> <output-png-path> [login-url]');
}

let browser;
try {
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text().slice(0, 200));
    }
  });
  page.on('pageerror', (err) => {
    consoleErrors.push(String(err.message || err).slice(0, 200));
  });

  // Optionally establish a session first (one-time login link), so the target
  // page is seen as the acting user rather than as an anonymous visitor.
  if (loginUrl) {
    await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 30000 }).catch(() => {});
  }

  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(500);

  const title = await page.title();
  const text = (await page.evaluate(() => (document.body ? document.body.innerText : '')))
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 4000);

  await page.screenshot({ path: out, fullPage: true });

  process.stdout.write(JSON.stringify({
    ok: true,
    title,
    text,
    consoleErrors: consoleErrors.slice(0, 10),
    screenshot: out,
  }) + '\n');
}
catch (error) {
  fail(String(error && error.message ? error.message : error).slice(0, 300));
}
finally {
  if (browser) {
    await browser.close();
  }
}
