<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Console;
use larsmarkusstudio\ogimages\helpers\Filename;
use larsmarkusstudio\ogimages\Plugin;
use larsmarkusstudio\ogimages\services\Images;
use yii\console\ExitCode;

/**
 * Deletes OG images nobody needs anymore.
 *
 *     craft lms-og-images/sweep [--dry-run]
 *
 * Only looks at the configured volume's root folder, and only at files whose name
 * matches the OG pattern exactly. Deletes an image when its entry no longer exists in
 * that site or isn't in a configured section, or when a newer image for the same
 * entry + site exists. Images of disabled entries are kept. Anything referenced from a
 * relation field is never deleted.
 */
class SweepController extends Controller
{
    /** List what would be deleted without deleting it. */
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'dryRun'];
    }

    public function optionAliases(): array
    {
        return ['n' => 'dryRun'];
    }

    public function actionIndex(): int
    {
        $plugin = Plugin::getInstance();
        $volume = $plugin->images->volume();
        $rootFolder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);

        /** @var Asset[] $assets newest first, so the first per entry + site is the one to keep */
        $assets = Asset::find()
            ->volumeId($volume->id)
            ->folderId($rootFolder->id)
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(null)
            ->all();

        $ours = [];
        foreach ($assets as $asset) {
            $parsed = Filename::parse($asset->filename);
            if ($parsed && $parsed['type'] === Images::TYPE) {
                $ours[] = [$asset, $parsed];
            }
        }

        // Entry/site pairs that may keep an image: existing, in a configured section, any status
        $entryIds = array_unique(array_map(fn($item) => $item[1]['entryId'], $ours));
        $valid = [];
        if ($entryIds) {
            $rows = Entry::find()->id($entryIds)->section($plugin->getSettings()->sections)->site('*')->status(null)
                ->select(['elements.id', 'elements_sites.siteId'])->asArray()->all();
            foreach ($rows as $row) {
                $valid["{$row['id']}-{$row['siteId']}"] = true;
            }
        }

        $referenced = array_flip((new Query())
            ->select('targetId')->distinct()
            ->from(Table::RELATIONS)
            ->where(['targetId' => array_map(fn($item) => $item[0]->id, $ours)])
            ->column());

        $kept = [];
        $deleted = 0;
        foreach ($ours as [$asset, $parsed]) {
            $key = "{$parsed['entryId']}-{$parsed['siteId']}";

            $reason = match (true) {
                !isset($valid[$key]) => 'entry gone or section not configured',
                isset($kept[$key]) => 'older version',
                default => null,
            };
            if ($reason === null) {
                $kept[$key] = true;
                continue;
            }
            if (isset($referenced[$asset->id])) {
                $this->stdout("keep    $asset->filename (referenced by a relation field)\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout(($this->dryRun ? 'would delete ' : 'delete  ') . "$asset->filename ($reason)\n");
            if (!$this->dryRun) {
                Craft::$app->getElements()->deleteElement($asset, true);
            }
            $deleted++;
        }

        $this->stdout(sprintf(
            "%s %d, kept %d current image(s) in %s.\n",
            $this->dryRun ? 'Would delete' : 'Deleted',
            $deleted,
            count($kept),
            $volume->handle,
        ));

        return ExitCode::OK;
    }
}
