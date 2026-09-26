<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Plugin settings. Override in `config/lms-og-images.php` (multi-environment arrays work).
 * Values starting with `$` are read from the environment.
 */
class Settings extends Model
{
    public string $gotenbergUrl = '$GOTENBERG_URL';
    public string $gotenbergUser = '$GOTENBERG_USER';
    public string $gotenbergPassword = '$GOTENBERG_PASSWORD';

    /** Section handles that get OG images. */
    public array $sections = [];

    /** Handle of the dedicated OG volume (see README: storage setup). */
    public ?string $volume = null;

    /** Templates are `{templateRoot}/{sectionHandle}`, falling back to `{templateRoot}/default`. */
    public string $templateRoot = '_og';

    public int $width = 1200;
    public int $height = 630;

    /** `jpeg` or `png`, as Gotenberg names them. */
    public string $format = 'jpeg';

    /** JPEG quality, 1–100. */
    public int $quality = 85;

    /** Used when an entry has no image yet. A URL, or an array of URLs keyed by site handle. */
    public string|array|null $fallbackUrl = null;

    public function getGotenbergUrl(): string
    {
        return rtrim((string)App::parseEnv($this->gotenbergUrl), '/');
    }

    public function getGotenbergUser(): string
    {
        return (string)App::parseEnv($this->gotenbergUser);
    }

    public function getGotenbergPassword(): string
    {
        return (string)App::parseEnv($this->gotenbergPassword);
    }

    public function getFallbackUrl(string $siteHandle): ?string
    {
        $url = is_array($this->fallbackUrl) ? ($this->fallbackUrl[$siteHandle] ?? null) : $this->fallbackUrl;

        // An empty or unset env var means no fallback
        return $url !== null ? (App::parseEnv($url) ?: null) : null;
    }

    protected function defineRules(): array
    {
        return [
            [['width', 'height'], 'integer', 'min' => 1],
            ['quality', 'integer', 'min' => 1, 'max' => 100],
            ['format', 'in', 'range' => ['jpeg', 'png']],
            [['volume', 'templateRoot', 'gotenbergUrl'], 'required'],
        ];
    }
}
