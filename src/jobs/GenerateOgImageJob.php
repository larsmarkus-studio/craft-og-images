<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\jobs;

use Craft;
use craft\elements\Entry;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use larsmarkusstudio\ogimages\Plugin;
use Throwable;
use yii\queue\RetryableJobInterface;

/**
 * Renders and stores the OG image for one entry in one site.
 */
class GenerateOgImageJob extends BaseJob implements RetryableJobInterface
{
    public int $entryId;
    public int $siteId;

    public function execute($queue): void
    {
        $mutex = Craft::$app->getMutex();
        $lock = "lms-og-images:$this->entryId:$this->siteId";

        // Another job is rendering this entry + site: try again shortly instead of racing it
        if (!$mutex->acquire($lock)) {
            Queue::push(new self(['entryId' => $this->entryId, 'siteId' => $this->siteId]), null, 10);
            return;
        }

        try {
            $entry = Entry::find()->id($this->entryId)->siteId($this->siteId)->status(null)->one();

            // Deleted, or disabled (globally or for this site) since the job was queued
            if (!$entry || !$entry->enabled || !$entry->getEnabledForSite()) {
                return;
            }

            Plugin::getInstance()->images->generate($entry);
        } catch (Throwable $e) {
            Craft::error("Entry $this->entryId, site $this->siteId: {$e->getMessage()}", 'lms-og-images');
            throw $e;
        } finally {
            $mutex->release($lock);
        }
    }

    public function getTtr(): int
    {
        return 60;
    }

    public function canRetry($attempt, $error): bool
    {
        // ponytail: immediate retries, Craft's queue has no backoff; re-push with a delay if Gotenberg hiccups prove longer
        return $attempt < 3;
    }

    protected function defaultDescription(): ?string
    {
        return "Generating OG image for entry $this->entryId";
    }
}
