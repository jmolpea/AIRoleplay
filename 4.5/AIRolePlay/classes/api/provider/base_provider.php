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
 * Shared HTTP/infrastructure base for all AI providers in mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Base class implementing everything that is identical across providers:
 * license enforcement, encrypted key handling, per-user and site-wide rate
 * limiting, and the retrying HTTP layer. Concrete providers only translate
 * message formats and endpoints.
 */
abstract class base_provider {
    /** @var int Maximum retry attempts. */
    protected const MAX_RETRIES = 3;

    /** @var int Connection-establishment timeout (seconds). */
    protected const CONNECT_TIMEOUT = 10;

    /**
     * Time budget (seconds) for one live roleplay chat call, retries included.
     * The student is waiting in silence: failing after this long and letting
     * them retry beats a two-minute hang. The site timeout still applies when
     * it is lower.
     */
    public const REALTIME_TIMEOUT = 45;

    /** @var int Time budget (seconds) for one text-to-speech call, retries included. */
    public const TTS_TIMEOUT = 25;

    /** @var int Time budget (seconds) for the pre-turn moderation check (fails open). */
    public const MODERATION_TIMEOUT = 10;

    /**
     * A streamed body (TTS audio) slower than this many bytes per second for
     * STALL_SECONDS is treated as stalled and retried. OpenAI's speech
     * endpoint occasionally stops mid-stream and keeps the socket open.
     */
    protected const STALL_BYTES_PER_SECOND = 512;

    /** @var int Seconds below STALL_BYTES_PER_SECOND before a transfer is aborted. */
    protected const STALL_SECONDS = 6;

    /** @var \stdClass Plugin configuration snapshot. */
    protected \stdClass $config;

    /** @var string Primary API key for this provider (decrypted). */
    protected string $apikey;

    /** @var int Request timeout in seconds. */
    protected int $timeout;

    /** @var int Max tokens per call (global safety cap). */
    protected int $maxtokens;

    /** @var int Max calls per minute per user. */
    protected int $ratelimit;

    /** @var int Max calls per minute across the whole installation (global backstop). */
    protected int $globalratelimit;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;
        // The \curl class is not autoloaded; CLI and cron entry points may not
        // have loaded it yet.
        require_once($CFG->libdir . '/filelib.php');

        // License backstop: no AI call may proceed without a valid key bound to
        // this site, guaranteeing the block holds on every call path (including
        // background tasks) even if an entry-point check is ever bypassed.
        if (!\mod_airoleplay\license\validator::is_valid()) {
            throw new \moodle_exception('error_nolicense', 'mod_airoleplay');
        }

        $this->config = get_config('mod_airoleplay');

        $keyname      = $this->apikey_config_name();
        $this->apikey = $this->decrypt_key((string)($this->config->$keyname ?? ''));

