<?php

namespace filter_digieramedia;

use local_digieramedia\marker\reference_parser;

/**
 * Render DIGIERA Media reference markers in formatted Moodle content.
 *
 * This first vertical slice intentionally implements PDF rendering only.
 * Other media types are added behind the same reference/media/version model.
 *
 * @package    filter_digieramedia
 * @copyright  2026 DIGIERA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text_filter extends \core_filters\text_filter {
    /**
     * Replace DIGIERA reference markers with rendered media.
     *
     * @param string $text HTML/text content.
     * @param array $options Filter options.
     * @return string
     */
    public function filter($text, array $options = []) {
        global $DB;

        if (!is_string($text) || $text === '' || strpos($text, '[[digiera-ref:') === false) {
            return $text;
        }

        $markers = reference_parser::extract($text);
        if (!$markers) {
            return $text;
        }

        // Replace from the end so offsets reported by the parser remain valid.
        foreach (array_reverse($markers) as $marker) {
            $replacement = $this->render_reference($marker['uuid']);
            $text = substr_replace($text, $replacement, $marker['offset'], $marker['length']);
        }

        return $text;
    }

    /**
     * Resolve one reference and render the effective media version.
     *
     * @param string $uuid Reference UUID.
     * @return string
     */
    private function render_reference(string $uuid): string {
        global $DB;

        try {
            $reference = $DB->get_record('local_digieramedia_reference', [
                'uuid' => $uuid,
                'status' => 'ACTIVE',
            ]);
            if (!$reference) {
                return $this->unavailable();
            }

            $media = $DB->get_record('local_digieramedia_media', [
                'id' => (int)$reference->mediaid,
                'status' => 'ACTIVE',
            ]);
            if (!$media) {
                return $this->unavailable();
            }

            $versionid = (int)$media->currentversionid;
            if (($reference->versionmode ?? '') === 'PINNED_VERSION' && (int)$reference->pinnedversionid > 0) {
                $versionid = (int)$reference->pinnedversionid;
            }
            if ($versionid <= 0) {
                return $this->unavailable();
            }

            $version = $DB->get_record('local_digieramedia_version', [
                'id' => $versionid,
                'mediaid' => (int)$media->id,
                'status' => 'READY',
            ]);
            if (!$version) {
                return $this->unavailable();
            }

            if (($media->mediatype ?? '') !== 'pdf' && ($version->mimetype ?? '') !== 'application/pdf') {
                return $this->unavailable();
            }

            return $this->render_pdf($media, $version);
        } catch (\Throwable $e) {
            debugging('DIGIERA Media reference render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->unavailable();
        }
    }

    /**
     * Render one PDF using the centrally configured DIGIERA PDF.js viewer.
     *
     * @param \stdClass $media Media record.
     * @param \stdClass $version Version record.
     * @return string
     */
    private function render_pdf(\stdClass $media, \stdClass $version): string {
        $cdnbase = trim((string)get_config('local_digieramedia', 'cdnbaseurl'));
        if ($cdnbase === '') {
            $cdnbase = 'https://cdn.digiera.vn';
        }
        $cdnbase = rtrim($cdnbase, '/');

        $viewer = trim((string)get_config('local_digieramedia', 'pdfviewerurl'));
        if ($viewer === '') {
            $viewer = $cdnbase . '/pdfjs/web/viewer.html';
        }

        $cdnurl = $cdnbase . '/' . ltrim((string)$version->objectkey, '/');
        $src = rtrim($viewer, '?') . '?file=' . rawurlencode($cdnurl)
            . '#zoom=page-width&scrollMode=page&spread=none';
        $title = trim((string)($media->name ?? ''));
        if ($title === '') {
            $title = trim((string)($version->displayfilename ?? 'PDF'));
        }

        return '<div class="digiera-media digiera-media-pdf" data-digiera-media="pdf">'
            . '<iframe class="digiera-media-pdf__viewer"'
            . ' src="' . s($src) . '"'
            . ' title="' . s($title) . '"'
            . ' loading="lazy"'
            . ' allowfullscreen="allowfullscreen"'
            . ' style="width:100%;height:80vh;min-height:600px;border:0"></iframe>'
            . '</div>';
    }

    /**
     * Non-sensitive fallback for unresolved/broken media.
     *
     * @return string
     */
    private function unavailable(): string {
        return '<div class="digiera-media digiera-media-unavailable" role="status">'
            . 'Học liệu hiện không khả dụng.'
            . '</div>';
    }
}
