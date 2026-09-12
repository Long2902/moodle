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

    private static function require_native_draft(int $versionid): object {
        global $DB;

        $version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
        if ($version->state !== 'draft') {
            throw new \moodle_exception('Only draft Native versions accept images');
        }
        $item = $DB->get_record('wslib_item', ['id' => $version->itemid], '*', MUST_EXIST);
        if ($item->kind !== 'native') {
            throw new \moodle_exception('Worksheet is not Native');
        }
        return $version;
    }

    private static function validate_upload(array $upload): array {
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

        return [
            'tmpname' => $tmpname,
            'mime' => $mime,
            'extension' => self::MIME_EXTENSIONS[$mime],
            'original' => clean_filename((string)($upload['name'] ?? 'image')),
        ];
    }

    private static function assert_asset_key(string $assetkey): void {
        if (!preg_match('/\Aa_[a-f0-9]{32}\z/', $assetkey)) {
            throw new \moodle_exception('Invalid Native asset key');
        }
    }

    private static function find_asset_file(int $versionid, string $assetkey): ?\stored_file {
        self::assert_asset_key($assetkey);
        $context = \context_system::instance();
        foreach (get_file_storage()->get_area_files(
            $context->id,
            'local_worksheetlibrary',
            self::FILEAREA,
            $versionid,
            'id ASC',
            false
        ) as $file) {
            if (!$file->is_directory() && pathinfo($file->get_filename(), PATHINFO_FILENAME) === $assetkey) {
                return $file;
            }
        }
        return null;
    }

    private static function base_record(int $versionid, int $userid, string $filename): array {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        return [
            'contextid' => \context_system::instance()->id,
            'component' => 'local_worksheetlibrary',
            'filearea' => self::FILEAREA,
            'itemid' => $versionid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
            'author' => fullname($user),
            'license' => 'allrightsreserved',
        ];
    }

    public static function store_upload(int $versionid, array $upload, int $userid): array {
        self::require_native_draft($versionid);
        $validated = self::validate_upload($upload);

        $assetkey = 'a_' . bin2hex(random_bytes(16));
        $filename = $assetkey . '.' . $validated['extension'];
        $file = get_file_storage()->create_file_from_pathname(
            self::base_record($versionid, $userid, $filename),
            $validated['tmpname']
        );

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($validated['original'], PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($versionid, $file),
        ];
    }

    public static function replace_upload(int $versionid, string $assetkey, array $upload, int $userid): array {
        self::require_native_draft($versionid);
        self::assert_asset_key($assetkey);
        $validated = self::validate_upload($upload);

        $existing = self::find_asset_file($versionid, $assetkey);
        if (!$existing) {
            throw new \moodle_exception('Native image asset not found');
        }

        $fs = get_file_storage();
        $temporaryname = '__replace_' . bin2hex(random_bytes(8)) . '.' . $validated['extension'];
        $temporary = $fs->create_file_from_pathname(
            self::base_record($versionid, $userid, $temporaryname),
            $validated['tmpname']
        );

        try {
            // Preserve the existing stored-file identity/logical assetKey while replacing bytes.
            $existing->replace_file_with($temporary);
            $targetname = $assetkey . '.' . $validated['extension'];
            if ($existing->get_filename() !== $targetname) {
                $existing->rename('/', $targetname);
            }
        } finally {
            $temporary->delete();
        }

        $current = self::find_asset_file($versionid, $assetkey);
        if (!$current) {
            throw new \moodle_exception('Native image replacement failed');
        }

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($validated['original'], PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($versionid, $current),
        ];
    }

    public static function asset_url(int $versionid, string $assetkey): string {
        $file = self::find_asset_file($versionid, $assetkey);
        if (!$file) {
            throw new \moodle_exception('Native image asset not found');
        }
        return self::file_url($versionid, $file);
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
