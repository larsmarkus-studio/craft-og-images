# Changelog

## 0.1.3 - 2026-10-08

- `sweep` also deletes images of entries without a URL when `onlyWithUrl` is on, e.g. images made before that setting existed

## 0.1.2 - 2026-10-08

- Delete a stale file at the target path before saving a new image, so a leftover from a failed run or a trashed asset no longer fails every retry with "A file with the name … already exists"

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
