# Configuration

All settings live at **Configuration → AI → AI Playwright**
(`/admin/config/ai/playwright`), behind the *Administer AI Playwright*
permission.

## Browser

| Setting | Description |
| --- | --- |
| **Internal base URL** | How the browser reaches this site from the server, usually the local loopback (e.g. `http://localhost` or `http://127.0.0.1:8080`). Validated as a plain http(s) URL. |
| **Node.js binary** | `node` from PATH, or an absolute path when the web server has none. |
| **Capture timeout** | Seconds before a single capture is killed (5–300). |

## Security

| Setting | Description |
| --- | --- |
| **Allow opening absolute off-site URLs** | Off by default. When off, the tool only ever opens paths on this site, resolved against the internal base URL. |

!!! warning "Authenticated browser access"
    The tool opens the browser as the acting user via a one-time auto-login
    link, so it sees what that user sees. It is gated on the *Use the AI
    Playwright browser tool* permission, and every argument is passed as an argv
    array — never an interpolated shell string. Grant the permission only to
    trusted roles.

## Test browser capture

**Test browser capture** runs one capture of the front page with the **saved**
settings and reports the URL opened, the page title, and how many console errors
were seen. Save your changes first. A failure surfaces a concise reason (for
example, Node.js or Playwright not installed).
