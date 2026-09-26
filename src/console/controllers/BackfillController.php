<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\console\controllers;

use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Console;
use craft\helpers\Queue;
use larsmarkusstudio\ogimages\jobs\GenerateOgImageJob;
use larsmarkusstudio\ogimages\Plugin;
use yii\console\ExitCode;

/**
 * Queues image jobs for existing entries.
 *
 *     craft lms-og-images/backfill [--section=handle] [--site=handle] [--limit=100] [--force]
 *
 * Without --force only entry/site pairs without an image are queued. With --force every
 * pair is queued and each job still skips when its hash is unchanged, so after a template
 * change only the images that actually look different are rendered.
 */
class BackfillController extends Controller
{
    /** Only this section (must be one of the configured sections). */
    public ?string $section = null;

    /** Only this site handle. */
    public ?string $site = null;

    /** Queue at most this many jobs. */
    public ?int $limit = null;

    /** Queue entries that already have an image too. */
    public bool $force = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'section', 'site', 'limit', 'force'];
    }

    public function actionIndex(): int
    {
        $plugin = Plugin::getInstance();
        $sections = $plugin->getSettings()->sections;

        if ($this->section !== null && !in_array($this->section, $sections, true)) {
            $this->stderr("Section '$this->section' is not in the configured sections: " . implode(', ', $sections) . "\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        // Same rule as the save listener: enabled for the site, including pending and expired
        $entries = Entry::find()
            ->section($this->section ?? $sections)
            ->site($this->site ?? '*')
            ->status([Entry::STATUS_LIVE, Entry::STATUS_PENDING, Entry::STATUS_EXPIRED])
            ->orderBy(['elements.id' => SORT_ASC])
            ->each();

        $queued = $skipped = 0;
        foreach ($entries as $entry) {
            if ($this->limit !== null && $queued >= $this->limit) {
                break;
            }
            if (!$this->force && $plugin->images->current($entry)) {
                $skipped++;
                continue;
            }

            Queue::push(new GenerateOgImageJob(['entryId' => $entry->id, 'siteId' => $entry->siteId]));
            $queued++;
        }

        $this->stdout("Queued $queued job(s)" . ($skipped ? ", skipped $skipped with an image (use --force to include them)" : '') . ".\n");
        if ($queued) {
            $this->stdout("Run them with a queue worker, or: craft queue/run\n", Console::FG_GREY);
        }

        return ExitCode::OK;
    }
}
