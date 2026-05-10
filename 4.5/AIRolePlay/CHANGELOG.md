# Changelog — mod_airoleplay

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/).

---

## [0.2.0] — 2025-05-10

### Security

- **Critical** — Implemented student PII redaction (`STUDENT-<sha256hash>`) before any payload reaches OpenAI, fulfilling the privacy promise the README always made. Last name, full name combinations, username and email are replaced with the hash; the first name is intentionally preserved so the avatar can address the human naturally.
- **Critical** — Hardened the evaluator against prompt injection: payload patterns are detected, delimiter strings are neutralised, and the final grade is recomputed server-side from the rubric components. Any integrity signal forces manual review even when the grading workflow is off.
- **Critical** — Fixed cross-attempt IDOR in `submissions.php`: queries now require an explicit `submissionid` and validate the `(airoleplay, userid, attempt)` triple. `is_enrolled()` is enforced before mutating any submission.
- **Critical** — Closed an OpenAI cost-runaway path: the evaluator now passes the real submission owner id, and `openai_client::check_rate_limit()` always enforces a site-wide cap (new `api_rate_limit_global` setting, default 60/min) before applying the per-user cap.
- **Critical** — OpenAI API keys are now stored with `admin_setting_encryptedpassword`. `decrypt_key()` fails closed instead of silently falling back to plaintext, and an upgrade step re-encrypts any pre-existing plaintext value.
- **High** — Privacy API metadata declares every persisted column the user controls, the previously hidden `airoleplay_overrides` table, and the full set of fields transferred to OpenAI.
- **High** — AI-generated and teacher-authored feedback are rendered as `FORMAT_PLAIN` and stored after `clean_param(..., PARAM_NOTAGS)` so prompt-injection echoes cannot smuggle markup into another user's browser.
- **High** — `ajax.php` no longer mixes JSON body and URL parameters; the `mod/airoleplay:grade` capability is now flagged with `RISK_XSS`.
- **High** — Outbound OpenAI requests are bounded by an absolute deadline, with `CURLOPT_CONNECTTIMEOUT`, `CURLOPT_SSL_VERIFYPEER` and `CURLOPT_SSL_VERIFYHOST` pinned explicitly.
- **Medium** — Participants can now withdraw GDPR consent and delete every submission/message they own on the activity (Art. 7(3) RGPD).
- **Medium** — Submission state transitions (`draft → active → submitted → graded` and the workflow states) are serialised through `\core\lock\lock_config` and validated against an explicit whitelist (`mod_airoleplay\local\submission_state`).
- **Medium** — Rate-limit cache TTL bumped to 24 h so the 5-minute regen cooldown and the 5/day per-submission cap actually fire as advertised.
- **Medium** — Exception messages and stack traces are routed to PHP `error_log` (server-side only) rather than `debugging()`, which previously surfaced prompt fragments and request bodies under `$CFG->debugdisplay = 1`.
- **Low** — All 4xx ajax responses use a single generic message; rejected actions are logged server-side instead of echoed back.

### Fixed

- **Functional** — The avatar now greets the human participant by their actual Moodle first name. Previously the system prompt left the participant nameless and the model would borrow the second avatar's name. The "OTHER PARTICIPANTS IN THE SCENE" wording was also renamed to "OTHER AI INTERLOCUTORS" to remove the ambiguity.

### Added

- New `mod_airoleplay\privacy\anonymizer`, `mod_airoleplay\local\prompt_guard` and `mod_airoleplay\local\submission_state` utilities, plus a PHPUnit suite under `tests/` covering each.

### Changed

- Plugin maturity raised from `MATURITY_ALPHA` to `MATURITY_BETA`.

---

## [0.1.0] — 2025-04-21

### Added

- **Core activity structure**: Moodle 4.5 compliant activity module with full Plugin API integration.
- **Configurable roleplay setup**:
  - 1, 2 or 3 AI avatars per activity.
  - Per-avatar name, role, personality prompt, TTS voice, and avatar image selection.
  - Configurable scenario description and participant role text.
  - Session duration timer (configurable in minutes).
- **Immersive roleplay UI**:
  - Push-to-talk button with Web Speech API transcription.
  - Real-time TTS audio playback with avatar talking/idle video animation.
  - Avatar addressing detection: participant can address a specific avatar by name.
  - Round-robin rotation fallback for multi-avatar activities.
  - Countdown timer with audio warning beeps at 2 minutes and 30 seconds.
  - Scrolling conversation log sidebar.
- **AI integration**:
  - Centralised `openai_client` singleton with retry/backoff, rate limiting, and content moderation.
  - `roleplay_conductor` class manages multi-avatar conversation flow.
  - `evaluator` class generates structured JSON grades and feedback via GPT.
  - Evaluation dimensions: communication, role adherence, scenario handling, language quality.
  - OpenAI TTS with 6 voice options (alloy, echo, fable, onyx, nova, shimmer).
  - Prompt injection protection via content delimiters in all API calls.
- **Student anonymisation**: SHA-256 hashed student IDs in all API prompts — real names never sent to OpenAI.
- **GDPR compliance**: Full `core_privacy\local\metadata\provider` implementation with data export and deletion.
- **Grading**: Moodle Gradebook integration, AI-generated structured feedback with per-dimension breakdown, optional teacher review workflow.
- **Notifications**: Student grade release notification, teacher submission review notification.
- **Backup/Restore**: Full Moodle Course Backup 2 compatibility (API keys excluded for security).
- **Internationalisation**: Complete string files for English (`en`), Spanish (`es`), and Brazilian Portuguese (`pt_br`).
- **3 video avatars**: Idle and talking loop videos for 3 avatar personas.
- **Global admin settings**: API keys, model selection, security filters, GDPR notice customisation.
- **Capabilities**: `view`, `submit`, `grade`, `viewallsubmissions`, `manageplugin`.
- **Overrides**: Per-user and per-group overrides for max attempts and time windows.
- **Activity completion rules**: Submit, receive grade, achieve minimum grade.
- **Security**: CSRF protection, capability checks on all endpoints, rate limiting for regen operations, sanitised teacher prompts.
