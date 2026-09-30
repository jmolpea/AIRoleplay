# AI Roleplay — Data processing statement (for buyers and reviewers)

## What is stored in Moodle
Per attempt: status, timing, consent flag and time, conversation transcript, per-turn message log, AI evaluation (scores, feedback, flags), final grade, teacher feedback, grader id, review state. Per override: user or group, attempts and dates. All covered by the Moodle Privacy API (export, deletion per user, per context and per user list).

## What leaves Moodle
Only to the AI provider selected by the site administrator (OpenAI, Anthropic, Google or DeepSeek), using the institution's own API key:

| Data | When | Purpose |
|---|---|---|
| Student's **first name** | Each avatar turn | So the character can address the student |
| Student's replies (text) | Each turn | To generate the character's answer |
| Conversation transcript | Once, after the session | To grade it |
| Scenario, role, avatar personalities, teacher's evaluation instructions | Each call | Context set by the teacher |
| Avatar lines (text) | Each turn, if a server voice is selected | Speech synthesis (OpenAI or Gemini) |

Before sending, the student's **surname, full name, username and email** are replaced by an anonymous `STUDENT-<hash>` token (salted SHA-256). Audio from the student's microphone is **not** sent to the AI provider: speech is transcribed by the student's browser (Web Speech API; Chrome and Edge use their vendor's speech service, Safari uses Apple's).

## Consent and withdrawal
Students must accept a notice (text editable by the administrator) before their first session. They can withdraw consent at any time from the activity page: their transcripts, message logs and AI feedback are deleted. The attempt count and any grade already recorded are kept as the institution's assessment record; administrators can erase everything through Moodle's privacy tools.

## Security measures
Encrypted API keys (Moodle encryption), sesskey and capability checks on every action, per-user and site-wide rate limits, cooldown and daily cap on teacher regenerations, prompt-injection detection and delimiting, AI output stored as plain text (no HTML), provider error details only in the server log, optional OpenAI moderation of student replies (when OpenAI is the chat provider, each reply is also sent to OpenAI's moderation endpoint before the avatar answers).

## Provider choice note
DeepSeek processes data on servers in China. Institutions subject to GDPR should review this before selecting it. OpenAI, Anthropic and Google offer data-processing agreements for API customers; the institution should sign the one that applies.
