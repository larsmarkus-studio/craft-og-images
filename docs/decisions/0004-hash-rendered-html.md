# 0004 Hash the rendered HTML
Status: accepted, 2026-09-24
Decision: hash = sha1(rendered HTML + width/height/format). The job skips Gotenberg when an asset with that hash exists.
Why: It catches template, related-content and global changes with zero config.
Rejected: a configured list of fields (misses template and relation changes); hashing in the listener (can't see rendered output).
Revisit if: rendering Twig per site per save becomes expensive, or templates contain time-dependent output.
