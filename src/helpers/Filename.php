<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\helpers;

/**
 * Builds and parses OG image filenames: `{type}-{entryId}-{siteId}-{hash}.{ext}`.
 *
 * Plain PHP with no Craft dependency, so tests/filename.php can run it on its own.
 * Sweep decides what to delete based on parse(), so anything that doesn't match
 * exactly is left alone.
 */
final class Filename
{
    // ponytail: 12 hex chars of sha1 is plenty, since hashes only ever compare within one entry + site
    public const HASH_LENGTH = 12;

    private const PATTERN = '/^([a-z][a-z0-9]*)-([1-9]\d*)-([1-9]\d*)-([0-9a-f]{12})\.(jpg|png)$/';

    public static function build(string $type, int $entryId, int $siteId, string $hash, string $format): string
    {
        return self::prefix($type, $entryId, $siteId) . substr($hash, 0, self::HASH_LENGTH) . '.' . self::extension($format);
    }

    /**
     * The part shared by every version of one entry + site, e.g. `og-12-1-`.
     * Append `*` for an asset query on `filename`.
     */
    public static function prefix(string $type, int $entryId, int $siteId): string
    {
        return "$type-$entryId-$siteId-";
    }

    /**
     * @return array{type: string, entryId: int, siteId: int, hash: string, ext: string}|null
     */
    public static function parse(string $filename): ?array
    {
        if (!preg_match(self::PATTERN, $filename, $m)) {
            return null;
        }

        return [
            'type' => $m[1],
            'entryId' => (int)$m[2],
            'siteId' => (int)$m[3],
            'hash' => $m[4],
            'ext' => $m[5],
        ];
    }

    /**
     * Gotenberg's format name to file extension.
     */
    public static function extension(string $format): string
    {
        return $format === 'jpeg' ? 'jpg' : $format;
    }
}
