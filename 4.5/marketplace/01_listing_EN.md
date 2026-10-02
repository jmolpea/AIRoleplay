# Moodle Marketplace listing — AI Roleplay (paste-ready, English)

> The Marketplace listing is in English. Copy each block into the matching field.

---

## Plugin name
AI Roleplay

## Frankenstyle component
mod_airoleplay

## Plugin type
Activity module

## Short description (1–2 sentences)
Voice roleplay simulations with AI characters and AI-assisted grading. Students talk with one to three AI avatars in a scenario you write, and get a grade and personal feedback as soon as the session ends, or once a teacher has reviewed it.

## Tagline (if a shorter one is requested)
Practise real conversations with AI characters. Get AI feedback in minutes.

---

## Full description

**Give every student a realistic conversation to practise — as many times as they need.**

AI Roleplay turns any scenario into a spoken simulation. A student joins a room with one, two or three AI characters: an upset customer, a demanding manager, a patient, a job interviewer, a native speaker. They talk out loud, the characters answer with natural voices and react to what the student says, and after the session an AI evaluator grades the conversation and explains what went well and what to improve.

Teachers write the scenario in plain language. There are no dialogue trees to build and no scripts to maintain.

### Why teachers choose it

- **Real practice, not multiple choice.** Students speak, listen and think on their feet, the way they will at work or in an exam.
- **Instant, specific feedback.** A grade out of your chosen maximum, a rubric breakdown (communication, role adherence, scenario handling, language quality), strengths and areas for improvement, all quoting what the student actually said.
- **Teachers stay in control.** Read every transcript, regenerate an evaluation, adjust the grade and feedback, and publish when ready. By default every AI grade waits for a teacher to review and release it; administrators can switch the site to automatic publishing, and can lock either choice for every activity.
- **Fair by design.** Only the student's words are graded. If a student said nothing (for example, a broken microphone), the attempt is not passed on the avatars' lines: it scores 0 and is sent to the teacher with an explanation.
- **Works for every student.** Push-to-talk with mouse, touch or keyboard. If the browser cannot recognise speech or the microphone is blocked, the page explains why and the student replies by typing. Reloading the page resumes the session with the time really left.

### Use cases

- Customer service and complaint handling
- Sales, negotiation and objection handling
- Job interviews and HR conversations
- Healthcare communication: breaking bad news, patient handovers
- Leadership: feedback conversations, difficult meetings
- Language learning: speaking practice with native-like partners in 30+ languages
- Ethics and decision-making debates with characters holding opposing views

### Features

- 1 to 3 AI avatars per activity, each with name, role, personality, voice and look (three animated avatars included, or upload your own image)
- Students can address an avatar by name to make it answer
- Server-kept session timer (5–30 minutes), resume after reload, automatic closing of abandoned sessions
- Automatic AI grading with a four-part rubric plus optional teacher instructions
- Teacher review before release or automatic publishing, set per activity or imposed site-wide by the administrator; evaluations with integrity warnings always wait for a teacher
- Teacher review page with transcripts and integrity flags, regenerate evaluation, manual grade override
- Multiple attempts (best grade to the gradebook), user and group overrides, availability dates
- Activity completion: "complete a roleplay session" and standard grade conditions
- Gradebook, backup/restore, course reset, Privacy API (export and deletion), groups and groupings
- Speaks the course language: English, Spanish and Brazilian Portuguese interface included; conversations in any language the model supports

### Choose your AI provider (bring your own key)

AI Roleplay works with the provider your institution already trusts. Keys are stored encrypted and you pay the provider directly, with no mark-up.

| Provider | Recommended models (preselected) |
|---|---|
| OpenAI | GPT-6 Sol (also GPT-6 Luna, GPT-6 Astra) |
| Anthropic | Claude Sonnet 5 (also Claude Haiku 4.5, Opus 5.5, Opus 5) |
| Google | Gemini 3.8 Flash (also 3.5 Flash-Lite, 3.1 Pro) |
| DeepSeek | DeepSeek V4.1 Flash / V4 Pro |

Voices: OpenAI or Google Gemini (natural voices), free browser voices, or text only.

Typical AI cost of a 10-minute session with the recommended models: about US$0.01–0.15, depending on the provider.

### Privacy and security

- Student surname, username and email are replaced by an anonymous token before any text is sent to the AI provider.
- Consent notice before the first session (text editable by the administrator); students can withdraw consent and delete their conversations.
- Encrypted API keys, per-user and site-wide rate limits, protection against prompt manipulation, provider errors never shown to students.
- Optional OpenAI content moderation of student replies (on by default when OpenAI is the chat provider).
- Built-in "Test AI connection" page for administrators.

---

## Requirements
- Moodle 4.5 LTS, 5.0, 5.1, 5.2 or 5.3 LTS
- HTTPS (required by browsers for the microphone)
- An API key from OpenAI, Anthropic, Google Gemini or DeepSeek
- Moodle cron running
- A licence key for the site URL (delivered after purchase)
- Voice input: Chrome, Edge or Safari. Other browsers use typed replies.

