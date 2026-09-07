<?php
namespace local_digieraoffice;
final class command_client {
    public static function forcesave_payload(string $key, string $userdata, string $secret): array {
        $body = ['c' => 'forcesave', 'key' => $key, 'userdata' => $userdata];
        return ['body' => $body, 'token' => jwt::encode($body, $secret)];
    }
    public static function forcesave(string $key, string $userdata = ''): array {
        global $CFG;
        $config = get_config('local_digieraoffice');
        if (empty($config->enabled) || empty($config->documentserverurl) || empty($config->jwtsecret)) {
            throw new \moodle_exception('ONLYOFFICE gateway is not configured');
        }
        $payload = self::forcesave_payload($key, $userdata, (string)$config->jwtsecret);
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'Authorization: Bearer ' . $payload['token']]);
        $url = rtrim((string)$config->documentserverurl, '/') . '/coauthoring/CommandService.ashx';
        $raw = $curl->post($url, json_encode($payload['body']), ['CURLOPT_TIMEOUT' => max(5, (int)($config->timeout ?? 30))]);
        $response = json_decode((string)$raw, true);
        if (!is_array($response)) { throw new \moodle_exception('Invalid ONLYOFFICE Command Service response'); }
        return $response;
    }
}
