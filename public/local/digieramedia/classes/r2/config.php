<?php

namespace local_digieramedia\r2;

/**
 * Server-only Cloudflare R2 configuration.
 *
 * Secrets are resolved at runtime from environment variables, $CFG, or
 * /etc/digiera/r2.php. They are never returned to browser-facing code.
 */
final class config {
    private const DEFAULT_FILE = '/etc/digiera/r2.php';
    private const DEFAULT_TTL = 600;
    private const DEFAULT_SINGLE_MAX = 104857600; // 100 MiB until multipart is enabled.

    private array $values;

    private function __construct(array $values) {
        $this->values = $values;
    }

    public static function load(): self {
        global $CFG;

        $file = trim((string)(getenv('DIGIERA_R2_CONFIG_FILE') ?: ($CFG->digiera_r2_config_file ?? self::DEFAULT_FILE)));
        $fileconfig = [];
        if ($file !== '' && is_readable($file)) {
            $loaded = require($file);
            if (is_array($loaded)) {
                $fileconfig = $loaded;
            }
        }

        $accountid = self::first(
            getenv('DIGIERA_R2_ACCOUNT_ID'),
            $CFG->digiera_r2_account_id ?? null,
            $fileconfig['accountid'] ?? null
        );
        $endpoint = self::first(
            getenv('DIGIERA_R2_ENDPOINT'),
            $CFG->digiera_r2_endpoint ?? null,
            $fileconfig['endpoint'] ?? null
        );
        if ($endpoint === '' && $accountid !== '') {
            $endpoint = 'https://' . $accountid . '.r2.cloudflarestorage.com';
        }

        $ttl = (int)self::first(
            getenv('DIGIERA_R2_PRESIGN_TTL'),
            $CFG->digiera_r2_presign_ttl ?? null,
            $fileconfig['presignttl'] ?? null,
            self::DEFAULT_TTL
        );
        $ttl = min(3600, max(60, $ttl));

        $singlemax = (int)self::first(
            getenv('DIGIERA_R2_SINGLE_MAX_BYTES'),
            $CFG->digiera_r2_single_max_bytes ?? null,
            $fileconfig['singleputmaxbytes'] ?? null,
            self::DEFAULT_SINGLE_MAX
        );
        $singlemax = max(1048576, $singlemax);

        return new self([
            'accountid' => $accountid,
            'endpoint' => rtrim($endpoint, '/'),
            'bucket' => self::first(
                getenv('DIGIERA_R2_BUCKET'),
                $CFG->digiera_r2_bucket ?? null,
                $fileconfig['bucket'] ?? null
            ),
            'accesskeyid' => self::first(
                getenv('DIGIERA_R2_ACCESS_KEY_ID'),
                $CFG->digiera_r2_access_key_id ?? null,
                $fileconfig['accesskeyid'] ?? null
            ),
            'secretaccesskey' => self::first(
                getenv('DIGIERA_R2_SECRET_ACCESS_KEY'),
                $CFG->digiera_r2_secret_access_key ?? null,
                $fileconfig['secretaccesskey'] ?? null
            ),
            'region' => 'auto',
            'presignttl' => $ttl,
            'singleputmaxbytes' => $singlemax,
            'configfile' => $file,
        ]);
    }

    public static function from_array(array $values): self {
        $values += [
            'accountid' => '',
            'endpoint' => '',
            'bucket' => '',
            'accesskeyid' => '',
            'secretaccesskey' => '',
            'region' => 'auto',
            'presignttl' => self::DEFAULT_TTL,
            'singleputmaxbytes' => self::DEFAULT_SINGLE_MAX,
            'configfile' => '',
        ];
        return new self($values);
    }

    public function has_credentials(): bool {
        return $this->endpoint() !== ''
            && $this->bucket() !== ''
            && $this->access_key_id() !== ''
            && $this->secret_access_key() !== '';
    }

    public function require_credentials(): void {
        if (!$this->has_credentials()) {
            throw new \moodle_exception('r2notconfigured', 'local_digieramedia');
        }
    }

    public function endpoint(): string {
        return (string)$this->values['endpoint'];
    }

    public function bucket(): string {
        return (string)$this->values['bucket'];
    }

    public function access_key_id(): string {
        return (string)$this->values['accesskeyid'];
    }

    public function secret_access_key(): string {
        return (string)$this->values['secretaccesskey'];
    }

    public function region(): string {
        return (string)$this->values['region'];
    }

    public function presign_ttl(): int {
        return (int)$this->values['presignttl'];
    }

    public function single_put_max_bytes(): int {
        return (int)$this->values['singleputmaxbytes'];
    }

    public function public_summary(): array {
        return [
            'configured' => $this->has_credentials(),
            'endpoint' => $this->endpoint(),
            'bucket' => $this->bucket(),
            'region' => $this->region(),
            'presignttl' => $this->presign_ttl(),
            'singleputmaxbytes' => $this->single_put_max_bytes(),
        ];
    }

    private static function first(...$values): string {
        foreach ($values as $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}
