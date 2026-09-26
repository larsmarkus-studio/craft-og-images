<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use larsmarkusstudio\ogimages\models\Settings;

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
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
