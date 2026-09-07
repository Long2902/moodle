<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_worksheetgrader\engine;

defined('MOODLE_INTERNAL') || die();

class client {
    private string $baseurl;
    private string $secret;
    private int $timeout;

    public function __construct(?string $baseurl = null, ?string $secret = null, ?int $timeout = null) {
        $config = get_config('mod_worksheetgrader');
        $this->baseurl = rtrim($baseurl ?? ($config->engineurl ?? ''), '/');
        $this->secret = $secret ?? ($config->enginesecret ?? '');
        $this->timeout = $timeout ?? (int)($config->enginetimeout ?? 120);
    }

    public function configured(): bool {
        return $this->baseurl !== '' && $this->secret !== '';
    }

    public function convert(string $localpath, string $filename, string $mode = 'auto'): array {
        if (!$this->configured()) {
            throw new \moodle_exception('enginenotconfigured', 'mod_worksheetgrader');
        }
        if (!is_readable($localpath)) {
            throw new \moodle_exception('filenotreadable', 'error');
        }
        $filehash = hash_file('sha256', $localpath);
        $timestamp = (string)time();
        $canonical = $timestamp . "\n" . $filehash . "\n" . $mode;
        $signature = hash_hmac('sha256', $canonical, $this->secret);
        $curl = new \curl();
        $curl->setHeader([
            'X-Worksheet-Timestamp: ' . $timestamp,
            'X-Worksheet-Signature: ' . $signature,
        ]);
        $options = [
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(20, $this->timeout),
        ];
        $post = [
            'file' => new \CURLFile($localpath, mime_content_type($localpath) ?: 'application/octet-stream', $filename),
            'mode' => $mode,
            'filehash' => $filehash,
        ];
        $response = $curl->post($this->baseurl . '/api/v1/convert', $post, $options);
        $info = $curl->get_info();
        if (($info['http_code'] ?? 0) >= 400) {
            throw new \moodle_exception('enginehttp', 'mod_worksheetgrader', '', $info['http_code'] ?? 0, $response);
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['ok'])) {
            throw new \moodle_exception('engineinvalidresponse', 'mod_worksheetgrader', '', null, $response);
        }
        return $data;
    }
}