        $this->timeout         = max(30, (int)($this->config->api_timeout ?? 120));
        $this->maxtokens       = max(256, (int)($this->config->safety_max_tokens ?? 4096));
        $this->ratelimit       = max(1, (int)($this->config->api_rate_limit ?? 10));
        $this->globalratelimit = max(1, (int)($this->config->api_rate_limit_global ?? 300));
    }

    /**
     * Human-readable provider name for error messages (e.g. 'OpenAI').
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Name of the plugin config setting holding this provider's encrypted API key.
     *
     * @return string
     */
    abstract protected function apikey_config_name(): string;

    // Shared infrastructure.

    /**
     * Makes a JSON API request with retry / back-off.
     *
     * When the API rejects the request with a 400 and $simplify is given, the
     * request is retried once with the body $simplify returns. Providers use
     * this to drop optional tuning parameters (reasoning effort, thinking
     * level, strict schemas) that a model may not accept, so a vendor-side
     * API change degrades gracefully instead of breaking every roleplay turn.
     *
     * @param string        $url      Full request URL.
     * @param array         $body     Request body (JSON-encoded before sending).
     * @param array         $headers  HTTP headers (including auth).
     * @param callable|null $simplify fn(array $body): ?array returning a fallback body, or null.
     * @param int           $budget   Time budget in seconds, retries included (0 = site timeout).
     * @param bool          $stream   Abort and retry transfers that stall mid-body (streamed audio).
     * @return array Decoded response.
     * @throws api_exception on failure after all retries.
     */
    protected function request_json(
        string $url,
        array $body,
        array $headers,
        ?callable $simplify = null,
        int $budget = 0,
        bool $stream = false
    ): array {
        $deadline = $this->deadline($budget);
        try {
            return $this->send_json($url, $body, $headers, $deadline, $stream);
        } catch (api_exception $e) {
            $fallback = ($e->httpstatus === 400 && $simplify !== null) ? $simplify($body) : null;
            if ($fallback === null || $fallback === $body) {
                throw $e;
            }
            \airoleplay_log_internal_error('provider_param_fallback', $e);
            return $this->send_json($url, $fallback, $headers, $deadline, $stream);
        }
    }

    /**
     * Sends one JSON request and decodes the JSON response.
     *
     * @param string $url      Full request URL.
     * @param array  $body     Request body.
     * @param array  $headers  HTTP headers.
     * @param float  $deadline Absolute deadline (microtime).
     * @param bool   $stream   Abort stalled transfers.
     * @return array Decoded response.
     * @throws api_exception on failure after all retries.
     */
    private function send_json(string $url, array $body, array $headers, float $deadline, bool $stream): array {
        $raw     = $this->send($url, $body, $headers, $deadline, $stream);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new api_exception(200, 'Response body is not valid JSON');
        }
        return $decoded;
    }

    /**
     * Makes a request and returns the raw binary response (used for TTS).
     *
     * Audio is streamed by the vendors, so stalled transfers are aborted and
     * retried instead of holding the student for the whole site timeout.
     *
     * @param string $url     Full request URL.
     * @param array  $body    Request body.
     * @param array  $headers HTTP headers (including auth).
     * @param int    $budget  Time budget in seconds, retries included (0 = site timeout).
     * @return string Raw binary response.
     * @throws api_exception on failure.
     */
    protected function request_raw(string $url, array $body, array $headers, int $budget = 0): string {
        return $this->send($url, $body, $headers, $this->deadline($budget), true);
    }

    /**
     * Absolute deadline for a call: the requested budget, capped by the site timeout.
     *
     * @param int $budget Seconds (0 = site timeout).
     * @return float Deadline as a microtime timestamp.
     */
    private function deadline(int $budget): float {
        $seconds = $budget > 0 ? min($budget, $this->timeout) : $this->timeout;
        return microtime(true) + $seconds;
    }

    /**
     * Sends one POST, retrying transient failures (429, 5xx, network, stalls)
     * while the deadline allows.
     *
     * @param string $url      Full request URL.
     * @param array  $body     Request body (JSON-encoded before sending).
     * @param array  $headers  HTTP headers.
     * @param float  $deadline Absolute deadline (microtime).
     * @param bool   $stream   Abort transfers slower than STALL_BYTES_PER_SECOND.
     * @return string Raw response body of a 2xx response.
     * @throws api_exception on failure after all retries.
     */
    private function send(string $url, array $body, array $headers, float $deadline, bool $stream): string {
        $attempt = 0;
        $lasterr = '';
        $status  = 0;

        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            throw new api_exception(0, 'Failed to encode request as JSON: ' . json_last_error_msg());
        }

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            $started   = microtime(true);
            $remaining = (int)max(1, ceil($deadline - $started));

            $curl = new \curl();
            $curl->setHeader($headers);
            $options = [
                'CURLOPT_TIMEOUT'        => $remaining,
                'CURLOPT_CONNECTTIMEOUT' => min(self::CONNECT_TIMEOUT, $remaining),
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_SSL_VERIFYHOST' => 2,
            ];
            if ($stream) {
                $options['CURLOPT_LOW_SPEED_LIMIT'] = self::STALL_BYTES_PER_SECOND;
                $options['CURLOPT_LOW_SPEED_TIME']  = self::STALL_SECONDS;
            }

            $raw = $curl->post($url, $encoded, $options);

            if ($curl->get_errno()) {
                $status  = 0;
                $lasterr = $curl->error;
                $this->log_request($url, 0, $started, $attempt);
                if (!$this->sleep_within_deadline($attempt, $deadline)) {
                    break;
                }
                continue;
            }

            $info   = $curl->get_info();
            $status = (int)($info['http_code'] ?? 0);
            $this->log_request($url, $status, $started, $attempt);

            if ($status >= 200 && $status < 300) {
                return (string)$raw;
            }

            // OpenAI, DeepSeek, Anthropic and Gemini all report errors under
            // error.message (with differing sibling fields).
            $decoded = json_decode((string)$raw, true);
            $lasterr = (string)($decoded['error']['message'] ?? "HTTP {$status}");

            if ($status === 429 || $status >= 500) {
                if (!$this->sleep_within_deadline($attempt, $deadline)) {
                    break;
                }
                continue;
            }

            break;
        }

        throw new api_exception($status, $this->get_name() . ': ' . $lasterr);
    }

    /**
     * Enforces both a global (per-installation) and a per-user rate limit.
     *
     * The counters are shared across all providers on purpose: the global
     * limit is a cost backstop for the site as a whole, not per vendor.
     *
     * @param int $userid Moodle user id (0 skips the per-user check only).
     * @throws \moodle_exception if either rate limit is exceeded.
     */
    protected function check_rate_limit(int $userid): void {
        $cache  = \cache::make('mod_airoleplay', 'ratelimit');
        $window = floor(time() / 60);

        // Global per-installation backstop, always enforced.
        $globalkey   = 'global_' . $window;
        $globalcount = (int)($cache->get($globalkey) ?? 0);
        if ($globalcount >= $this->globalratelimit) {
            throw new \moodle_exception('rate_limit_exceeded_global', 'mod_airoleplay');
        }
        $cache->set($globalkey, $globalcount + 1);

        if (!$userid) {
            return;
        }

        $key   = 'user_' . $userid . '_' . $window;
        $count = (int)($cache->get($key) ?? 0);
        if ($count >= $this->ratelimit) {
            throw new \moodle_exception('rate_limit_exceeded', 'mod_airoleplay');
        }
        $cache->set($key, $count + 1);
    }

    /**
     * Sleeps an exponential back-off, but never past the request deadline.
     *
     * Returns false when there is no time left to retry, so callers can stop
     * looping instead of blocking the PHP worker indefinitely.
     *
     * @param int   $attempt  Current attempt number (1-based).
     * @param float $deadline Absolute unix timestamp (microtime).
     * @return bool True if there is time left to retry; false otherwise.
     */
    protected function sleep_within_deadline(int $attempt, float $deadline): bool {
        $backoff   = min(30, 2 ** max(0, $attempt - 1));
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            return false;
        }
        // Never burn more than half of the remaining budget on a sleep.
        $sleep = (int)max(0, min($backoff, floor($remaining / 2)));
        if ($sleep > 0) {
            sleep($sleep);
        }
        return ($deadline - microtime(true)) > 0;
    }

    /**
     * Logs an API request in developer debug mode (no content).
     *
     * @param string $url     Request URL.
     * @param int    $status  HTTP status code (0 = network error or timeout).
     * @param float  $started Request start (microtime).
     * @param int    $attempt Attempt number (1-based).
     */
    protected function log_request(string $url, int $status, float $started, int $attempt): void {
        global $CFG;
        // Server log only, and only in developer mode: printing through
        // debugging() would corrupt the JSON responses of the roleplay page.
        if (!empty($CFG->debugdeveloper) && !PHPUNIT_TEST) {
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log(sprintf(
                'mod_airoleplay %s: POST %s -> %d in %d ms (attempt %d)',
                $this->get_name(),
                $url,
                $status,
                (int)round((microtime(true) - $started) * 1000),
                $attempt
            ));
        }
    }

    /**
     * Returns the inference profile requested by the caller.
     *
     * @param array $options Chat options.
     * @return string chat_provider::PROFILE_REALTIME or chat_provider::PROFILE_EVALUATION.
     */
    protected function profile(array $options): string {
        return (($options['profile'] ?? '') === chat_provider::PROFILE_EVALUATION)
            ? chat_provider::PROFILE_EVALUATION
            : chat_provider::PROFILE_REALTIME;
    }

    /**
     * Time budget for a chat call of the given profile.
     *
     * @param string $profile Inference profile.
     * @return int Seconds (0 = the site timeout, used for evaluations).
     */
    protected function chat_budget(string $profile): int {
        return $profile === chat_provider::PROFILE_REALTIME ? self::REALTIME_TIMEOUT : 0;
    }

    /**
     * Builds the error raised when a model answers 200 but produces no text.
     *
     * Thinking models that exhaust their token budget while reasoning, and
     * safety refusals, both look like this. Failing loudly beats handing the
     * student an empty avatar line.
     *
     * @param string $model  Model id.
     * @param string $reason Vendor finish/stop reason.
     * @return api_exception
     */
    protected function empty_response(string $model, string $reason): api_exception {
        return new api_exception(200, "{$this->get_name()} returned no text (reason: {$reason}, model: {$model}).");
    }

    /**
     * Throws a clear error when this provider has no API key configured (or it
     * could not be decrypted) so callers do not get an opaque 401 upstream.
     *
     * @throws \moodle_exception
     */
    protected function require_api_key(): void {
        if ($this->apikey === '') {
            throw new \moodle_exception('apikey_missing', 'mod_airoleplay', '', $this->get_name());
        }
    }

    /**
     * Decrypts a stored API key. Fails closed — never falls back to plaintext.
     *
     * The plugin stores API keys via admin_setting_encryptedpassword, which
     * always encrypts on save. If decryption fails, we refuse to use the value:
     * leaking a plaintext key from a misconfigured config column would be far
     * worse than the AI calls failing loudly.
     *
     * @param string $value Encrypted value from config.
     * @return string Plaintext API key, or empty string if unavailable.
     */
    protected function decrypt_key(string $value): string {
        if (empty($value)) {
            return '';
        }
        try {
            $decrypted = \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log('mod_airoleplay: API key decryption failed; refusing to use the raw value');
            return '';
        }
        if ($decrypted === false || $decrypted === null || $decrypted === '') {
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log('mod_airoleplay: API key could not be decrypted; refusing to use the raw value');
            return '';
        }
        return $decrypted;
    }

    /**
     * Normalises an internal messages array for providers with strict
     * turn-taking rules (Anthropic, Gemini): extracts system messages, merges
     * consecutive same-role messages and guarantees the list starts with a
     * user turn.
     *
     * @param array $messages Internal-format messages.
     * @return array ['system' => string, 'turns' => array of ['role' => user|assistant, 'content' => string]]
     */
    protected function normalise_turns(array $messages): array {
        $system = [];
        $turns  = [];

        foreach ($messages as $message) {
            $role    = $message['role'] ?? 'user';
            $content = trim((string)($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'system') {
                $system[] = $content;
                continue;
            }
            $role = ($role === 'assistant') ? 'assistant' : 'user';
            if (!empty($turns) && $turns[count($turns) - 1]['role'] === $role) {
                // Merge consecutive same-role turns (e.g. two avatar messages
                // in a row in multi-avatar scenarios).
                $turns[count($turns) - 1]['content'] .= "\n\n" . $content;
            } else {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }

        // Both Anthropic and Gemini expect the conversation to open with a
        // user turn; the roleplay opens with an avatar (assistant) statement.
        if (!empty($turns) && $turns[0]['role'] === 'assistant') {
            array_unshift($turns, ['role' => 'user', 'content' => '(The roleplay session begins.)']);
        }
        if (empty($turns)) {
            $turns[] = ['role' => 'user', 'content' => '(The roleplay session begins.)'];
        }

        return ['system' => implode("\n\n", $system), 'turns' => $turns];
    }
}
