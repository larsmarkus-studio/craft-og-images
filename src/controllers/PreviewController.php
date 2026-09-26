<?php

declare(strict_types=1);

namespace larsmarkusstudio\ogimages\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use larsmarkusstudio\ogimages\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Previews the OG template while editing. Add a preview target to the section with
 * URL format `og-preview/{canonicalId}/{siteId}`.
 *
 * `og-preview/<entryId>/<siteId>` renders the template as HTML, scaled to fit the preview pane,
 * with the unsaved changes Craft's preview token carries. `?render=1` returns the real
 * image from Gotenberg instead. Only answers requests that carry a valid preview token,
 * so nothing is public and full-page caches never store it.
 */
class PreviewController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index', 'asset'];

    private const FIT_SCRIPT = <<<'HTML'
<script>(() => { const fit = () => document.documentElement.style.zoom = Math.min(1, innerWidth / 1200); fit(); addEventListener('resize', fit); })();</script>
HTML;

    public function actionIndex(int $entryId, int $siteId): Response
    {
        // Craft validates the token (an invalid one never reaches this action) and swaps in the draft being edited
        if (!$this->request->getHadToken()) {
            throw new NotFoundHttpException();
        }

        $entry = Entry::find()
            ->id($entryId)
            ->siteId($siteId)
            ->status(null)
            ->one();
        if (!$entry) {
            throw new NotFoundHttpException();
        }

        $plugin = Plugin::getInstance();
        $html = $plugin->images->render($entry)['html'];
        $this->response->headers->set('X-Robots-Tag', 'noindex');

        if ($this->request->getQueryParam('render')) {
            $this->response->format = Response::FORMAT_RAW;
            $this->response->headers->set('Content-Type', 'image/' . $plugin->getSettings()->format);
            $this->response->data = $plugin->gotenberg->screenshot($html, $plugin->images->assetFiles());

            return $this->response;
        }

        // Relative asset URLs resolve to og-preview/<file>, like next to index.html in Gotenberg
        // strtok: Craft appends the preview token to site URLs; the base doesn't need it
        $base = Html::tag('base', '', ['href' => strtok(UrlHelper::siteUrl('og-preview/', siteId: $siteId), '?')]);
        $html = preg_replace('/<head[^>]*>/i', '$0' . $base, $html, 1, $count);
        if (!$count) {
            $html = $base . $html;
        }
        $html = str_contains($html, '</body>')
            ? substr_replace($html, self::FIT_SCRIPT, strrpos($html, '</body>'), 0)
            : $html . self::FIT_SCRIPT;

        return $this->asRaw($html);
    }

    /**
     * Serves files from `templates/{templateRoot}/assets/`, so relative URLs in the
     * template (fonts, logos) resolve in the preview the same way they do in Gotenberg.
     */
    public function actionAsset(string $file): Response
    {
        $path = Plugin::getInstance()->images->assetFiles()[$file] ?? null;
        if (!$path) {
            throw new NotFoundHttpException();
        }

        return $this->response->sendFile($path, $file, ['inline' => true]);
    }
}
