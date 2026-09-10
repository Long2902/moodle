<?php
namespace local_digieranative\document;

final class validator {
    private const DEFAULT_MAX_BYTES = 2097152; // 2 MiB canonical JSON safety limit.
    private const DEFAULT_MAX_NODES = 20000;
    private const MAX_TEXT_BYTES = 1048576;
    private const MAX_ATTR_TEXT = 2048;
    private const SAFE_ID = '/\A[A-Za-z0-9._:-]{1,128}\z/';
    private const SAFE_ASSET = '/\A[A-Za-z0-9_-]{1,128}\z/';
    private const SAFE_COLOR = '/\A#[0-9A-Fa-f]{6}\z/';
    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    public static function validate_json(string $json): array {
        if ($json === '' || strlen($json) > self::max_bytes()) {
            throw new \invalid_parameter_exception('Native document size is invalid');
        }

        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \invalid_parameter_exception('Invalid Native JSON');
        }

        if (!is_array($document) || ($document['type'] ?? null) !== 'worksheet') {
            throw new \invalid_parameter_exception('Native document root must be worksheet');
        }
        if (($document['version'] ?? null) !== schema::CURRENT_VERSION) {
            throw new \invalid_parameter_exception('Unsupported Native schema version');
        }
        if (!array_key_exists('content', $document) || !is_array($document['content'])) {
            throw new \invalid_parameter_exception('Native document content must be an array');
        }

        self::assert_allowed_keys($document, ['type', 'version', 'content', 'meta'], 'document');
        if (isset($document['meta']) && !is_array($document['meta'])) {
            throw new \invalid_parameter_exception('Native document meta must be an object');
        }

