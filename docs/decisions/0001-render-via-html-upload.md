# 0001 Render via HTML upload, not URL fetch
Status: accepted, 2026-09-24
Decision: The job renders Twig and posts the HTML (plus template asset files) to Gotenberg `/forms/chromium/screenshot/html`.
Why: This removes the render route, tokens, the staging-password exemption and the site URL override, and Gotenberg never needs to reach Craft.
Rejected: `/screenshot/url` with a token-protected route. It burns tokens on redirects, gives 400 instead of 404 on a bad token, and the action path is reachable without a token.
Revisit if: templates need request-dependent output that can't render in a console context.
