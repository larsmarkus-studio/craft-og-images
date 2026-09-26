<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages;

use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use craft\services\Elements;
use larsmarkusstudio\ogimages\jobs\GenerateOgImageJob;
use larsmarkusstudio\ogimages\models\Settings;
use larsmarkusstudio\ogimages\services\Gotenberg;
use larsmarkusstudio\ogimages\services\Images;
use yii\base\Event;

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

    public function init(): void
    {
        parent::init();

        // Fires after the save transaction commits
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function (ElementEvent $event) {
            if ($this->shouldGenerate($event->element)) {
                $this->queue($event->element);
            }
        });
    }

    /**
     * Published saves only: no drafts (including autosaves), revisions, propagated
     * copies, resaves or disabled entries. Pending entries do get an image, since
     * going live on their post date doesn't trigger a save.
     */
    public function shouldGenerate(mixed $element): bool
    {
        return $element instanceof Entry
            && !ElementHelper::isDraftOrRevision($element)
            && !$element->propagating
            && !$element->resaving
            && $element->enabled
            && in_array($element->getSection()?->handle, $this->getSettings()->sections, true);
    }

    /**
     * One job per site the entry is enabled in: non-translatable fields propagate, so any
     * of them may have changed. Unchanged sites cost one Twig render.
     */
    public function queue(Entry $entry): void
    {
        $siteIds = Entry::find()->id($entry->id)->site('*')->status(null)->select(['elements_sites.siteId'])
            ->andWhere(['elements_sites.enabled' => true])->column();

        foreach ($siteIds as $siteId) {
            Queue::push(new GenerateOgImageJob(['entryId' => $entry->id, 'siteId' => (int)$siteId]));
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
