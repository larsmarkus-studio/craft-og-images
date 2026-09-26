# Brief: Craft CMS plugin, self-hosted OG images from Twig via Gotenberg

## Goal
An open-source Craft 5 plugin (MIT). When an entry is saved, a queued job renders its OG template to HTML, uploads it to a self-hosted Gotenberg instance, gets back a 1200x630 image, stores it as an Asset in a dedicated CDN-backed volume, and removes the previous image. There is one image per entry per site.

The point: full HTML/CSS control (custom fonts, layout) in normal Twig templates, with no design logic in PHP, no per-render fees and no third-party SaaS dependency.

## Positioning
- Closest existing plugin: html2img "Auto Open Graph Images" (hosted API, needs an API key and a paid plan). It has the same workflow idea with a different model. Ours is self-hosted and costs nothing per render.
- Other plugins found: Share Previews (alpshq, Craft 4 only, layer editor, not HTML/CSS) and Paperclip (commercial PDF plugin with a Gotenberg driver).
- Our niche: Craft 5 + Gotenberg + Twig template + queue + CDN storage + cleanup, multi-site from day one.
- Market check done 2026-09-24 (Plugin Store, GitHub, Packagist): no other OG/social image plugins found beyond those above.

## Stack and environment
- Craft CMS 5.x. Assume the host has no persistent local filesystem (common on managed PHP hosts): use Craft's temp path for intermediate files only.
- Gotenberg 8.x on a separate server, reached over HTTPS with basic auth.
- Each site's existing CDN-backed filesystem, with a dedicated `ogImages` volume per environment (see Storage setup).
- Composer package, a real plugin (not a module), installable across sites.

## Settled design (see `docs/decisions/`)
- **HTML upload, not URL fetch.** The job renders Twig itself and posts the HTML to Gotenberg. Gotenberg never calls the Craft site: there is no render route, no tokens and no staging-password exemption.
- **Lookup by filename, no Assets field.** The job never saves the entry. The image is found by its filename `og-{entryId}-{siteId}-{hash}.{ext}` in the OG volume. There is no plugin table and no field handle setting.
- **Always multi-site.** Jobs, filenames, lookups and commands are keyed by entryId + siteId.
- **Hash = sha1 of the rendered HTML plus the render settings** (width, height, format). This catches template, related-content and global changes with no config.

## Storage setup (per site, per environment)
The plugin never creates filesystems or volumes: those are project config. It only writes into the volume it's given. Each site needs:

- **Where:** the site's existing CDN-backed storage. No new storage and no new filesystem plugin: reuse whatever filesystem the site already uses. The plugin only uses Craft's volume API, so it works on any filesystem.
- **A dedicated volume `ogImages`:**
  - It's separate from editorial volumes, so sweep's boundary is a whole volume, not a folder. Hide it from editors with volume permissions.
  - Public URLs on, base URL = the CDN URL. No transforms.
  - Give it a path of its own inside the storage, set from an env var such as `og-images/$CRAFT_ENVIRONMENT`. Use the volume's subpath setting if your Craft 5 version has one; otherwise use the filesystem's subpath.
- **Environments must not share that path.** Staging and local are usually copies of the production database, so entry IDs match. With a shared path, a staging job hard-deletes production's `og-123-1-*` file, and production's sweep deletes staging's images.
- **Folder structure: flat, the volume root only.** The filename `{type}-{entryId}-{siteId}-{hash}.{ext}` (type is always `og` in 1.0) already encodes type, entry, site and version, and every lookup goes through Craft's asset index, not a directory listing. Per-site or per-type folders would add nothing but path handling.
- **The plugin checks the target:** `lms-og-images/test` reports whether the volume exists, has public URLs and can be written to. The job fails with a clear error if not.

## Scope
In 1.0: Gotenberg client, queue job, asset storage and cleanup, Twig helper, console commands, config file settings, docs.

