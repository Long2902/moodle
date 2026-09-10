<?php
namespace local_worksheetlibrary\service;

defined('MOODLE_INTERNAL') || die();

final class native_asset_service {
    public const FILEAREA = 'nativeasset';
    public const MAX_BYTES = 5242880;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store_upload(int $versionid, array $upload, int $userid): array {
        global $DB, $CFG;

        $version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
        if ($version->state !== 'draft') {
            throw new \moodle_exception('Only draft Native versions accept images');
        }
        $item = $DB->get_record('wslib_item', ['id' => $version->itemid], '*', MUST_EXIST);
        if ($item->kind !== 'native') {
            throw new \moodle_exception('Worksheet is not Native');
        }

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
        $context = \context_system::instance();
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'local_worksheetlibrary',
            'filearea' => self::FILEAREA,
            'itemid' => $versionid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'author' => fullname($user),
            'license' => 'allrightsreserved',
        ], $tmpname);

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($original, PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($versionid, $file),
        ];
    }

    public static function asset_urls(int $versionid): array {
        $context = \context_system::instance();
        $urls = [];
        foreach (get_file_storage()->get_area_files(
            $context->id,
            'local_worksheetlibrary',
            self::FILEAREA,
            $versionid,
            'id ASC',
            false
        ) as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $key = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            if (preg_match('/\Aa_[a-f0-9]{32}\z/', $key)) {
                $urls[$key] = self::file_url($versionid, $file);
            }
        }
        return $urls;
    }

    public static function clone_assets(int $sourceversionid, int $targetversionid): void {
        $context = \context_system::instance();
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'local_worksheetlibrary', self::FILEAREA, $targetversionid);
        foreach ($fs->get_area_files(
            $context->id,
            'local_worksheetlibrary',
            self::FILEAREA,
            $sourceversionid,
            'id ASC',
            false
        ) as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $record = [
                'contextid' => $context->id,
                'component' => 'local_worksheetlibrary',
                'filearea' => self::FILEAREA,
                'itemid' => $targetversionid,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];
            $fs->create_file_from_storedfile($record, $file);
        }
    }

    private static function file_url(int $versionid, \stored_file $file): string {
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'local_worksheetlibrary',
            self::FILEAREA,
            $versionid,
            $file->get_filepath(),
            $file->get_filename(),
            false
        )->out(false);
    }
}
