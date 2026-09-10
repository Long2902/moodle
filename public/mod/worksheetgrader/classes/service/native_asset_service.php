<?php
namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

final class native_asset_service {
    public const FILEAREA = 'attemptimage';
    public const MAX_BYTES = 5242880;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store_upload(\context_module $context, int $attemptid, array $upload, int $userid): array {
        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpname = (string)($upload['tmp_name'] ?? '');
        $size = (int)($upload['size'] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $tmpname === '' || !is_uploaded_file($tmpname)) {
            throw new \moodle_exception('Image upload failed');
        }
        $sitemax = (int)get_max_upload_file_size();
        $maxbytes = $sitemax > 0 ? min(self::MAX_BYTES, $sitemax) : self::MAX_BYTES;
        if ($size <= 0 || $size > $maxbytes) {
            throw new \moodle_exception('Image must be between 1 byte and 5 MiB');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpname);
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            throw new \moodle_exception('Only JPEG, PNG and WebP images are supported');
        }

        $assetkey = 'a_' . bin2hex(random_bytes(16));
        $filename = $assetkey . '.' . self::MIME_EXTENSIONS[$mime];
        $original = clean_filename((string)($upload['name'] ?? 'image'));
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_worksheetgrader',
            'filearea' => self::FILEAREA,
            'itemid' => $attemptid,
            'filepath' => '/native/',
            'filename' => $filename,
            'userid' => $userid,
            'author' => fullname($user),
            'license' => 'allrightsreserved',
        ], $tmpname);

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($original, PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($context, $attemptid, $file),
        ];
    }

    public static function asset_urls(\context_module $context, int $attemptid): array {
        $urls = [];
        foreach (get_file_storage()->get_area_files(
            $context->id,
            'mod_worksheetgrader',
            self::FILEAREA,
            $attemptid,
            'id ASC',
            false
        ) as $file) {
            if ($file->is_directory() || $file->get_filepath() !== '/native/') {
                continue;
            }
            $key = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            if (preg_match('/\Aa_[a-f0-9]{32}\z/', $key)) {
                $urls[$key] = self::file_url($context, $attemptid, $file);
            }
        }
        return $urls;
    }

    private static function file_url(\context_module $context, int $attemptid, \stored_file $file): string {
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'mod_worksheetgrader',
            self::FILEAREA,
            $attemptid,
            $file->get_filepath(),
            $file->get_filename(),
            false
        )->out(false);
    }
}
