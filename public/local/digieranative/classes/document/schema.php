<?php
namespace local_digieranative\document;

final class schema {
    public const CURRENT_VERSION = 1;

    public const BLOCK_NODES = [
        'paragraph', 'heading', 'orderedList', 'unorderedList', 'listItem',
        'table', 'tableRow', 'tableCell', 'image', 'horizontalRule', 'pageBreak',
        'instruction', 'question', 'shortAnswer', 'longAnswer', 'checkbox',
        'multipleChoice', 'answerTable', 'teacherOnlyNote', 'rubricAnchor',
        'mathBlock',
    ];

    public const INLINE_NODES = ['text', 'hardBreak', 'mathInline'];
    public const MARKS = [
        'bold', 'italic', 'underline', 'strike',
        'textColor', 'fontFamily', 'fontSize', 'highlight', 'link',
    ];

    public const ANSWER_NODES = ['shortAnswer', 'longAnswer', 'checkbox', 'multipleChoice', 'answerTable'];

    public static function node_types(): array {
        return array_merge(self::BLOCK_NODES, self::INLINE_NODES);
    }
}
