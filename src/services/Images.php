<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\services;

use Craft;
use craft\base\Component;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Volume;
use craft\web\View;
use larsmarkusstudio\ogimages\helpers\Filename;
use larsmarkusstudio\ogimages\Plugin;
use yii\base\Exception;

/**
 * Renders an entry's OG template to HTML and finds its current image.
 */
class Images extends Component
{
    // ponytail: one render type until a second size is needed (decision 0005)
    public const TYPE = 'og';

    /**
     * Renders the entry's template as its own site and language.
     *
     * @return array{template: string, html: string}
     */
    public function render(Entry $entry): array
    {
        $sites = Craft::$app->getSites();
        $previousSite = $sites->getCurrentSite();
        $previousLanguage = Craft::$app->language;
        $site = $entry->getSite();

        $sites->setCurrentSite($site);
        Craft::$app->language = $site->language;

        try {
            $template = $this->templateFor($entry);
            $html = Craft::$app->getView()->renderTemplate($template, ['entry' => $entry, 'site' => $site], View::TEMPLATE_MODE_SITE);
        } finally {
            $sites->setCurrentSite($previousSite);
            Craft::$app->language = $previousLanguage;
        }

        return ['template' => $template, 'html' => $html];
    }

    /**
     * `{templateRoot}/{sectionHandle}`, falling back to `{templateRoot}/default`.
     */
    public function templateFor(Entry $entry): string
    {
        $root = Plugin::getInstance()->getSettings()->templateRoot;
        $view = Craft::$app->getView();

        foreach (["$root/{$entry->getSection()?->handle}", "$root/default"] as $template) {
            if ($view->doesTemplateExist($template, View::TEMPLATE_MODE_SITE)) {
                return $template;
            }
        }

        throw new Exception("No OG template found. Create templates/$root/default.twig.");
    }

    /**
     * Files in `templates/{templateRoot}/assets/`, sent along with index.html.
     *
     * @return array<string, string> filename => path
     */
    public function assetFiles(): array
    {
        $dir = Craft::getAlias('@templates') . '/' . Plugin::getInstance()->getSettings()->templateRoot . '/assets';
        $files = [];
        foreach (glob("$dir/*") ?: [] as $path) {
            if (is_file($path)) {
                $files[basename($path)] = $path;
            }
        }

        return $files;
    }

    /**
     * Changes whenever the image would: HTML, render settings or a template asset file.
     */
    public function hash(string $html, array $files): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $parts = [$html, $settings->width, $settings->height, $settings->format, $settings->quality];
        foreach ($files as $name => $path) {
            $parts[] = $name . sha1_file($path);
        }

        return sha1(implode("\n", $parts));
    }

    /**
     * @throws Exception if the configured volume doesn't exist or has no public URLs
     */
    public function volume(): Volume
    {
        $handle = Plugin::getInstance()->getSettings()->volume;
        $volume = $handle ? Craft::$app->getVolumes()->getVolumeByHandle($handle) : null;

        if (!$volume) {
            throw new Exception("OG volume '$handle' not found. Set 'volume' in config/lms-og-images.php.");
        }
        if (!$volume->getFs()->hasUrls) {
            throw new Exception("OG volume '$handle' has no public URLs.");
        }

        return $volume;
    }

    /**
     * Renders and stores a new image when the hash changed, then hard-deletes the older versions.
     *
     * @return Asset|null the new image, or null when the current one is up to date
     * @throws Exception when rendering or saving fails; the old image is left in place
     */
    public function generate(Entry $entry): ?Asset
    {
        $volume = $this->volume();
        $rendered = $this->render($entry);
        $files = $this->assetFiles();
        $hash = substr($this->hash($rendered['html'], $files), 0, Filename::HASH_LENGTH);
        $versions = $this->versions($entry);

        foreach ($versions as $version) {
            if (Filename::parse($version->filename)['hash'] === $hash) {
                return null;
            }
        }

        $bytes = Plugin::getInstance()->gotenberg->screenshot($rendered['html'], $files);

        $filename = Filename::build(self::TYPE, $entry->id, $entry->siteId, $hash, Plugin::getInstance()->getSettings()->format);
        $tempPath = Craft::$app->getPath()->getTempPath() . '/' . $filename;
        file_put_contents($tempPath, $bytes);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id;
        $asset->setVolumeId($volume->id);
        $asset->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($asset)) {
            @unlink($tempPath);
            throw new Exception("Could not save $filename: " . implode(' ', $asset->getFirstErrors()));
        }

        // Only now that the new image exists. Saving and deleting assets is also what
        // refreshes Craft's template caches and Blitz for pages showing the image.
        foreach ($versions as $old) {
            Craft::$app->getElements()->deleteElement($old, true);
        }

        // SEOmate caches meta per entry and only clears it when the entry itself is saved
        if (class_exists(\vaersaagod\seomate\helpers\CacheHelper::class)) {
            \vaersaagod\seomate\helpers\CacheHelper::deleteMetaCacheForElement($entry);
        }

        return $asset;
    }

    /**
     * The newest image for this entry + site, if any.
     */
    public function current(Entry $entry): ?Asset
    {
        return $this->versions($entry)[0] ?? null;
    }

    /**
     * All images for this entry + site in the volume's root folder, newest first.
     * Only exact filename matches (see Filename::parse), so nothing else in the volume is touched.
     *
     * @return Asset[]
     */
    public function versions(Entry $entry): array
    {
        $volume = $this->volume();
        $assets = Asset::find()
            ->volumeId($volume->id)
            ->folderId(Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id)
            ->filename(Filename::prefix(self::TYPE, $entry->id, $entry->siteId) . '*')
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        return array_values(array_filter($assets, function (Asset $asset) use ($entry) {
            $parsed = Filename::parse($asset->filename);

            return $parsed && $parsed['type'] === self::TYPE && $parsed['entryId'] === $entry->id && $parsed['siteId'] === $entry->siteId;
        }));
    }
}