        $nodecount = 0;
        foreach ($document['content'] as $node) {
            self::validate_node($node, 1, $nodecount);
        }
        return $document;
    }

    private static function validate_node(mixed $node, int $depth, int &$nodecount): void {
        if (!is_array($node) || !isset($node['type']) || !is_string($node['type'])) {
            throw new \invalid_parameter_exception('Native node must contain a string type');
        }
        if ($depth > 64) {
            throw new \invalid_parameter_exception('Native document nesting is too deep');
        }
        $nodecount++;
        if ($nodecount > self::max_nodes()) {
            throw new \invalid_parameter_exception('Native document contains too many nodes');
        }

        $type = $node['type'];
        if (!in_array($type, schema::node_types(), true)) {
            throw new \invalid_parameter_exception('Unknown Native node type: ' . $type);
        }
        self::assert_allowed_keys($node, ['type', 'attrs', 'content', 'marks', 'text'], 'node');

        if ($type === 'text') {
            if (!array_key_exists('text', $node) || !is_string($node['text']) || strlen($node['text']) > self::MAX_TEXT_BYTES) {
                throw new \invalid_parameter_exception('Text node has invalid text');
            }
            if (array_key_exists('content', $node)) {
                throw new \invalid_parameter_exception('Text nodes cannot contain child nodes');
            }
        } else if (array_key_exists('text', $node)) {
            throw new \invalid_parameter_exception('Only text nodes can contain text');
        }

        if (isset($node['attrs'])) {
            if (!is_array($node['attrs'])) {
                throw new \invalid_parameter_exception('Node attrs must be an object');
            }
            self::validate_attrs($type, $node['attrs']);
        } else {
            self::validate_attrs($type, []);
        }

        if (isset($node['marks'])) {
            if ($type !== 'text' || !is_array($node['marks'])) {
                throw new \invalid_parameter_exception('Marks are only allowed on text nodes');
            }
            foreach ($node['marks'] as $mark) {
                self::validate_mark($mark);
            }
        }

        if (array_key_exists('content', $node)) {
            if (!is_array($node['content'])) {
                throw new \invalid_parameter_exception('Node content must be an array');
            }
            foreach ($node['content'] as $child) {
                self::validate_node($child, $depth + 1, $nodecount);
            }
        }
    }

    private static function validate_attrs(string $type, array $attrs): void {
        $allowed = match ($type) {
            'paragraph' => ['align'],
            'heading' => ['level', 'align'],
            'question' => ['id', 'points', 'align'],
            'instruction' => ['variant', 'align'],
            'shortAnswer', 'longAnswer' => ['questionId', 'minHeight', 'placeholder'],
            'checkbox' => ['questionId', 'optionId', 'checked', 'label'],
            'multipleChoice' => ['questionId', 'selectionMode'],
            'answerTable' => ['questionId', 'rows', 'cols'],
            'image' => ['assetKey', 'alt', 'title', 'width', 'align'],
            'orderedList' => ['order'],
            'teacherOnlyNote' => ['label'],
            'rubricAnchor' => ['id'],
            'mathInline', 'mathBlock' => ['source'],
            'tableCell' => ['colspan', 'rowspan'],
            default => [],
        };
        self::assert_allowed_keys($attrs, $allowed, $type . ' attrs');

        if (isset($attrs['level']) && (!is_int($attrs['level']) || $attrs['level'] < 1 || $attrs['level'] > 6)) {
            throw new \invalid_parameter_exception('Heading level must be between 1 and 6');
        }
        if (isset($attrs['align']) && (!is_string($attrs['align']) || !in_array($attrs['align'], self::ALIGNMENTS, true))) {
            throw new \invalid_parameter_exception('Invalid alignment');
        }
        if ($type === 'question') {
            self::require_safe_id($attrs, 'id');
        }
        if (in_array($type, schema::ANSWER_NODES, true)) {
            self::require_safe_id($attrs, 'questionId');
        }
        if ($type === 'rubricAnchor') {
            self::require_safe_id($attrs, 'id');
        }
        if ($type === 'image') {
            if (!isset($attrs['assetKey']) || !is_string($attrs['assetKey']) || !preg_match(self::SAFE_ASSET, $attrs['assetKey'])) {
                throw new \invalid_parameter_exception('Image assetKey is invalid');
            }
        }
        if (isset($attrs['points']) && (!is_int($attrs['points']) && !is_float($attrs['points']))) {
            throw new \invalid_parameter_exception('Question points must be numeric');
        }
        foreach (['minHeight', 'width', 'order', 'rows', 'cols', 'colspan', 'rowspan'] as $key) {
            if (isset($attrs[$key]) && (!is_int($attrs[$key]) || $attrs[$key] < 0 || $attrs[$key] > 10000)) {
                throw new \invalid_parameter_exception($key . ' must be a bounded integer');
            }
        }
        foreach (['placeholder', 'label', 'alt', 'title', 'variant', 'optionId', 'selectionMode', 'source'] as $key) {
            if (isset($attrs[$key]) && (!is_string($attrs[$key]) || strlen($attrs[$key]) > self::MAX_ATTR_TEXT)) {
                throw new \invalid_parameter_exception($key . ' must be bounded text');
            }
        }
        if (isset($attrs['checked']) && !is_bool($attrs['checked'])) {
            throw new \invalid_parameter_exception('checked must be boolean');
        }
    }

    private static function validate_mark(mixed $mark): void {
        if (!is_array($mark) || !isset($mark['type']) || !is_string($mark['type']) || !in_array($mark['type'], schema::MARKS, true)) {
            throw new \invalid_parameter_exception('Unknown Native mark');
        }
        self::assert_allowed_keys($mark, ['type', 'attrs'], 'mark');
        if ($mark['type'] === 'textColor') {
            $attrs = $mark['attrs'] ?? null;
            if (!is_array($attrs) || !isset($attrs['color']) || !is_string($attrs['color']) || !preg_match(self::SAFE_COLOR, $attrs['color'])) {
                throw new \invalid_parameter_exception('Invalid text color');
            }
            self::assert_allowed_keys($attrs, ['color'], 'textColor attrs');
        } else if ($mark['type'] === 'link') {
            $attrs = $mark['attrs'] ?? null;
            if (!is_array($attrs) || !isset($attrs['href']) || !is_string($attrs['href']) || !self::is_safe_link_url($attrs['href'])) {
                throw new \invalid_parameter_exception('Invalid link href');
            }
            self::assert_allowed_keys($attrs, ['href', 'target', 'rel', 'class'], 'link attrs');
            foreach (['target', 'rel', 'class'] as $key) {
                if (isset($attrs[$key]) && (!is_string($attrs[$key]) || strlen($attrs[$key]) > self::MAX_ATTR_TEXT)) {
                    throw new \invalid_parameter_exception('Invalid link attribute');
                }
            }
            if (isset($attrs['target']) && !in_array($attrs['target'], ['_blank', '_self'], true)) {
                throw new \invalid_parameter_exception('Invalid link target');
            }
        } else if (isset($mark['attrs']) && $mark['attrs'] !== []) {
            throw new \invalid_parameter_exception('This mark does not accept attrs');
        }
    }

    private static function is_safe_link_url(string $value): bool {
        if ($value === '' || strlen($value) > self::MAX_ATTR_TEXT || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return false;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return true;
        }
        if (str_starts_with($value, '#')) {
            return true;
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);
        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function require_safe_id(array $attrs, string $key): void {
        if (!isset($attrs[$key]) || !is_string($attrs[$key]) || !preg_match(self::SAFE_ID, $attrs[$key])) {
            throw new \invalid_parameter_exception($key . ' is invalid');
        }
    }

    private static function assert_allowed_keys(array $value, array $allowed, string $label): void {
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new \invalid_parameter_exception('Unexpected key in ' . $label . ': ' . (string)$key);
            }
        }
    }

    private static function max_bytes(): int {
        if (function_exists('get_config')) {
            $configured = (int)get_config('local_digieranative', 'maxdocumentbytes');
            if ($configured > 0) {
                return max(1024, min($configured, 16777216));
            }
        }
        return self::DEFAULT_MAX_BYTES;
    }

    private static function max_nodes(): int {
        if (function_exists('get_config')) {
            $configured = (int)get_config('local_digieranative', 'maxdocumentnodes');
            if ($configured > 0) {
                return max(100, min($configured, 100000));
            }
        }
        return self::DEFAULT_MAX_NODES;
    }
}
