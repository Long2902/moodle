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

    private static function find_asset_file(\context_module $context, int $attemptid, string $assetkey): ?\stored_file {
        self::assert_asset_key($assetkey);
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
            if (pathinfo($file->get_filename(), PATHINFO_FILENAME) === $assetkey) {
                return $file;
            }
        }
        return null;
    }

    private static function base_record(\context_module $context, int $attemptid, int $userid, string $filename): array {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        return [
            'contextid' => $context->id,
            'component' => 'mod_worksheetgrader',
            'filearea' => self::FILEAREA,
            'itemid' => $attemptid,
            'filepath' => '/native/',
            'filename' => $filename,
            'userid' => $userid,
            'author' => fullname($user),
            'license' => 'allrightsreserved',
        ];
    }

    public static function store_upload(\context_module $context, int $attemptid, array $upload, int $userid): array {
        $validated = self::validate_upload($upload);
        $assetkey = 'a_' . bin2hex(random_bytes(16));
        $filename = $assetkey . '.' . $validated['extension'];
        $file = get_file_storage()->create_file_from_pathname(
            self::base_record($context, $attemptid, $userid, $filename),
            $validated['tmpname']
        );

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($validated['original'], PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($context, $attemptid, $file),
        ];
    }

    public static function replace_upload(
        \context_module $context,
        int $attemptid,
        string $assetkey,
        array $upload,
        int $userid
    ): array {
        self::assert_asset_key($assetkey);
        $validated = self::validate_upload($upload);

        $existing = self::find_asset_file($context, $attemptid, $assetkey);
        if (!$existing) {
            throw new \moodle_exception('Native attempt image asset not found');
        }

        $fs = get_file_storage();
        $temporaryname = '__replace_' . bin2hex(random_bytes(8)) . '.' . $validated['extension'];
        $temporary = $fs->create_file_from_pathname(
            self::base_record($context, $attemptid, $userid, $temporaryname),
            $validated['tmpname']
        );

        try {
            $existing->replace_file_with($temporary);
            $targetname = $assetkey . '.' . $validated['extension'];
            if ($existing->get_filename() !== $targetname) {
                $existing->rename('/native/', $targetname);
            }
        } finally {
            $temporary->delete();
        }

        $current = self::find_asset_file($context, $attemptid, $assetkey);
        if (!$current) {
            throw new \moodle_exception('Native attempt image replacement failed');
        }

        return [
            'assetKey' => $assetkey,
            'alt' => pathinfo($validated['original'], PATHINFO_FILENAME) ?: 'Ảnh',
            'url' => self::file_url($context, $attemptid, $current),
        ];
    }

    public static function asset_url(\context_module $context, int $attemptid, string $assetkey): string {
        $file = self::find_asset_file($context, $attemptid, $assetkey);
        if (!$file) {
            throw new \moodle_exception('Native attempt image asset not found');
        }
        return self::file_url($context, $attemptid, $file);
    }

    public static function clone_library_assets(\context_module $context, int $attemptid, int $libraryversionid): void {
        if ($libraryversionid <= 0) {
            return;
        }
        $fs = get_file_storage();
        $sourcecontext = \context_system::instance();
        foreach ($fs->get_area_files(
            $sourcecontext->id,
            'local_worksheetlibrary',
            'nativeasset',
            $libraryversionid,
            'id ASC',
            false
        ) as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $record = [
                'contextid' => $context->id,
                'component' => 'mod_worksheetgrader',
                'filearea' => self::FILEAREA,
                'itemid' => $attemptid,
                'filepath' => '/native/',
                'filename' => $file->get_filename(),
            ];
            if (!$fs->file_exists(
                $context->id,
                'mod_worksheetgrader',
                self::FILEAREA,
                $attemptid,
                '/native/',
                $file->get_filename()
            )) {
                $fs->create_file_from_storedfile($record, $file);
            }
        }
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
