# 0009 — Only entries with a URL get an image

Status: accepted, 2026-10-02

Decision: `onlyWithUrl` setting, on by default. Entries without a URL are skipped on save, in the queue job (the URL may have gone since queueing) and in `backfill`.
Why: sections mix entries with and without a page (one entry type has a URI format, the others don't; or the URI format depends on a field), and an image for a page that doesn't exist is wasted renders and storage.
Rejected: an entry-type filter setting (a second list to keep in sync with the URI formats), a skip event (more API for the same rule).
Revisit if: an entry without a URL needs an image anyway, e.g. for a non-web share target (turn the setting off).
