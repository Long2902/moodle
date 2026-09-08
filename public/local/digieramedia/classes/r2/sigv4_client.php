<?php

namespace local_digieramedia\r2;

/**
 * Narrow AWS Signature V4 client for Cloudflare R2.
 *
 * RC1 single-PUT uses no third-party runtime dependency. The signer is limited
 * to the operations exercised here; multipart methods deliberately fail closed
 * until the multipart batch lands.
 */
final class sigv4_client implements client_interface {
    private config $config;
    private ?int $fixedtime;

    public function __construct(?config $config = null, ?int $fixedtime = null) {
        $this->config = $config ?? config::load();
        $this->config->require_credentials();
        $this->fixedtime = $fixedtime;
    }

    public function presign_put(string $bucket, string $key, string $contenttype, int $ttl): string {
        $this->assert_server_target($bucket, $key);
        $ttl = min(3600, max(1, $ttl));
        $time = $this->now();
        $amzdate = gmdate('Ymd\THis\Z', $time);
        $datestamp = gmdate('Ymd', $time);
        $scope = $datestamp . '/' . $this->config->region() . '/s3/aws4_request';
        $host = $this->endpoint_host();
        $uri = $this->canonical_uri($bucket, $key);
        $signedheaders = 'content-type;host';

        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->config->access_key_id() . '/' . $scope,
            'X-Amz-Date' => $amzdate,
            'X-Amz-Expires' => (string)$ttl,
            'X-Amz-SignedHeaders' => $signedheaders,
        ];
        $canonicalquery = $this->canonical_query($query);
        $canonicalheaders = 'content-type:' . $this->normalise_header_value($contenttype) . "\n"
            . 'host:' . $host . "\n";
        $canonicalrequest = "PUT\n{$uri}\n{$canonicalquery}\n{$canonicalheaders}\n{$signedheaders}\nUNSIGNED-PAYLOAD";
        $stringtosign = "AWS4-HMAC-SHA256\n{$amzdate}\n{$scope}\n" . hash('sha256', $canonicalrequest);
        $signature = hash_hmac('sha256', $stringtosign, $this->signing_key($datestamp));

        return $this->config->endpoint() . $uri . '?' . $canonicalquery . '&X-Amz-Signature=' . $signature;
    }

    public function head_object(string $bucket, string $key): array {
        $this->assert_server_target($bucket, $key);
        $time = $this->now();
        $amzdate = gmdate('Ymd\THis\Z', $time);
        $datestamp = gmdate('Ymd', $time);
        $payloadhash = hash('sha256', '');
        $host = $this->endpoint_host();
        $uri = $this->canonical_uri($bucket, $key);
        $signedheaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalheaders = 'host:' . $host . "\n"
            . 'x-amz-content-sha256:' . $payloadhash . "\n"
            . 'x-amz-date:' . $amzdate . "\n";
        $canonicalrequest = "HEAD\n{$uri}\n\n{$canonicalheaders}\n{$signedheaders}\n{$payloadhash}";
        $scope = $datestamp . '/' . $this->config->region() . '/s3/aws4_request';
        $stringtosign = "AWS4-HMAC-SHA256\n{$amzdate}\n{$scope}\n" . hash('sha256', $canonicalrequest);
        $signature = hash_hmac('sha256', $stringtosign, $this->signing_key($datestamp));
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->config->access_key_id() . '/' . $scope
            . ', SignedHeaders=' . $signedheaders . ', Signature=' . $signature;

        if (!function_exists('curl_init')) {
            throw new \runtime_exception('R2 transport unavailable: PHP cURL extension is missing.');
        }

        $headers = [];
        $ch = curl_init($this->config->endpoint() . $uri);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Host: ' . $host,
                'x-amz-content-sha256: ' . $payloadhash,
                'x-amz-date: ' . $amzdate,
                'Authorization: ' . $authorization,
            ],
            CURLOPT_HEADERFUNCTION => static function($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);
        $result = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $status < 200 || $status >= 300) {
            $suffix = $error !== '' ? ' transport=' . clean_param($error, PARAM_TEXT) : '';
            throw new \runtime_exception('R2 HEAD verification failed with HTTP ' . $status . $suffix);
        }

        return [
            'contentlength' => isset($headers['content-length']) ? (int)$headers['content-length'] : -1,
            'contenttype' => (string)($headers['content-type'] ?? ''),
            'etag' => trim((string)($headers['etag'] ?? ''), "\"' "),
            'status' => $status,
        ];
    }

    public function create_multipart_upload(string $bucket, string $key, string $contenttype): string {
        throw new \coding_exception('DIGIERA R2 multipart upload is not enabled in the single-PUT RC batch.');
    }

    public function presign_upload_part(string $bucket, string $key, string $uploadid, int $partnumber, int $ttl): string {
        throw new \coding_exception('DIGIERA R2 multipart upload is not enabled in the single-PUT RC batch.');
    }

    public function complete_multipart_upload(string $bucket, string $key, string $uploadid, array $parts): array {
        throw new \coding_exception('DIGIERA R2 multipart upload is not enabled in the single-PUT RC batch.');
    }

    public function abort_multipart_upload(string $bucket, string $key, string $uploadid): void {
        throw new \coding_exception('DIGIERA R2 multipart upload is not enabled in the single-PUT RC batch.');
    }

    public function delete_object(string $bucket, string $key): void {
        throw new \coding_exception('DIGIERA R2 delete is outside the single-PUT RC batch.');
    }

    public function copy_object(string $sourcebucket, string $sourcekey, string $targetbucket, string $targetkey): array {
        throw new \coding_exception('DIGIERA R2 copy is outside the single-PUT RC batch.');
    }

    private function assert_server_target(string $bucket, string $key): void {
        if (!hash_equals($this->config->bucket(), $bucket)) {
            throw new \invalid_parameter_exception('R2 bucket is not the configured DIGIERA bucket.');
        }
        if ($key === '' || str_contains($key, '..') || str_starts_with($key, '/')) {
            throw new \invalid_parameter_exception('Invalid DIGIERA R2 object key.');
        }
    }

    private function endpoint_host(): string {
        $host = parse_url($this->config->endpoint(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new \coding_exception('Invalid DIGIERA R2 endpoint.');
        }
        $port = parse_url($this->config->endpoint(), PHP_URL_PORT);
        return $port ? $host . ':' . $port : $host;
    }

    private function canonical_uri(string $bucket, string $key): string {
        $segments = array_merge([$bucket], explode('/', $key));
        return '/' . implode('/', array_map(static fn(string $segment): string => rawurlencode($segment), $segments));
    }

    private function canonical_query(array $query): string {
        ksort($query, SORT_STRING);
        $pairs = [];
        foreach ($query as $name => $value) {
            $pairs[] = rawurlencode((string)$name) . '=' . rawurlencode((string)$value);
        }
        return implode('&', $pairs);
    }

    private function signing_key(string $datestamp): string {
        $kdate = hash_hmac('sha256', $datestamp, 'AWS4' . $this->config->secret_access_key(), true);
        $kregion = hash_hmac('sha256', $this->config->region(), $kdate, true);
        $kservice = hash_hmac('sha256', 's3', $kregion, true);
        return hash_hmac('sha256', 'aws4_request', $kservice, true);
    }

    private function normalise_header_value(string $value): string {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }

    private function now(): int {
        return $this->fixedtime ?? time();
    }
}
