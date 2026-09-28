# Changelog

## Unreleased

- Render OG images from Twig templates via Gotenberg's screenshot route, in a queue job per entry and site
- Published saves only; skip rendering when the rendered HTML is unchanged
- Store images in a dedicated volume and delete older versions
- `craft.ogImages.url()`, `tags()`, `asset()` and the SEOmate helper
- `og-preview` route for previewing the template while editing
- Console commands: `test`, `backfill`, `sweep`
- `assetFiles` setting for fonts and other local files
