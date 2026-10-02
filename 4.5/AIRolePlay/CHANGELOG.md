# Changelog — mod_airoleplay

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/).

---

## [1.0.2] — 2026-10-02

Fixes for the findings of the Moodle Plugins directory review.

### Changed

- **External Services instead of `ajax.php`.** The browser now talks to Moodle through six web service functions (`mod_airoleplay_start_session`, `submit_turn`, `close_session`, `finalise_session`, `get_evaluation_status`, `regenerate_evaluation`) declared in `db/services.php` and called with `core/ajax`. `ajax.php` was removed. Session, capability, licence and rate-limit checks are unchanged and live in the service classes (`classes/external/`).
- **Default consent notice.** It now says that the *text* of the replies goes to the AI provider, that spoken replies are transcribed by the browser's speech recognition service — which may process the audio on the browser vendor's servers (Google, Microsoft or Apple) — and that students can type instead. Sites that already saved the plugin settings keep their stored notice: review *Site administration > Plugins > Activity modules > AI Roleplay > GDPR notice text*.

### Fixed

- **Activity index page.** `index.php` ended in "Call to undefined method … create_from_course()"; the `course_module_instance_list_viewed` event is now created the core way.
- **Group access on regeneration.** In separate groups mode, a teacher without `moodle/site:accessallgroups` could regenerate the evaluation of a student outside their groups by sending the attempt id. The service now makes the same group check as the submissions page.
- **Missing language strings** for the rate-limit cache definition and the two message providers (`cachedef_ratelimit`, `messageprovider:gradenotification`, `messageprovider:submissionnotification`).
- **Privacy API.** A teacher's export now includes the attempts they graded (attempt number, grade, feedback, review state and dates — never the student's identity or conversation). The browser speech recognition service is declared as an external location.

### Added

- `LICENSE` file (GNU GPL v3) in the plugin root.
- GitHub Actions workflow running `moodle-plugin-ci` on every push.
- Unit tests for the group check of the regeneration service and for the grader export.

---

## [1.0.1] — 2026-09-30

### Added

- **Site-wide control of teacher review.** New setting *Site administration > Plugins > Activity modules > AI Roleplay > Grading > Teacher review before release*: it sets the default for new activities (teacher review, unless the administrator chooses automatic grading) and, when *Locked*, imposes the choice on every activity, existing ones included. Teachers keep the per-activity option otherwise. Evaluations with integrity warnings always wait for a teacher.

### Changed

- All page output now goes through Mustache templates (`templates/`) and output classes (`classes/output/`) instead of HTML built in the page scripts; helper functions left the page scripts for `lib.php`.
- The OpenAI moderation check now runs only on the participant's replies; the opening and closing lines, built from plugin prompts alone, no longer pay for a moderation call.

### Fixed — reported issue: "the avatar sometimes takes about a minute to answer"

- **Voice synthesis could hang a turn for two minutes.** The server logs showed the chat model (GPT-6 Sol) answering in 1–3 seconds every time; the delay came from OpenAI's text-to-speech endpoint stopping mid-stream while keeping the connection open, which the plugin waited out for the full API timeout (120 s) before showing the line without audio. Speech requests now have their own 25-second budget, stalled transfers are detected within about 12 seconds and retried, and Gemini speech gets the same protection.
- Live roleplay turns (chat call, retries included) are capped at 45 seconds and the OpenAI moderation check at 10 seconds, whatever the site-wide API timeout. Evaluations keep the full timeout.
- The AJAX endpoint now releases the PHP session lock before calling the AI, so a slow provider no longer blocks the user's other requests (Moodle notifications, a second tab).
- When a model rejects the reasoning-effort parameter, the automatic retry now raises the output-token cap, so the fallback cannot end in an empty reply.
- Developer-mode request logs now include the duration and attempt number of every provider call.

### Fixed — review findings

- Overrides: deleting used an inline `onclick` confirmation (now a confirmed POST button); a user or group could get two overrides, the second silently ignored (now rejected); the user selector always showed e-mail addresses (now follows the site's "Show user identity" setting and the viewer's permissions); closing dates before opening dates were accepted.
- SVG files are no longer accepted as custom avatar images (they can carry scripts).
- The activity index page now triggers the standard `course_module_instance_list_viewed` event and lists sections.
- Sessions closed by the scheduled task now update the "completed a session" completion rule, as sessions closed in the browser already did.
- Restoring a backup no longer creates attempts without an owner when the participant is not part of the restore.
- Privacy API: a missing grader no longer reaches the user list.
- `mod/airoleplay:addinstance` now clones its permissions from `moodle/course:manageactivities`.
- The licence check runs once per request instead of on every AI call.
- Remaining hard-coded English UI text (avatar fallback names, session durations) now uses language strings; `styles.css` is no longer requested twice; the unused `db/events.php` and `workflow_readyforrelease` string were removed; unit-test coverage metadata now satisfies the coding standard on both Moodle 4.5 and 5.x.

---

## [1.0.0] — 2026-09-28

