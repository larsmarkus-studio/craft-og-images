<?php

// Copy to config/lms-og-images.php in your Craft project.
// Secrets stay in .env: GOTENBERG_URL, GOTENBERG_USER, GOTENBERG_PASSWORD.

return [
    'sections' => ['pages', 'news'],
    'volume' => 'ogImages',

    // Everything below is optional; these are the defaults.
    // 'templateRoot' => '_og',
    // 'width' => 1200,
    // 'height' => 630,
    // 'format' => 'jpeg',
    // 'quality' => 85,
    // 'fallbackUrl' => '$OG_FALLBACK_URL', // or ['siteHandle' => 'https://…']
];
