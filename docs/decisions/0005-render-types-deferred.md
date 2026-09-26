# 0005 One render type in 1.0, type-prefixed filenames
Status: accepted, 2026-09-24
Decision: 1.0 ships only the `og` type (1200x630). Filenames are `{type}-{entryId}-{siteId}-{hash}.{ext}` and the template root follows the type (`_og/`). Type handles contain no hyphens.
Why: other sizes or formats (square, Pinterest) can be added later as a `types` config with no migration. Gotenberg already takes width, height and format per request.
Rejected: building a presets config now (no current need); unprefixed filenames (would need renaming later).
Revisit if: a second size or format is actually needed.
