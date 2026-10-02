# Changelog

## 0.1.1 - 2026-10-02

- `onlyWithUrl` setting (default on): entries without a URL get no image, on save, in the queue job and in `backfill`

## 0.1.0 - 2026-09-28

First release, for testing on production sites.

- Render OG images from Twig templates via Gotenberg's screenshot route, in a queue job per entry and site
- Published saves only; skip rendering when the rendered HTML is unchanged
- Store images in a dedicated volume and delete older versions
- `craft.ogImages.url()`, `tags()`, `asset()` and the SEOmate helper
- `og-preview` route for previewing the template while editing
- Console commands: `test`, `backfill`, `sweep`
- `assetFiles` setting for fonts and other local files
- Render errors name the resource that failed to load, including an `<img>` with an empty `src`
