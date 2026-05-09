# Changelog — mod_airoleplay

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/).

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
