<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Offline asymmetric (RSA-SHA256) license validator for mod_airoleplay.
 *
 * Key format:   {base64url(json_payload)}.{base64url(rsa_sha256_signature)}
 * Payload:      {"wwwroot": "...", "expires": "YYYY-MM-DD"?, "edition": "..."}
 *
 * The full $CFG->wwwroot is bound into the signed payload, so a key issued for
 * one site never validates on another. Only the PUBLIC key ships here; the
 * private key (license_private.key) stays at RSMAX and signs keys via
 * generate_license.php. Each plugin has its own keypair.
 *
 * Enforcement: when the license is missing, invalid, or expired the activity is
 * blocked (view.php shows a message, ajax.php returns an error, and the OpenAI
 * client refuses to run). A site administrator can always reach the plugin
 * settings to paste a key.
 *
 * @package    mod_airoleplay
 * @copyright  2025 RSMAX Consulting S.L.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\license;

/**
 * Offline RSA-SHA256 license validator for mod_airoleplay.
 */
class validator {
    /** @var string Frankenstyle component — used for get_config() and get_string(). */
    public const COMPONENT = 'mod_airoleplay';

    /**
     * RSA public key (base64 DER) used to verify license signatures.
     * Safe to ship; the matching private key never leaves RSMAX.
     */
    public const PUBLIC_KEY =
            'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvc33f9V/vbDSqxpPGrZ67NUfRD7NvkE0oDjQIlsNAHTZE2Qceg1M'
            . 'DBfhpF7gzb37rMQhdrFsoLAQQSELZMlnvmGpJBiGVa6Xj7dy3SvIiNoonkrJ7TdYzASDICwjMVxpeEspWKT3liCkMF7qiHrX'
            . '+toG5fuu/VuGSkGZYXLvWDKIuP1W5bNFtLISGuXvutKfCwc4GIBw+60OVY1jbtJ1Ik5slBctrH0gNZwbIvG2u67EFTe22r/s'
            . 'j9pj47eG5zg6Op2BvdeKgePFHymo1DN3wZT1MdpyATUVnKp+PX83L3kZ7EgLxGSO8xv22IbJj+sg/iqdIpo8U51WwZ4bttzB'
            . 'cQIDAQAB';

    /** License is present and cryptographically valid. */
    public const STATUS_VALID   = 'valid';

    /** Signature verification failed, or wwwroot does not match. */
    public const STATUS_INVALID = 'invalid';

    /** Signature valid and wwwroot matches, but the expiry date has passed. */
    public const STATUS_EXPIRED = 'expired';

    /** No license key has been entered in plugin settings. */
    public const STATUS_MISSING = 'missing';

    /**
     * Validate the currently-configured license key against this Moodle installation.
     *
     * @return \stdClass {string status; string|null expires; string|null edition}
     */
    public static function check(): \stdClass {
        global $CFG;
        static $cache = [];

        $key = trim((string) get_config(self::COMPONENT, 'license_key'));

        if ($key === '') {
            return (object) ['status' => self::STATUS_MISSING, 'expires' => null, 'edition' => null];
        }

        // The RSA verification runs on every AI call; remember the verdict for
        // this request, keyed on everything it depends on.
        $cachekey = sha1($key . '|' . $CFG->wwwroot . '|' . date('Y-m-d'));
        if (!isset($cache[$cachekey]) || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $cache[$cachekey] = self::validate_key($key);
        }
        return clone $cache[$cachekey];
    }

    /**
     * Convenience boolean: true only when the license is present and valid.
     *
     * @return bool
     */
    public static function is_valid(): bool {
        return self::check()->status === self::STATUS_VALID;
    }

    /**
     * Return a user-facing message for non-valid license states, or null when
     * the license is valid. Shown on the blocked activity page and AJAX errors.
     *
     * @return string|null
     */
    public static function get_banner(): ?string {
        global $CFG;

        $result = self::check();

        switch ($result->status) {
            case self::STATUS_MISSING:
                return get_string('license_banner_missing', self::COMPONENT);

            case self::STATUS_INVALID:
                return get_string('license_banner_invalid', self::COMPONENT, rtrim($CFG->wwwroot, '/'));

            case self::STATUS_EXPIRED:
                return get_string('license_banner_expired', self::COMPONENT, $result->expires);

            default: // STATUS_VALID.
                return null;
        }
    }

    /**
     * Return a short status string and CSS class for the settings page display.
     *
     * @return array{text: string, css: string}
     */
    public static function get_settings_status(): array {
        $result = self::check();

        switch ($result->status) {
            case self::STATUS_VALID:
                $text = $result->expires
                    ? get_string('license_status_valid', self::COMPONENT, $result->expires)
                    : get_string('license_status_valid_lifetime', self::COMPONENT);
                return ['text' => $text, 'css' => 'text-success fw-bold'];

            case self::STATUS_EXPIRED:
                return [
                    'text' => get_string('license_status_expired', self::COMPONENT, $result->expires),
                    'css'  => 'text-warning fw-bold',
                ];

            case self::STATUS_INVALID:
                return [
                    'text' => get_string('license_status_invalid', self::COMPONENT),
                    'css'  => 'text-danger fw-bold',
                ];

            default: // MISSING.
                return [
                    'text' => get_string('license_status_missing', self::COMPONENT),
                    'css'  => 'text-muted',
                ];
        }
    }

    // Internals.

    /**
     * Perform cryptographic validation of a non-empty license key string.
     *
     * @param  string $key  Trimmed license key.
     * @return \stdClass
     */
    private static function validate_key(string $key): \stdClass {
        $dotpos = strrpos($key, '.');
        if ($dotpos === false || $dotpos === 0 || $dotpos === strlen($key) - 1) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        $payloadb64 = substr($key, 0, $dotpos);
        $sigpart    = substr($key, $dotpos + 1);

        // Step 1: RSA-SHA256 signature verification with the embedded public key.
        $signature = base64_decode(strtr($sigpart, '-_', '+/'), true);
        $publickey = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(self::PUBLIC_KEY, 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        if (
            $signature === false
                || !function_exists('openssl_verify')
                || openssl_verify($payloadb64, $signature, $publickey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 2: Decode payload.
        $payloadjson = base64_decode(strtr($payloadb64, '-_', '+/'), true);
        if ($payloadjson === false) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        $payload = json_decode($payloadjson, true);
        if (!is_array($payload) || empty($payload['wwwroot'])) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 3: wwwroot binding — exact match against $CFG->wwwroot.
        global $CFG;
        $siteroot = rtrim($CFG->wwwroot, '/');
        $keyroot  = rtrim($payload['wwwroot'], '/');

        if ($siteroot !== $keyroot) {
            return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
        }

        // Step 4: Expiry check. Absent 'expires' means lifetime license.
        $expires = null;
        $edition = $payload['edition'] ?? null;

        if (!empty($payload['expires'])) {
            try {
                $expirydate = new \DateTime($payload['expires']);
                $expires     = $expirydate->format('Y-m-d');

                if (new \DateTime() > $expirydate) {
                    return (object) [
                        'status'  => self::STATUS_EXPIRED,
                        'expires' => $expires,
                        'edition' => $edition,
                    ];
                }
            } catch (\Exception $e) {
                return (object) ['status' => self::STATUS_INVALID, 'expires' => null, 'edition' => null];
            }
        }

        return (object) [
            'status'  => self::STATUS_VALID,
            'expires' => $expires,
            'edition' => $edition,
        ];
    }
}
