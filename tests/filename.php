<?php

// Run: php tests/filename.php (no Craft needed). Exits non-zero on failure.

declare(strict_types=1);

require __DIR__ . '/../src/helpers/Filename.php';

use larsmarkusstudio\ogimages\helpers\Filename;

// zend.assertions can't be switched on at runtime; without it every assert below is skipped
if (ini_get('zend.assertions') !== '1') {
    fwrite(STDERR, "Run with assertions on: php -d zend.assertions=1 tests/filename.php\n");
    exit(1);
}
ini_set('assert.exception', '1');

$hash = sha1('<html>');

// build → parse round trip
$name = Filename::build('og', 123, 2, $hash, 'jpeg');
assert($name === 'og-123-2-' . substr($hash, 0, 12) . '.jpg', $name);
assert(Filename::parse($name) === ['type' => 'og', 'entryId' => 123, 'siteId' => 2, 'hash' => substr($hash, 0, 12), 'ext' => 'jpg']);
assert(Filename::build('og', 1, 1, $hash, 'png') === 'og-1-1-' . substr($hash, 0, 12) . '.png');
assert(str_starts_with($name, Filename::prefix('og', 123, 2)));

// Sweep must ignore anything that isn't exactly ours
foreach ([
    'og-123-2-abc.jpg',                 // short hash
    'og-123-2-' . str_repeat('a', 12) . '_1.jpg', // Craft's conflict suffix
    'og-123-2-' . str_repeat('a', 12) . '.webp',
    'og-123-2-' . str_repeat('A', 12) . '.jpg',   // uppercase hash
    'og-0-2-' . str_repeat('a', 12) . '.jpg',     // id 0
    'og-12-' . str_repeat('a', 12) . '.jpg',      // missing site id
    'hero-image-final.jpg',
    'Og-1-1-' . str_repeat('a', 12) . '.jpg',
    'og-1-1-' . str_repeat('a', 12) . '.jpg.bak',
] as $foreign) {
    assert(Filename::parse($foreign) === null, "should not parse: $foreign");
}

// A type handle other than og (decision 0005)
assert(Filename::parse('square-5-1-' . str_repeat('b', 12) . '.png')['type'] === 'square');

echo "filename: ok\n";
