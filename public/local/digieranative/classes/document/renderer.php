<?php
namespace local_digieranative\document;

final class renderer {
    private const MODES = ['student', 'teacher', 'submission', 'preview'];

    public static function render_json(string $json, string $mode = 'student', array $asseturls = []): string {
        if (!in_array($mode, self::MODES, true)) {
            throw new \invalid_parameter_exception('Invalid Native render mode');
        }
        $document = validator::validate_json($json);
        return '<div class="dgn-document" data-schema-version="' . schema::CURRENT_VERSION . '">' .
            self::children($document, $mode, $asseturls) . '</div>';
    }

    private static function children(array $node, string $mode, array $asseturls): string {
        $html = '';
        foreach (($node['content'] ?? []) as $child) {
            $html .= self::render_node($child, $mode, $asseturls);
        }
        return $html;
    }

    private static function render_node(array $node, string $mode, array $asseturls): string {
        return match ($node['type']) {
            'text' => self::render_text($node),
            'paragraph' => self::wrap_textual('p', 'dgn-paragraph', $node, $mode, $asseturls),
            'heading' => self::heading($node, $mode, $asseturls),
            'orderedList' => self::list_node('ol', $node, $mode, $asseturls),
            'unorderedList' => self::list_node('ul', $node, $mode, $asseturls),
            'listItem' => '<li>' . self::children($node, $mode, $asseturls) . '</li>',
            'table' => '<div class="dgn-table-wrap"><table class="dgn-table"><tbody>' . self::children($node, $mode, $asseturls) . '</tbody></table></div>',
            'tableRow' => '<tr>' . self::children($node, $mode, $asseturls) . '</tr>',
            'tableCell' => self::table_cell($node, $mode, $asseturls),
            'image' => self::image($node, $asseturls),
            'horizontalRule' => '<hr class="dgn-horizontal-rule">',
            'pageBreak' => '<div class="dgn-page-break" role="separator" aria-label="Page break"></div>',
            'instruction' => self::instruction($node, $mode, $asseturls),
            'question' => self::question($node, $mode, $asseturls),
            'shortAnswer' => self::answer_box($node, 'short'),
            'longAnswer' => self::answer_box($node, 'long'),
            'checkbox' => self::checkbox($node),
            'multipleChoice' => self::multiple_choice($node, $mode, $asseturls),
            'answerTable' => self::answer_table($node),
            'teacherOnlyNote' => $mode === 'teacher' ? self::teacher_note($node, $mode, $asseturls) : '',
            'rubricAnchor' => self::rubric_anchor($node, $mode),
            'mathInline' => self::math($node, false),
            'mathBlock' => self::math($node, true),
            'hardBreak' => '<br>',
            default => '', // Validator makes this unreachable.
        };
    }

    private static function render_text(array $node): string {
        $html = self::escape((string)($node['text'] ?? ''));
        foreach (($node['marks'] ?? []) as $mark) {
            $html = match ($mark['type']) {
                'bold' => '<strong>' . $html . '</strong>',
                'italic' => '<em>' . $html . '</em>',
                'underline' => '<u>' . $html . '</u>',
                'strike' => '<s>' . $html . '</s>',
                'textColor' => '<span style="color:' . self::escape((string)$mark['attrs']['color']) . '">' . $html . '</span>',
                default => $html,
            };
        }
        return $html;
    }

    private static function wrap_textual(string $tag, string $class, array $node, string $mode, array $asseturls): string {
        $align = self::alignment_class($node['attrs']['align'] ?? '');
        return '<' . $tag . ' class="' . $class . $align . '">' . self::children($node, $mode, $asseturls) . '</' . $tag . '>';
    }

    private static function heading(array $node, string $mode, array $asseturls): string {
        $level = (int)($node['attrs']['level'] ?? 2);
        $level = max(1, min(6, $level));
        $align = self::alignment_class($node['attrs']['align'] ?? '');
        return '<h' . $level . ' class="dgn-heading' . $align . '">' . self::children($node, $mode, $asseturls) . '</h' . $level . '>';
    }

    private static function list_node(string $tag, array $node, string $mode, array $asseturls): string {
        $attrs = '';
        if ($tag === 'ol' && isset($node['attrs']['order']) && (int)$node['attrs']['order'] > 1) {
            $attrs = ' start="' . (int)$node['attrs']['order'] . '"';
        }
        return '<' . $tag . ' class="dgn-list"' . $attrs . '>' . self::children($node, $mode, $asseturls) . '</' . $tag . '>';
    }

    private static function table_cell(array $node, string $mode, array $asseturls): string {
        $attrs = $node['attrs'] ?? [];
        $span = '';
        if (!empty($attrs['colspan']) && (int)$attrs['colspan'] > 1) {
            $span .= ' colspan="' . (int)$attrs['colspan'] . '"';
        }
        if (!empty($attrs['rowspan']) && (int)$attrs['rowspan'] > 1) {
            $span .= ' rowspan="' . (int)$attrs['rowspan'] . '"';
        }
        return '<td' . $span . '>' . self::children($node, $mode, $asseturls) . '</td>';
    }