Out of 1.0 (list them as roadmap in the README, don't build them): CP settings page, editor UI, per-entry overrides, template gallery, CP preview button, PDF features, other renderers (add a renderer interface when a second one exists).

## Requirements

1. **Plugin skeleton**: Composer package `larsmarkus-studio/craft-og-images` (type `craft-plugin`), handle `lms-og-images`, name "OG Images", namespace `larsmarkusstudio\ogimages`, PSR-4, Craft 5 only (`craftcms/cms ^5.0`, PHP 8.2+), no other dependencies, semver, MIT license, CHANGELOG. Layout:
   ```
   src/
     Plugin.php                       # settings, components, save listener, Twig variable, console namespace
     models/Settings.php
     services/Images.php              # render HTML, hash, find current asset, store + cleanup, sweep candidates
     services/Gotenberg.php           # the one multipart POST (decision 0006)
     jobs/GenerateOgImageJob.php
     variables/OgImagesVariable.php   # craft.ogImages: url() and tags() only, so templates can't call sweep()
     console/controllers/BackfillController.php   # actionIndex → lms-og-images/backfill
     console/controllers/SweepController.php      # actionIndex → lms-og-images/sweep
     console/controllers/TestController.php       # actionIndex → lms-og-images/test
     helpers/Filename.php             # build/parse {type}-{entryId}-{siteId}-{hash}.{ext}, pure PHP
   tests/filename.php                 # assert-based, runs without Craft: php tests/filename.php
   examples/  docker-compose.gotenberg.yml, config/lms-og-images.php, templates/_og/ (default.twig, og.css, assets/ with an OFL font)
   docs/      decisions/, example.png
   .gitattributes                     # export-ignore docs/ examples/ tests/ BRIEF.md
   ```
   Not needed: migrations (no table), web controllers (no render route), CP templates (no settings page in 1.0), asset bundles. The three console controllers are thin; all logic lives in `services/Images.php`.

2. **Settings** (`config/lms-og-images.php`, env-parsed, multi-environment aware):
   - `gotenbergUrl`, `gotenbergUser`, `gotenbergPassword` (`$GOTENBERG_URL`, `$GOTENBERG_USER`, `$GOTENBERG_PASSWORD`)
   - `sections`: enabled section handles
   - `volume`: handle of the dedicated OG volume (see Storage setup)
   - `templateRoot`: default `_og`
   - `width`, `height` (default 1200x630), `format` (`jpeg` default, or `png`), `quality`
   - `fallbackUrl`: may be a string, or an array keyed by site handle
   - Nothing hardcoded to a specific site, section or volume.

3. **Templates by convention**: `{templateRoot}/{sectionHandle}` falls back to `{templateRoot}/default`. The templates receive `entry` and `site`.

4. **Event listener** on `Elements::EVENT_AFTER_SAVE_ELEMENT` (verify it fires after the transaction commits; otherwise use `Entry::EVENT_AFTER_PROPAGATE`):
   - Skip anything where `ElementHelper::isDraftOrRevision()` is true, plus `propagating`, `resaving` and sections that aren't enabled.
   - Queue one `GenerateOgImageJob(entryId, siteId)` per site the entry exists in. Non-translatable fields propagate, so every site can change. Unchanged sites cost one Twig render and then exit.

5. **Job `GenerateOgImageJob(entryId, siteId)`**:
   - Acquire the mutex `lms-og-images:{entryId}:{siteId}`. If it's held, re-push the job with a short delay and return.
   - Re-fetch the entry for that site (`status(null)`). Return early if it's missing or disabled for that site.
   - Set the current site and app language to the entry's site, then render the template in `View::TEMPLATE_MODE_SITE`.
   - Compute the hash. If an asset with that hash already exists, return.
   - Post to Gotenberg and write the returned bytes to a temp file.
   - Save a new Asset to the OG volume's root folder (`Assets::getRootFolderByVolumeId()`, `SCENARIO_CREATE`, `tempFilePath`, `newFolderId`, `avoidFilenameConflicts`).
   - After the swap, invalidate caches for the entry with `Craft::$app->getElements()->invalidateCachesForElement($entry)`. The job never saves the entry, so full-page caches (e.g. Blitz) would otherwise keep serving the old, deleted image URL. Verify Blitz actually refreshes; if it doesn't, call Blitz's refresh API when Blitz is installed.
   - Only after that save succeeds, hard-delete the other `og-{entryId}-{siteId}-*` assets with `deleteElement($asset, true)`. A soft delete leaves the file in storage.
   - On failure: delete the new asset if one was created, keep the old one, and throw.
   - Implement `RetryableJobInterface`: `getTtr()` about 60, `canRetry()` up to 3 attempts. Craft's queue has no built-in backoff, so re-push with `delay` if backoff is needed.
   - Log clear errors (auth failure, timeout, allow-list rejection, 4xx/5xx body) with `Craft::error(..., 'lms-og-images')`. Never log credentials.

6. **Gotenberg request**: `POST /forms/chromium/screenshot/html`, multipart, with basic auth, sent through `Craft::createGuzzleClient()`.
   - Files: the rendered `index.html`, plus every file in `templates/{templateRoot}/assets/` (fonts, logo, CSS if external). The template references them by relative path, e.g. `url('inter.woff2')`.
   - Form fields: `width`, `height`, `format`, `quality`, `waitForExpression=window.ogReady === true`, and `skipNetworkIdleEvent=false`.
   - Check these in the official docs for your Gotenberg 8.x version before implementing, and don't guess:
     - every field name and default above;
     - the fail-on-resource options, so a missing font fails the job instead of rendering in a fallback font;
     - how multipart asset files are resolved relative to `index.html`.
   - Where things go:
     - **CSS**: inline in the template, via `<style>{{ source('_og/og.css') }}</style>` or plain `<style>`. This needs no network.
     - **Fonts**: send them as files from `_og/assets/`. This avoids network and CORS (the page's origin is `file://`, so a cross-origin font needs `Access-Control-Allow-Origin: *`).
     - **Entry images** (hero photos): absolute CDN/transform URLs. The allow-list only needs the CDN host.
   - Readiness: the template sets `document.fonts.ready.then(() => window.ogReady = true)`. If the template has images, it should wait for those too.

7. **Twig helper**:
   - `craft.ogImages.url(entry)` returns the asset URL for the entry's site, or the fallback. It is one query, so memoize it per request.
   - `craft.ogImages.tags(entry)` outputs `og:image`, `og:image:width`, `og:image:height` and `twitter:image` as `Markup`.
   - The README covers plain `tags()` and SEOmate only: feed `url()` into SEOmate's Twig override (e.g. `{% set seomate = { meta: { image: craft.ogImages.url(entry) } } %}`). Check whether SEOmate accepts a URL string there or needs an Asset. It also says to use either `tags()` or SEOmate, never both.

8. **Console commands** (all accept `--site=`, default all sites):
   - `lms-og-images/backfill [--section=] [--limit=] [--force]` queues jobs. Without `--force`, it only queues entry/site pairs that have no image.
   - `lms-og-images/sweep [--dry-run]` works only in the configured volume, and only on files matching the `{type}-{entryId}-{siteId}-{hash}` pattern. It deletes those assets when:
     - the entry/site pair no longer exists or its section isn't enabled;
     - the file isn't the newest asset for its entry/site.
     It skips any asset referenced in the `relations` table. Document running it on a cron.
   - `lms-og-images/test <entryId> [--site=]` renders synchronously and prints the template used, the hash, timing, size and errors. `--html` dumps the rendered HTML.

## Config and security
- No secrets in code: everything comes from env.
- Gotenberg is called only from queue jobs and console commands, never from a web request. Document `runQueueAutomatically => false` plus a queue worker (a daemon running `queue/listen`).
- OG images live in a dedicated volume with its own path per environment. Sweep and delete verify the volume and the filename pattern before removing anything.
- Gotenberg is protected by basic auth over HTTPS. Its Chromium allow-list is `file:///tmp/` plus the sites' CDN hosts.

## Docs (part of done)
- **README**:
  - what it is and how it differs from hosted options;
  - install and the config file;
  - the template convention and an example with a custom font and inline CSS;
  - the readiness flag;
  - the queue worker note;
  - Gotenberg requirements;
  - troubleshooting: auth, allow-list, fonts not loaded, CORS, templates that use `craft.app.request`, `PRIMARY_SITE_URL` for absolute URLs in console;
  - a best-effort maintenance statement and the roadmap.
- `examples/docker-compose.gotenberg.yml`: basic auth, Chromium allow-list, memory limit, restart policy, health check.
- `examples/_og/default.twig` plus `examples/_og/assets/`: a minimal 1200x630 layout using a custom font.
- A screenshot of an example output.
- `docs/decisions/`: the decision records that ship with this brief.

## Local development and first test project
- The test project requires `larsmarkus-studio/craft-og-images` from GitHub (VCS), mounts `../craft-og-images` into the local dev container, and runs a `link-og-images` Composer script to symlink the vendor copy to the working tree. Re-run the link after any `composer install/update`.
- First test project: a Craft 5.11 site (PHP 8.4) with a CDN filesystem plugin, a full-page cache and SEOmate.
- Local Gotenberg: run `examples/docker-compose.gotenberg.yml` next to the test project and set `GOTENBERG_URL=http://gotenberg:3000`.

## Before you start
- Check the filesystem setup of the test project and reuse it.
- Ask for section and volume handles instead of guessing.
- Propose the file structure first, then implement in small steps.

## Release plan
- Dogfood on 2 or 3 production sites (at least one multi-site) for a few weeks.
- Fix what breaks, then tag 1.0, publish on GitHub and Packagist, and consider the Craft Plugin Store afterwards (the CP settings page goes in then).

## Done when
- Saving an entry produces a new OG asset per site in the OG volume within a minute.
- The old asset is gone and the meta tag points to the new URL, on every site.
- A repeat save with no changes queues jobs that exit without calling Gotenberg.
- Editing the OG template and running `backfill --force` regenerates the images. Unchanged entries are still skipped by hash.
- If Gotenberg fails, the old image stays in place and the job shows as failed.
- Backfill, sweep (dry-run) and test commands work.
- The README, example Compose file, example template and decision records are in place.