First stable release. Supports Moodle 4.5 LTS, 5.0, 5.1, 5.2 and 5.3 LTS.

### Fixed — reported issue: "the microphone did not work and the student passed anyway"

- **Silent microphone failures.** Browsers without speech recognition (e.g. Firefox), a denied microphone permission, a missing microphone, an insecure (http) page and a blocked speech service were all ignored: the student saw a push-to-talk button that did nothing. The microphone is now checked *before* the timer starts, every failure is explained on screen, and the session switches to typed replies.
- **Grades built from the avatars' lines.** The evaluator graded the whole transcript even when the student never spoke. A session without participant input now scores 0 without calling the AI and is always sent to teacher review; fewer than 20 words is flagged; the prompt states that only the participant's lines may earn credit.
- **Integrity flags ignored.** Flagged evaluations (no participation, suspected prompt manipulation, incomplete rubric) were auto-published when the review workflow was off. They now always wait for the teacher.
- Push-to-talk no longer gets stuck when the pointer leaves the button; it works with touch, pen and the keyboard (Space/Enter).

### Fixed — other bugs

- The session clock only existed in the browser: reloading restarted the timer and generated a second opening. The deadline is now kept on the server, reloading resumes the conversation, and abandoned sessions are closed and graded by a scheduled task.
- A failure to reach the AI when starting (missing key, outage) consumed the attempt and started the clock. The attempt now stays unstarted.
- Students could never start a second attempt, although "maximum attempts" was configurable. New attempts are now offered after grading; the best released grade goes to the gradebook.
- User/group overrides could not be saved (a hidden form field overwrote the course module id) and, when present, were never applied. Both fixed; overrides now change attempts and availability.
- "Publish grade" in Submissions saved the grade but never published it. Editing a released grade did not update the gradebook; deleting an attempt left its grade behind; grades were not range-checked.
- Custom avatar images were accepted by the form but never stored or shown.
- The admin "GDPR notice text" setting was ignored.
- The background evaluation task swallowed errors, so a failed evaluation was never retried and the student waited forever.
- A gradebook or messaging failure after an evaluation was stored reverted the attempt and paid for a second evaluation.
- Conversation history could be sent to the model out of order (turn numbers came from the browser) and the student's last line was sent twice.
- Anonymisation replaced the student's surname inside other words ("Sol" in "solución").
- The feedback language followed whoever triggered the evaluation (teacher or cron) instead of the student.
- With debug display on, developer messages were printed into the AJAX responses and broke the session.
- The transcript collapsed to a few pixels on laptop screens.
- `deepseek-chat` stopped working when DeepSeek retired it (2026-07-24); retired ids are now mapped to current models.

### Security

- The RSA licence-signing key and the licence generator lived inside the plugin folder, which the web server serves in development. They were moved out of the plugin; the generator refuses to run outside the CLI.
- Provider error texts (which can quote request content or part of an API key) are no longer shown to students; they go to the server log.
- Moderation checks only the student's latest line, and never the evaluation.
- Withdrawing consent no longer resets the attempt counter.
- Separate-groups mode is respected in Submissions. Privacy API: overrides and graders are covered; grader ids are cleared when a teacher's data is deleted.
- Backups without user data no longer carry user overrides.

### Added

- Model catalogue: GPT-6 Sol, Luna and Astra; Claude Sonnet 5, Haiku 4.5, Opus 5.5 and Opus 5; Gemini 3.8 Flash, 3.5 Flash-Lite and 3.1 Pro; DeepSeek V4.1 Flash and V4 Pro. Recommended defaults per provider for roleplay and evaluation. Previous models remain selectable for existing activities.
- Per-provider latency tuning: live turns use the lowest thinking effort each model allows (e.g. GPT-6 `reasoning_effort: none`, Claude `effort: low`, Gemini `thinkingLevel: low`, DeepSeek thinking off); evaluations use more.
- If a provider rejects an optional tuning parameter, the request is retried once without it.
- Voice options: browser voices (free) and text-only, besides OpenAI and Gemini (Gemini 3.8 Flash TTS).
- "Test AI connection" page for administrators.
- Typed replies, availability dates, activity dates in the course page, custom completion rule, course reset support.
- Teacher view shows integrity flags, avatar names and strengths/areas for improvement; grades can be withdrawn back to review.
- PHPUnit suite extended to 78 tests (evaluator guard, session clock, attempts and overrides, model catalogue, privacy provider).

### Changed

- Minimum Moodle version corrected to 4.5 (2024100700); maturity STABLE.
- Site-wide rate limit default raised from 60 to 300 calls/minute (60 throttled a single class).
- Evaluation runs in its own request after the goodbye line, so students no longer wait for the grader to hear the ending.
- Unused per-activity columns removed (per-activity API key, safety knobs, grade completion columns).

## [0.4.0] — 2026-08-05

### Added