    private static function image(array $node, array $asseturls): string {
        $attrs = $node['attrs'] ?? [];
        $key = (string)($attrs['assetKey'] ?? '');
        if (!array_key_exists($key, $asseturls)) {
            return '<div class="dgn-image-missing" data-asset-key="' . self::escape($key) . '">Image unavailable</div>';
        }
        $url = self::safe_asset_url($asseturls[$key]);
        if ($url === null) {
            return '<div class="dgn-image-missing" data-asset-key="' . self::escape($key) . '">Image unavailable</div>';
        }
        $alt = self::escape((string)($attrs['alt'] ?? ''));
        $title = isset($attrs['title']) ? ' title="' . self::escape((string)$attrs['title']) . '"' : '';
        $width = isset($attrs['width']) && (int)$attrs['width'] > 0 ? ' width="' . (int)$attrs['width'] . '"' : '';
        $align = self::alignment_class($attrs['align'] ?? '');
        return '<figure class="dgn-image' . $align . '"><img src="' . self::escape($url) . '" alt="' . $alt . '"' . $title . $width . ' loading="lazy"></figure>';
    }

    private static function instruction(array $node, string $mode, array $asseturls): string {
        $variant = self::escape((string)($node['attrs']['variant'] ?? 'info'));
        return '<aside class="dgn-instruction" data-variant="' . $variant . '">' . self::children($node, $mode, $asseturls) . '</aside>';
    }

    private static function question(array $node, string $mode, array $asseturls): string {
        $id = self::escape((string)($node['attrs']['id'] ?? ''));
        $points = isset($node['attrs']['points']) ? ' data-points="' . self::escape((string)$node['attrs']['points']) . '"' : '';
        $align = self::alignment_class($node['attrs']['align'] ?? '');
        return '<section class="dgn-question' . $align . '" data-question-id="' . $id . '"' . $points . '>' . self::children($node, $mode, $asseturls) . '</section>';
    }

    private static function answer_box(array $node, string $kind): string {
        $attrs = $node['attrs'] ?? [];
        $questionid = self::escape((string)($attrs['questionId'] ?? ''));
        $minheight = max(32, min(1200, (int)($attrs['minHeight'] ?? ($kind === 'long' ? 160 : 48))));
        $placeholder = self::escape((string)($attrs['placeholder'] ?? ''));
        return '<div class="dgn-answer dgn-answer-' . $kind . '" data-digiera-answer="' . $kind . '" data-question-id="' . $questionid . '" style="min-height:' . $minheight . 'px"' .
            ($placeholder !== '' ? ' data-placeholder="' . $placeholder . '"' : '') . '></div>';
    }

    private static function checkbox(array $node): string {
        $attrs = $node['attrs'] ?? [];
        $questionid = self::escape((string)($attrs['questionId'] ?? ''));
        $optionid = self::escape((string)($attrs['optionId'] ?? ''));
        $label = self::escape((string)($attrs['label'] ?? ''));
        return '<div class="dgn-answer dgn-checkbox" data-digiera-answer="checkbox" data-question-id="' . $questionid . '" data-option-id="' . $optionid . '"><span class="dgn-checkbox-box" aria-hidden="true">□</span><span>' . $label . '</span></div>';
    }

    private static function multiple_choice(array $node, string $mode, array $asseturls): string {
        $attrs = $node['attrs'] ?? [];
        $questionid = self::escape((string)($attrs['questionId'] ?? ''));
        $selectionmode = self::escape((string)($attrs['selectionMode'] ?? 'single'));
        return '<div class="dgn-answer dgn-multiple-choice" data-digiera-answer="multiple-choice" data-question-id="' . $questionid . '" data-selection-mode="' . $selectionmode . '">' . self::children($node, $mode, $asseturls) . '</div>';
    }

    private static function answer_table(array $node): string {
        $attrs = $node['attrs'] ?? [];
        $questionid = self::escape((string)($attrs['questionId'] ?? ''));
        $rows = max(1, min(50, (int)($attrs['rows'] ?? 2)));
        $cols = max(1, min(20, (int)($attrs['cols'] ?? 2)));
        $html = '<table class="dgn-answer-table" data-digiera-answer="table" data-question-id="' . $questionid . '"><tbody>';
        for ($r = 0; $r < $rows; $r++) {
            $html .= '<tr>';
            for ($c = 0; $c < $cols; $c++) {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    private static function teacher_note(array $node, string $mode, array $asseturls): string {
        $label = self::escape((string)($node['attrs']['label'] ?? 'Teacher note'));
        return '<aside class="dgn-teacher-note"><strong>' . $label . '</strong>' . self::children($node, $mode, $asseturls) . '</aside>';
    }

    private static function rubric_anchor(array $node, string $mode): string {
        if ($mode !== 'teacher' && $mode !== 'preview') {
            return '';
        }
        return '<span class="dgn-rubric-anchor" data-rubric-id="' . self::escape((string)($node['attrs']['id'] ?? '')) . '"></span>';
    }

    private static function math(array $node, bool $block): string {
        $source = self::escape((string)($node['attrs']['source'] ?? ''));
        $tag = $block ? 'div' : 'span';
        return '<' . $tag . ' class="dgn-math ' . ($block ? 'dgn-math-block' : 'dgn-math-inline') . '" data-math-source="' . $source . '">' . $source . '</' . $tag . '>';
    }

    private static function safe_asset_url(mixed $value): ?string {
        $url = trim((string)$value);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') ? null : $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme']) || !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        return $url;
    }

    private static function alignment_class(mixed $align): string {
        return in_array($align, ['left', 'center', 'right', 'justify'], true) ? ' dgn-align-' . $align : '';
    }

    private static function escape(string $value): string {
        if (function_exists('s')) {
            return s($value);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
