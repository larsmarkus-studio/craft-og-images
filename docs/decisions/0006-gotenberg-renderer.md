# 0006 Gotenberg as the renderer
Status: accepted, 2026-09-24
Decision: Gotenberg 8, self-hosted, called on the `/forms/chromium/screenshot/html` route. The plugin ships one concrete `GotenbergRenderer`, with no interface.
Why: real Chromium means full HTML/CSS in Twig. It's one maintained MIT Docker image, with basic auth and an allow-list built in, a plain multipart API, and no per-render cost.
Rejected: Browsershot (needs Node and Chrome on the web host, which managed PHP hosts often lack), Satori/@vercel/og (only a subset of CSS, JSX instead of Twig), Browserless (heavier, licensing), Cloudflare Browser Rendering (SaaS), a custom Puppeteer service (ours to maintain), GD/Imagick (design logic in PHP), hosted html2img (API key, paid plan).
Revisit if: Gotenberg drops the screenshot routes, render time or memory becomes a problem, or a second renderer is needed (then extract an interface).
