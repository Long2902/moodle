<?php
namespace local_digieraoffice;
final class document_key {
    public static function make(string $component, int $ownerid, int $revision, string $contenthash = ''): string {
        if ($component === '' || $ownerid < 1 || $revision < 1) { throw new \invalid_argument_exception('Invalid document key input'); }
        $digest = hash('sha256', $component . ':' . $ownerid . ':' . $revision . ':' . $contenthash);
        return substr(preg_replace('/[^A-Za-z0-9._=-]/', '', $component), 0, 24) . '-' . $ownerid . '-' . $revision . '-' . substr($digest, 0, 32);
    }
}
