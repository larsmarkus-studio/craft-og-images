# 0008 Plugin handle `lms-og-images`
Status: accepted, 2026-09-24
Decision: the handle is `lms-og-images` (config file `config/lms-og-images.php`, commands `lms-og-images/backfill|sweep|test`, one console controller per command). The Twig variable stays `craft.ogImages`.
Why: the `lms-` vendor prefix keeps the handle unique in the Plugin Store (free as of 2026-09-24), and one controller per command avoids `og-images/images/...` style repetition.
Rejected: `og-images` (generic, likely to clash); a single `ImagesController` (commands read double).
Revisit if: before the first production install, which is the last cheap moment to rename.