## Supported Moodle versions (compatibility field)
4.5, 5.0, 5.1, 5.2, 5.3

## Supported languages (interface)
English, Spanish, Portuguese (Brazil)

---

## Installation / setup instructions (field "Installation")

1. Install the ZIP from **Site administration → Plugins → Install plugins** and complete the upgrade.
2. Go to **Site administration → Plugins → Activity modules → AI Roleplay**.
3. Paste the licence key you received after purchase. The status must read *Valid*.
4. Paste the API key of your AI provider and select it as *Chat provider*. The recommended models are preselected.
5. Choose how the avatars speak (OpenAI or Gemini voices, browser voices, or text only).
6. Under **Grading**, decide whether AI grades wait for teacher review (default) or are published automatically, and whether to lock that choice for every activity.
7. Click **Run connection test**: every line must show *OK*.
8. In a course: **Add an activity → AI Roleplay**, write the scenario and the student's role, configure the avatars and save.

The licence key is bound to the exact site URL (wwwroot). Need a key for a test or staging site? Contact support.

---

## What's new in 1.0.2 (release notes field)

- Fixes from the Moodle Plugins directory review: the activity index page no longer fails; teachers in separate groups can only regenerate evaluations of their own groups; missing language strings added; LICENSE file included.
- The browser now talks to Moodle through External Services (`core/ajax`) instead of a custom `ajax.php` endpoint.
- Privacy: the consent notice and the documentation now explain that spoken replies are transcribed by the browser's speech service, which may process audio on the browser vendor's servers; teachers' data exports include the attempts they graded.

## What's new in 1.0.1

- Faster, more reliable conversations: a stalled voice response can no longer hold a turn for two minutes. Speech is retried within seconds, live turns have a strict time limit, and moderation runs only on student replies.
- New site setting *Teacher review before release*: choose teacher review (default) or automatic publishing for new activities, and optionally lock the choice for every activity.
- All pages rebuilt on Moodle templates; security and accessibility refinements to user/group overrides; SVG images are no longer accepted as custom avatars.
- Completion is updated when a session is closed by the scheduled task; backup/restore and privacy edge cases fixed.

## What's new in 1.0.0

First stable release.
- New model catalogue: GPT-6, Claude Sonnet 5 / Opus 5.5 / Haiku 4.5, Gemini 3.8, DeepSeek V4.
- Microphone check before the timer starts, clear messages for every microphone problem, typed replies as fallback.
- Sessions with no student participation are never passed: they score 0 and go to teacher review.
- Server-side session timer with resume after reload.
- Multiple attempts, user/group overrides and availability dates.
- Browser voices (free) and text-only mode, "Test AI connection" page.
- Compatible with Moodle 4.5 to 5.3; tested on MariaDB and PostgreSQL.

---

## FAQ (field "FAQ" or documentation page)

**Do students need a microphone?**
No. Voice is recommended, but students without a microphone, or on a browser without speech recognition, type their replies.

**Which browsers support voice?**
Chrome, Edge and Safari. The page must be served over HTTPS.

**Who pays for the AI?**
Your institution, directly to the AI provider, using your own API key. The plugin never adds a mark-up.

**Which provider should we choose?**
Use the one your institution already has an agreement with. For the best quality/latency balance we preselect GPT-6 Sol (OpenAI) or Claude Sonnet 5 (Anthropic).

**Can a student pass without talking?**
No. Only the student's own words are graded. A silent session scores 0 and is flagged for the teacher.

**Can teachers change a grade?**
Yes. Teachers can edit the grade and feedback, regenerate the evaluation, and choose when grades are released.

**Are grades published automatically?**
Your choice. By default each AI grade waits for a teacher to review and publish it. An administrator can make automatic publishing the default, or lock either option for every activity. Evaluations with integrity warnings (no or very little participation, suspected manipulation) always wait for a teacher.

**What does the OpenAI content moderation do?**
When OpenAI is the chat provider, each student reply is checked with OpenAI's free moderation service before the avatar answers; flagged replies (harassment, hate, violence, sexual content, self-harm) get no answer and the student sees a notice. It adds about one second per turn and can be switched off by the administrator; the other providers rely on their built-in safety systems.

**Is student data sent to the AI provider?**
Only what is needed to run the conversation: the student's first name (so the avatar can greet them) and what they say. Surname, username and email are replaced by an anonymous token. See the privacy statement.

**Does it work in languages other than English?**
Yes. The avatars speak and the feedback is written in the course/user language.

**What happens if the session is interrupted?**
Reloading the page resumes the session with the remaining time. If the student leaves, the session is closed and graded automatically when time runs out.

**How is the licence delivered and bound?**
You receive a licence key for your site URL. It is validated offline (no call home). Annual licences show their expiry date in the plugin settings.

---

## Keywords / tags
AI, artificial intelligence, roleplay, role play, simulation, conversation, speaking, voice, oral assessment, soft skills, customer service, language learning, ESL, communication, feedback, automatic grading, avatar, ChatGPT, OpenAI, Claude, Gemini, training, healthcare communication, interview practice

## Category
Activities → Assessment / Communication
