<?php
namespace local_digieraoffice;
final class jwt {
    private static function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
    private static function b64urldecode(string $raw): string|false {
        $raw = strtr($raw, '-_', '+/');
        $pad = strlen($raw) % 4;
        if ($pad) { $raw .= str_repeat('=', 4 - $pad); }
        return base64_decode($raw, true);
    }
    public static function encode(array $payload, string $secret): string {
        if ($secret === '') { throw new \invalid_argument_exception('JWT secret is empty'); }
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES)), self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES))];
        $segments[] = self::b64url(hash_hmac('sha256', implode('.', $segments), $secret, true));
        return implode('.', $segments);
    }
    public static function decode(string $token, string $secret): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $secret === '') { throw new \invalid_argument_exception('Invalid JWT'); }
        $sig = self::b64urldecode($parts[2]);
        $expected = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
        if ($sig === false || !hash_equals($expected, $sig)) { throw new \invalid_argument_exception('Invalid JWT signature'); }
        $payload = self::b64urldecode($parts[1]);
        $decoded = json_decode((string)$payload, true);
        if (!is_array($decoded)) { throw new \invalid_argument_exception('Invalid JWT payload'); }
        return $decoded;
    }
}
