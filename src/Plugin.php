<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use larsmarkusstudio\ogimages\models\Settings;
use larsmarkusstudio\ogimages\services\Gotenberg;
use larsmarkusstudio\ogimages\services\Images;

/**
 * OG Images plugin.
 *
 * Renders Open Graph images from Twig templates via a self-hosted Gotenberg
 * instance and stores them as assets.
 *
 * @author Lars Markus
 * @license MIT
 *
 * @property-read Settings $settings
 * @property-read Images $images
 * @property-read Gotenberg $gotenberg
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'images' => Images::class,
                'gotenberg' => Gotenberg::class,
            ],
        ];
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
