<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use larsmarkusstudio\ogimages\helpers\Filename;
use larsmarkusstudio\ogimages\Plugin;
use larsmarkusstudio\ogimages\services\Images;
use Throwable;
use yii\base\Exception;
use yii\console\ExitCode;

/**
 * Renders one entry's OG image synchronously, for setup and debugging. Nothing is saved to the volume.
 *
 *     craft lms-og-images/test 123 [--site=handle] [--html]
 */
class TestController extends Controller
{
    /** Site handle. Defaults to the first site the entry exists in. */
    public ?string $site = null;

    /** Print the rendered HTML instead of calling Gotenberg. */
    public bool $html = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'site', 'html'];
    }

    public function actionIndex(int $entryId): int
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        try {
            $entry = Entry::find()->id($entryId)->site($this->site ?? '*')->unique()->status(null)->one();
            if (!$entry) {
                throw new Exception("Entry $entryId not found" . ($this->site ? " in site '$this->site'" : '') . '.');
            }

            $volume = $plugin->images->volume();
            $this->line('Entry', "$entry->title (#$entry->id, site {$entry->getSite()->handle})");
            $this->line('Volume', "$volume->handle → {$volume->getRootUrl()}");

            $rendered = $plugin->images->render($entry);
            $files = $plugin->images->assetFiles();
            $hash = substr($plugin->images->hash($rendered['html'], $files), 0, Filename::HASH_LENGTH);
            $current = $plugin->images->current($entry);

            $this->line('Template', $rendered['template'] . ($files ? ' + ' . implode(', ', array_keys($files)) : ''));
            $this->line('Hash', $hash);
            $this->line('Current', $current?->filename ?? 'none');
            if ($current) {
                $upToDate = (Filename::parse($current->filename)['hash'] ?? null) === $hash;
                $this->line('Up to date', $upToDate ? 'yes' : 'no, a save would regenerate it');
            }

            if ($this->html) {
                $this->stdout("\n" . $rendered['html'] . "\n");
                return ExitCode::OK;
            }

            $start = microtime(true);
            $bytes = $plugin->gotenberg->screenshot($rendered['html'], $files);
            $ms = (int)round((microtime(true) - $start) * 1000);

            $size = getimagesizefromstring($bytes);
            $path = Craft::$app->getPath()->getTempPath() . '/' . Filename::build(Images::TYPE, $entry->id, $entry->siteId, $hash, $settings->format);
            file_put_contents($path, $bytes);

            $this->line('Rendered', sprintf('%dx%d %s, %s KB in %d ms', $size[0] ?? 0, $size[1] ?? 0, $settings->format, number_format(strlen($bytes) / 1024, 1), $ms));
            $this->line('Saved to', $path);
        } catch (Throwable $e) {
            $this->stderr("\n" . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    private function line(string $label, string $value): void
    {
        $this->stdout(str_pad($label, 12), Console::FG_GREY);
        $this->stdout($value . "\n");
    }
}
