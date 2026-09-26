<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\variables;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Html;
use larsmarkusstudio\ogimages\Plugin;
use Throwable;
use Twig\Markup;

/**
 * `craft.ogImages` in Twig. Read-only: nothing here renders or deletes images.
 * Never throws, so a misconfiguration can't break a page; problems are logged.
 */
class OgImagesVariable
{
    /** @var array<string, Asset|null> per entry + site, for this request */
    private array $assets = [];

    /**
     * The entry's current OG image asset, or null.
     */
    public function asset(?Entry $entry): ?Asset
    {
        if (!$entry?->id) {
            return null;
        }

        $key = "$entry->id-$entry->siteId";
        if (!array_key_exists($key, $this->assets)) {
            try {
                $this->assets[$key] = Plugin::getInstance()->images->current($entry);
            } catch (Throwable $e) {
                Craft::error("OG image lookup for entry $entry->id failed: {$e->getMessage()}", 'lms-og-images');
                $this->assets[$key] = null;
            }
        }

        return $this->assets[$key];
    }

    /**
     * The OG image URL, or the configured fallback, or null.
     */
    public function url(?Entry $entry): ?string
    {
        return $this->asset($entry)?->getUrl() ?? $this->fallbackUrl();
    }

    /**
     * og:image (with width and height when it's a generated image) and twitter:image.
     * Use this or an SEO plugin for the image tags, not both.
     */
    public function tags(?Entry $entry): Markup
    {
        $asset = $this->asset($entry);
        $url = $asset?->getUrl() ?? $this->fallbackUrl();
        if (!$url) {
            return new Markup('', 'UTF-8');
        }

        $tags = [Html::tag('meta', '', ['property' => 'og:image', 'content' => $url])];
        if ($asset) {
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:width', 'content' => $asset->width]);
            $tags[] = Html::tag('meta', '', ['property' => 'og:image:height', 'content' => $asset->height]);
        }
        $tags[] = Html::tag('meta', '', ['name' => 'twitter:image', 'content' => $url]);

        return new Markup(implode("\n", $tags), 'UTF-8');
    }

    /**
     * Returns the SEOmate override object with og:image set to the generated image:
     *
     *     {% set seomate = craft.ogImages.seomate(entry ?? null, seomate ?? {}) %}
     *     {% hook 'seomateMeta' %}
     *
     * SEOmate only accepts an Asset for og:image and would transform it into a second,
     * re-encoded copy. So this passes the URL and turns off SEOmate's og:image transform
     * for this page. Without a generated image, SEOmate's own image settings apply unchanged.
     */
    public function seomate(?Entry $entry, array $seomate = []): array
    {
        $asset = $this->asset($entry);
        $seomatePlugin = Craft::$app->getPlugins()->getPlugin('seomate');
        if (!$asset || !$seomatePlugin) {
            return $seomate;
        }

        $transformMap = $seomate['config']['imageTransformMap'] ?? $seomatePlugin->getSettings()->imageTransformMap;
        unset($transformMap['og:image']);

        $seomate['config'] = ['imageTransformMap' => $transformMap] + ($seomate['config'] ?? []);
        $seomate['meta'] = [
            'og:image' => $asset->getUrl(),
            'og:image:width' => $asset->width,
            'og:image:height' => $asset->height,
            'og:image:type' => $asset->getMimeType(),
        ] + ($seomate['meta'] ?? []);

        return $seomate;
    }

    private function fallbackUrl(): ?string
    {
        return Plugin::getInstance()->getSettings()->getFallbackUrl(Craft::$app->getSites()->getCurrentSite()->handle);
    }
}
