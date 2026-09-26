# 0007 Dedicated OG volume, separate path per environment, flat structure
Status: accepted, 2026-09-24
Decision: each site gets an `ogImages` volume on the site's existing CDN-backed filesystem, with a path set per environment (e.g. `og-images/$CRAFT_ENVIRONMENT`) and all files in the volume root. The plugin never creates volumes.
Why: sweep's boundary is a whole volume, so editorial assets are never at risk. Environments share entry IDs through database copies, so a shared path would let staging delete production's images. The filename already holds type, entry, site and hash.
Rejected: a subfolder inside an editorial volume (sweep boundary is a folder); a shared path across environments; per-site or per-type folders (path handling for no gain).
Revisit if: the storage backend or Craft handles large flat folders badly.
