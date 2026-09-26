# 0003 One image per entry per site
Status: accepted, 2026-09-24
Decision: Jobs, filenames, lookups and commands are keyed by entryId + siteId. A save queues one job per site the entry exists in.
Why: Translated content needs per-site images, and non-translatable fields propagate to every site.
Rejected: primary site only.
Revisit if: queue volume on large multi-site installs becomes a problem.
