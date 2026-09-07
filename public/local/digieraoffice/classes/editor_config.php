<?php
namespace local_digieraoffice;
final class editor_config {
    public static function build(array $document, array $editor, string $secret): array {
        foreach (['fileType','key','title','url'] as $required) {
            if (empty($document[$required])) { throw new \invalid_argument_exception('Missing document.' . $required); }
        }
        if (empty($editor['callbackUrl'])) { throw new \invalid_argument_exception('Missing editor.callbackUrl'); }
        $config = [
            'document' => $document,
            'documentType' => $editor['documentType'] ?? 'word',
            'editorConfig' => $editor,
        ];
        $config['token'] = jwt::encode($config, $secret);
        return $config;
    }
}
