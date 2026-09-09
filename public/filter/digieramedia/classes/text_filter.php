<?php

namespace filter_digieramedia;

use local_digieramedia\marker\reference_parser;

final class text_filter extends \core_filters\text_filter {
    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '' || strpos($text, '[[digiera-ref:') === false) {
            return $text;
        }
        $markers = reference_parser::extract($text);
        if (!$markers) {
            return $text;
        }
        foreach (array_reverse($markers) as $marker) {
            $text = substr_replace($text, $this->render_reference($marker['uuid']), $marker['offset'], $marker['length']);
        }
        return $text;
    }

    private function render_reference(string $uuid): string {
        global $DB;
        try {
            $reference = $DB->get_record('local_digieramedia_reference', ['uuid' => $uuid]);
            if (!$reference || !in_array((string)$reference->status, ['ACTIVE', 'DRAFT'], true)) {
                return $this->unavailable();
            }
            $media = $DB->get_record('local_digieramedia_media', ['id' => (int)$reference->mediaid]);
            if (!$media || !in_array((string)$media->status, ['ACTIVE', 'TRASHED'], true)) {
                return $this->unavailable();
            }
            $versionid = (int)$media->currentversionid;
            if (($reference->versionmode ?? '') === 'PINNED_VERSION' && (int)$reference->pinnedversionid > 0) {
                $versionid = (int)$reference->pinnedversionid;
            }
            $version = $DB->get_record('local_digieramedia_version', [
                'id' => $versionid,
                'mediaid' => (int)$media->id,
                'status' => 'READY',
            ]);
            if (!$version) {
                return $this->unavailable();
            }
            $type = strtolower((string)($media->mediatype ?? 'generic'));
            return match ($type) {
                'pdf' => $this->render_pdf($media, $version, $reference),
                'video' => $this->render_video($media, $version),
                'image' => $this->render_image($media, $version, $reference),
                'audio' => $this->render_audio($media, $version),
                default => $this->render_generic($media, $version),
            };
        } catch (\Throwable $e) {
            debugging('DIGIERA Media reference render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->unavailable();
        }
    }

    private function cdn_url(\stdClass $version): string {
        $cdnbase = trim((string)get_config('local_digieramedia', 'cdnbaseurl')) ?: 'https://cdn.digiera.vn';
        return rtrim($cdnbase, '/') . '/' . ltrim((string)$version->objectkey, '/');
    }

    private function title(\stdClass $media, \stdClass $version): string {
        $title = trim((string)($media->name ?? ''));
        return $title !== '' ? $title : trim((string)($version->displayfilename ?? 'Học liệu DIGIERA'));
    }

    private function render_pdf(\stdClass $media, \stdClass $version, \stdClass $reference): string {
        $cdnurl = $this->cdn_url($version);
        $profile = strtolower((string)($reference->displayprofile ?? 'embedded'));
        if (in_array($profile, ['link', 'pdf_link'], true)) {
            return $this->render_link($media, $version);
        }
        $cdnbase = trim((string)get_config('local_digieramedia', 'cdnbaseurl')) ?: 'https://cdn.digiera.vn';
        $viewer = trim((string)get_config('local_digieramedia', 'pdfviewerurl')) ?: rtrim($cdnbase, '/') . '/pdfjs/web/viewer.html';
        $src = rtrim($viewer, '?') . '?file=' . rawurlencode($cdnurl) . '#zoom=page-width&scrollMode=page&spread=none';
        $height = in_array($profile, ['compact', 'pdf_compact'], true) ? '52vh' : '80vh';
        return '<div class="digiera-media digiera-media-pdf" data-digiera-media="pdf">'
            . '<iframe class="digiera-media-pdf__viewer" src="' . s($src) . '" title="' . s($this->title($media, $version)) . '"'
            . ' loading="lazy" allowfullscreen="allowfullscreen" style="display:block;width:100%;height:' . $height . ';min-height:420px;border:0"></iframe>'
            . '</div>';
    }

    private function render_video(\stdClass $media, \stdClass $version): string {
        return '<div class="digiera-media digiera-media-video" data-digiera-media="video">'
            . '<video controls playsinline preload="metadata" style="display:block;width:100%;height:auto;max-width:100%">'
            . '<source src="' . s($this->cdn_url($version)) . '" type="' . s((string)$version->mimetype) . '">'
            . '</video></div>';
    }

    private function render_image(\stdClass $media, \stdClass $version, \stdClass $reference): string {
        $alt = trim((string)($reference->alttext ?? '')) ?: $this->title($media, $version);
        $caption = trim((string)($reference->caption ?? ''));
        $html = '<figure class="digiera-media digiera-media-image" data-digiera-media="image">'
            . '<img src="' . s($this->cdn_url($version)) . '" alt="' . s($alt) . '" loading="lazy" style="display:block;max-width:100%;height:auto">';
        if ($caption !== '') {
            $html .= '<figcaption>' . s($caption) . '</figcaption>';
        }
        return $html . '</figure>';
    }

    private function render_audio(\stdClass $media, \stdClass $version): string {
        return '<div class="digiera-media digiera-media-audio" data-digiera-media="audio">'
            . '<audio controls preload="metadata" style="width:100%"><source src="' . s($this->cdn_url($version))
            . '" type="' . s((string)$version->mimetype) . '"></audio></div>';
    }

    private function render_generic(\stdClass $media, \stdClass $version): string {
        return $this->render_link($media, $version);
    }

    private function render_link(\stdClass $media, \stdClass $version): string {
        $name = $this->title($media, $version);
        return '<div class="digiera-media digiera-media-file" data-digiera-media="file">'
            . '<a href="' . s($this->cdn_url($version)) . '" target="_blank" rel="noopener noreferrer">' . s($name) . '</a>'
            . '<span class="digiera-media-file__meta"> (' . s((string)$version->mimetype) . ')</span></div>';
    }

    private function unavailable(): string {
        return '<div class="digiera-media digiera-media-unavailable" role="status">Học liệu hiện không khả dụng.</div>';
    }
}
