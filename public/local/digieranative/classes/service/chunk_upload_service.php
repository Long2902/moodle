<?php
namespace local_digieranative\service;

defined('MOODLE_INTERNAL') || die();

final class chunk_upload_service {
    public const FILEAREA = 'nativechunk';
    public const CHUNK_BYTES = 65536;
    public const MAX_BYTES = 5242880;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private static function upload_filepath(string $scope, string $uploadid): string {
        $scopehash = hash('sha256', $scope . '|' . $uploadid);
        return '/' . substr($scopehash, 0, 40) . '/';
    }

    private static function validate_request(
        string $scope,
        string $uploadid,
        int $chunkindex,
        int $chunktotal,
        int $filesize,
        string $filename,
        string $declaredmime,
        string $body
    ): void {
        if ($scope === '') {
            throw new \moodle_exception('Chunk upload scope is required');
        }
        if (!preg_match('/\A[a-f0-9]{32}\z/', $uploadid)) {
            throw new \moodle_exception('Invalid chunk upload id');
        }
        if ($filesize <= 0 || $filesize > self::MAX_BYTES) {
            throw new \moodle_exception('Image must be between 1 byte and 5 MiB');
        }
        $expectedtotal = (int)ceil($filesize / self::CHUNK_BYTES);
        if ($chunktotal <= 0 || $chunktotal !== $expectedtotal) {
            throw new \moodle_exception('Invalid chunk total');
        }
        if ($chunkindex < 0 || $chunkindex >= $chunktotal) {
            throw new \moodle_exception('Invalid chunk index');
        }
        $expectedbytes = min(self::CHUNK_BYTES, $filesize - ($chunkindex * self::CHUNK_BYTES));
        if ($expectedbytes <= 0 || strlen($body) !== $expectedbytes) {
            throw new \moodle_exception('Invalid chunk size');
        }
        if (clean_filename($filename) === '') {
            throw new \moodle_exception('Invalid image filename');
        }
        if ($declaredmime !== '' && !isset(self::MIME_EXTENSIONS[strtolower($declaredmime)])) {
            throw new \moodle_exception('Unsupported declared image type');
        }
    }

    private static function staged_chunks(int $userid, string $filepath): array {
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_digieranative',
            self::FILEAREA,
            $userid,
            'filename ASC',
            false
        );
        $chunks = [];
        foreach ($files as $file) {
            if ($file->is_directory() || $file->get_filepath() !== $filepath) {
                continue;
            }
            if (!preg_match('/\Achunk-(\d{6})\.part\z/', $file->get_filename(), $matches)) {
                continue;
            }
            $chunks[(int)$matches[1]] = $file;
        }
        ksort($chunks, SORT_NUMERIC);
        return $chunks;
    }

    private static function cleanup(int $userid, string $filepath): void {
        $context = \context_system::instance();
        foreach (get_file_storage()->get_area_files(
            $context->id,
            'local_digieranative',
            self::FILEAREA,
            $userid,
            'id ASC',
            true
        ) as $file) {
            if ($file->get_filepath() === $filepath) {
                $file->delete();
            }
        }
    }

    public static function accept_chunk(
        string $scope,
        string $uploadid,
        int $chunkindex,
        int $chunktotal,
        int $filesize,
        string $filename,
        string $declaredmime,
        string $body,
        int $userid
    ): array {
        self::validate_request(
            $scope,
            $uploadid,
            $chunkindex,
            $chunktotal,
            $filesize,
            $filename,
            $declaredmime,
            $body
        );

        $context = \context_system::instance();
        $filepath = self::upload_filepath($scope, $uploadid);
        $chunkname = sprintf('chunk-%06d.part', $chunkindex);
        $fs = get_file_storage();

        $existing = $fs->get_file(
            $context->id,
            'local_digieranative',
            self::FILEAREA,
            $userid,
            $filepath,
            $chunkname
        );
        if ($existing) {
            if ($existing->get_content() !== $body) {
                self::cleanup($userid, $filepath);
                throw new \moodle_exception('Chunk retry content mismatch');
            }
        } else {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_digieranative',
                'filearea' => self::FILEAREA,
                'itemid' => $userid,
                'filepath' => $filepath,
                'filename' => $chunkname,
            ], $body);
        }

        $chunks = self::staged_chunks($userid, $filepath);
        if (count($chunks) < $chunktotal) {
            return [
                'complete' => false,
                'received' => count($chunks),
                'total' => $chunktotal,
            ];
        }

        if (count($chunks) !== $chunktotal) {
            self::cleanup($userid, $filepath);
            throw new \moodle_exception('Unexpected chunk count');
        }

        $bytes = '';
        for ($index = 0; $index < $chunktotal; $index++) {
            if (!isset($chunks[$index])) {
                self::cleanup($userid, $filepath);
                throw new \moodle_exception('Missing image chunk');
            }
            $bytes .= $chunks[$index]->get_content();
        }
        if (strlen($bytes) !== $filesize) {
            self::cleanup($userid, $filepath);
            throw new \moodle_exception('Assembled image size mismatch');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->buffer($bytes);
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            self::cleanup($userid, $filepath);
            throw new \moodle_exception('Only JPEG, PNG and WebP images are supported');
        }

        $original = clean_filename($filename);
        self::cleanup($userid, $filepath);

        return [
            'complete' => true,
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => self::MIME_EXTENSIONS[$mime],
            'original' => $original,
            'size' => $filesize,
        ];
    }
}
