<?php

namespace local_digieramedia\service;

use local_digieramedia\r2\config;

final class file_policy {
    private const TYPES = [
        'pdf' => ['mediatype' => 'pdf', 'mime' => 'application/pdf', 'allowed' => ['application/pdf']],
        'jpg' => ['mediatype' => 'image', 'mime' => 'image/jpeg', 'allowed' => ['image/jpeg', 'image/pjpeg']],
        'jpeg' => ['mediatype' => 'image', 'mime' => 'image/jpeg', 'allowed' => ['image/jpeg', 'image/pjpeg']],
        'png' => ['mediatype' => 'image', 'mime' => 'image/png', 'allowed' => ['image/png']],
        'webp' => ['mediatype' => 'image', 'mime' => 'image/webp', 'allowed' => ['image/webp']],
        'mp4' => ['mediatype' => 'video', 'mime' => 'video/mp4', 'allowed' => ['video/mp4', 'application/mp4']],
        'webm' => ['mediatype' => 'video', 'mime' => 'video/webm', 'allowed' => ['video/webm']],
        'mp3' => ['mediatype' => 'audio', 'mime' => 'audio/mpeg', 'allowed' => ['audio/mpeg', 'audio/mp3']],
        'wav' => ['mediatype' => 'audio', 'mime' => 'audio/wav', 'allowed' => ['audio/wav', 'audio/x-wav']],
        'm4a' => ['mediatype' => 'audio', 'mime' => 'audio/mp4', 'allowed' => ['audio/mp4', 'audio/x-m4a']],
        'doc' => ['mediatype' => 'office', 'mime' => 'application/msword', 'allowed' => ['application/msword']],
        'docx' => ['mediatype' => 'office', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'allowed' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
        'ppt' => ['mediatype' => 'office', 'mime' => 'application/vnd.ms-powerpoint', 'allowed' => ['application/vnd.ms-powerpoint']],
        'pptx' => ['mediatype' => 'office', 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'allowed' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation']],
        'xls' => ['mediatype' => 'office', 'mime' => 'application/vnd.ms-excel', 'allowed' => ['application/vnd.ms-excel']],
        'xlsx' => ['mediatype' => 'office', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'allowed' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
    ];

    private config $config;

    public function __construct(?config $config = null) {
        $this->config = $config ?? config::load();
    }

    public function validate(string $filename, string $browsermimetype, int $filesize): array {
        $filename = trim(str_replace("\0", '', basename(str_replace('\\', '/', $filename))));
        if ($filename === '' || \core_text::strlen($filename) > 255) {
            throw new \invalid_parameter_exception('Tên tệp không hợp lệ.');
        }

        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$extension])) {
            throw new \invalid_parameter_exception('Định dạng tệp chưa được DIGIERA Media cho phép.');
        }
        if ($filesize <= 0) {
            throw new \invalid_parameter_exception('Tệp rỗng hoặc kích thước không hợp lệ.');
        }
        if ($filesize > $this->config->single_put_max_bytes()) {
            throw new \invalid_parameter_exception('Tệp vượt ngưỡng single-PUT hiện tại; multipart sẽ được bật ở batch kế tiếp.');
        }

        $policy = self::TYPES[$extension];
        $browsermimetype = strtolower(trim($browsermimetype));
        if ($browsermimetype !== '' && $browsermimetype !== 'application/octet-stream'
                && !in_array($browsermimetype, $policy['allowed'], true)) {
            throw new \invalid_parameter_exception('MIME type không khớp với phần mở rộng tệp.');
        }

        return [
            'filename' => $filename,
            'extension' => $extension,
            'mediatype' => $policy['mediatype'],
            'mimetype' => $policy['mime'],
            'filesize' => $filesize,
        ];
    }
}
