# 0002 Find OG images by filename, no Assets field
Status: accepted, 2026-09-24
Decision: Name images `og-{entryId}-{siteId}-{hash}.{ext}` in a dedicated folder and look them up by filename. The job never saves the entry.
Why: Saving the entry creates revisions, re-fires save events (a loop) and triggers other plugins (Blitz, search, SEOmatic).
Rejected: writing to an Assets field via saveElement; a plugin table (not needed yet).
Revisit if: editors need to see or override the image per entry (roadmap).
