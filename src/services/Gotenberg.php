<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Exception\GuzzleException;
use larsmarkusstudio\ogimages\Plugin;
use yii\base\Exception;

/**
 * Posts rendered HTML to Gotenberg's Chromium screenshot route and returns the image bytes.
 * Parameters checked against Gotenberg 8.37.
 */
class Gotenberg extends Component
{
    /**
     * Gotenberg waits for this to be true before the screenshot. Network idle covers
     * CSS backgrounds; this also covers web fonts and <img> tags. Templates need no script.
     */
    private const READY = "document.fonts.status === 'loaded' && Array.from(document.images).every(i => i.complete)";

    /**
     * Gotenberg silently drops requests its allow-list blocks (net::ERR_ACCESS_DENIED is left out
     * of failOnResourceLoadingFailed on purpose), so an image or font from a host that isn't
     * listed would render blank. This throws on load instead; failOnConsoleExceptions turns it into a 409.
     */
    private const CHECK_SCRIPT = <<<'JS'
<script>addEventListener('load', () => {
  const failed = Array.from(document.images).filter(i => !i.naturalWidth).map(i => i.currentSrc || i.src)
    .concat(Array.from(document.fonts).filter(f => f.status === 'error').map(f => 'font ' + f.family));
  if (failed.length) throw new Error('OG_RESOURCES_FAILED: ' + failed.join(', '));
});</script>
JS;

    /**
     * @param array<string, string> $files extra files next to index.html, as filename => local path
     * @throws Exception with a readable message on any failure
     */
    public function screenshot(string $html, array $files = []): string
    {
        $settings = Plugin::getInstance()->getSettings();

        $html = str_contains($html, '</body>')
            ? substr_replace($html, self::CHECK_SCRIPT, strrpos($html, '</body>'), 0)
            : $html . self::CHECK_SCRIPT;

        $multipart = [['name' => 'files', 'contents' => $html, 'filename' => 'index.html']];
        foreach ($files as $name => $path) {
            $multipart[] = ['name' => 'files', 'contents' => fopen($path, 'rb'), 'filename' => $name];
        }

        $fields = [
            'width' => $settings->width,
            'height' => $settings->height,
            'format' => $settings->format,
            // Gotenberg skips the network-idle wait by default
            'skipNetworkIdleEvent' => 'false',
            'waitForExpression' => self::READY,
            // A missing font or a host blocked by the allow-list fails the render instead of rendering wrong
            'failOnResourceLoadingFailed' => 'true',
            'failOnResourceHttpStatusCodes' => '[499,599]',
            // Also fails on errors in the template's own scripts
            'failOnConsoleExceptions' => 'true',
        ];
        if ($settings->format === 'jpeg') {
            $fields['quality'] = $settings->quality;
        }
        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => $name, 'contents' => (string)$value];
        }

        $url = $settings->getGotenbergUrl();
        if ($url === '') {
            throw new Exception('GOTENBERG_URL is not set.');
        }

        try {
            // ponytail: timeouts fixed just above Gotenberg's --api-timeout=30s; make them settings if a server needs longer
            $response = Craft::createGuzzleClient(['timeout' => 35, 'connect_timeout' => 5])
                ->post("$url/forms/chromium/screenshot/html", [
                    'auth' => [$settings->getGotenbergUser(), $settings->getGotenbergPassword()],
                    'multipart' => $multipart,
                    'http_errors' => false,
                ]);
        } catch (GuzzleException $e) {
            throw new Exception("Gotenberg unreachable at $url: {$e->getMessage()}", 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status === 200) {
            return (string)$response->getBody();
        }

        $body = trim((string)$response->getBody());
        if (preg_match('/OG_RESOURCES_FAILED: ([^"\n\\\\]+)/', $body, $m)) {
            throw new Exception("These failed to load: {$m[1]}. Check the URLs, and that each host is in the server's Chromium allow-list.");
        }

        $body = mb_substr($body, 0, 500);
        throw new Exception(match ($status) {
            401 => 'Gotenberg rejected the credentials (401): check GOTENBERG_USER and GOTENBERG_PASSWORD.',
            503 => "Gotenberg timed out (503): $body",
            400, 409 => "Gotenberg could not render ($status): $body",
            default => "Gotenberg returned $status: $body",
        });
    }
}