- **GPT-5.6 family.** `gpt-5.6-sol`, `gpt-5.6-terra` and `gpt-5.6-luna` join the OpenAI catalogue. All three speak Chat Completions, so no API migration was needed.
- **Reasoning-model support in the OpenAI provider.** Requests now carry `reasoning_effort` (`low` for live roleplay turns, where latency is what the student feels; `medium` for the asynchronous evaluation, where judgement matters). The parameter is only sent to models that accept it: non-reasoning models such as GPT-4o never receive it, and GPT-5.6 Luna is explicitly excluded because it rejects the parameter despite producing reasoning tokens. Sampling parameters (`temperature`, `top_p`) are never sent to any model, so one request shape stays valid across the whole catalogue.
- The evaluator now uses strict `json_schema` structured outputs on OpenAI instead of plain `json_object`. The rubric schema was already strict-compatible, so this removes a class of parse failures without changing the contract.

### Changed

- **Default model is now Claude Sonnet 5** (`claude-sonnet-5`) for both roleplay and evaluation. Existing activities keep the model their teacher selected; only the column default and the runtime fallbacks change. The site-wide chat provider default stays `openai`, so on a fresh install the activity form still offers the OpenAI catalogue and preselects its first entry — each provider catalogue is now ordered with its preferred model first (GPT-5.6 Luna, Claude Sonnet 5, Gemini 2.5 Flash) so that fallback is always the sensible one rather than the most expensive.
- The default model lives in a single constant, `mod_form_helper::DEFAULT_MODEL`. It was previously hardcoded in five places (`mod_form.php`, `db/install.xml`, `roleplay_conductor`, `evaluator`, `settings.php`), which could drift apart.
- OpenAI TTS moved from the legacy `tts-1` to `gpt-4o-mini-tts`. Same voice ids, same MP3 output.

### Fixed

- **Reasoning models could return empty avatar replies.** Hidden reasoning tokens are billed and counted against `max_completion_tokens`, and they are produced before any visible text, so the 512-token cap used for roleplay turns let the model spend its whole budget thinking and return a 200 with no content. The OpenAI provider now raises the cap to a 16,000-token floor for reasoning models only.
- An empty model response is no longer returned to the caller as an empty string. The provider raises an error carrying `finish_reason` and the model id, so a truncated or refused generation surfaces instead of showing the student a silent avatar.

## [0.3.0] — 2026-07-07

### Added

- **Multi-provider AI support.** The plugin can now use Anthropic (Claude Sonnet 5, Claude Haiku 4.5), Google Gemini (2.5 Flash / Pro) and DeepSeek (deepseek-chat) in addition to OpenAI. The provider is inferred from the model id selected in each activity; per-provider encrypted API keys are configured in the plugin settings, and a model only appears in the activity form when its provider's key is set.
- **Selectable TTS provider.** A new site-wide setting chooses between OpenAI voices (MP3) and Google Gemini voices (WAV, PCM wrapped server-side). Anthropic and DeepSeek offer no TTS, so chat and voice providers are configured independently. The frontend now honours the audio MIME type sent by the server.
- New provider abstraction layer (`classes/api/provider/`): `chat_provider` / `tts_provider` interfaces, a shared `base_provider` (license gate, encrypted keys, rate limiting, retrying HTTP) and adapters for OpenAI, Anthropic (Messages API with structured outputs for the evaluator), Gemini (generateContent + TTS) and DeepSeek (OpenAI-compatible). `provider_factory` replaces the old `openai_client` singleton.
- Privacy API now declares all four possible external destinations (OpenAI, Anthropic, Google, DeepSeek), with an explicit warning that DeepSeek processes data on servers in China.

### Changed

- **Settings redesign:** the per-model checkboxes were replaced by a site-wide *Chat provider* select plus one model multi-select per provider (only the selected provider's list is shown, via `hide_if`). The OpenAI catalogue was expanded (GPT-5.1, GPT-5, GPT-5 mini, GPT-4.1, GPT-4.1 mini, GPT-4o, GPT-4o mini); reasoning models are sent `max_completion_tokens` as the API requires. Anthropic gains Claude Opus 4.8 and Gemini gains 2.5 Flash-Lite.
- **New activity icon** following Moodle 4.x guidelines: `pix/monologo.svg` (monochrome glyph, no background — a speech bubble with a voice waveform); `pix/icon.svg` uses the same glyph for legacy themes.
- **TTS playback hardening:** the avatar audio player now resumes a suspended `AudioContext` (browser autoplay policies), resolves through an idempotent `finish()` and carries duration-based plus 60-second watchdogs, so a stalled decode or a never-firing `onended` can no longer freeze the session start.
- The evaluator requests JSON via each provider's native mechanism (OpenAI/DeepSeek `response_format`, Gemini `responseMimeType`, Anthropic structured outputs with the rubric schema) and tolerates markdown-fenced JSON.
- Messages sent to Anthropic/Gemini are normalised (system prompt extracted, consecutive same-role turns merged, conversation forced to open with a user turn) to satisfy their strict turn-taking rules.
- The OpenAI moderation content filter now applies only when the activity uses an OpenAI chat model.
- The duplicated model/voice option lists in `mod_form.php` were removed; `mod_form_helper` is the single source of truth.
- GDPR consent texts and settings descriptions are provider-neutral.

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
