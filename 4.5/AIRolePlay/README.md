# AI Roleplay (`mod_airoleplay`)

Voice roleplay simulations for Moodle. Students talk, out loud, with one to three AI characters in a scenario the teacher writes (a difficult customer, a job interview, a negotiation, a clinical handover, a conversation in a foreign language). When the time is up, an AI evaluator grades the conversation against a four-part rubric and writes personalised feedback. Teachers can review every transcript and adjust or publish each grade.

---

## Requirements

| | |
|---|---|
| Moodle | 4.5 LTS, 5.0, 5.1, 5.2 and 5.3 LTS |
| PHP | 8.1 or later (whatever your Moodle version requires) |
| Database | MySQL, MariaDB or PostgreSQL (tested on MariaDB 10.11 and PostgreSQL 17) |
| HTTPS | Required for the microphone in the browser |
| Browser (voice) | Chrome, Edge or Safari. Other browsers, or students without a microphone, reply by typing |
| AI provider | An API key from **one** of: OpenAI, Anthropic (Claude), Google Gemini or DeepSeek |
| Cron | Must run regularly (closes abandoned sessions and retries evaluations) |
| Licence | A licence key for the site URL (sold per site) |

---

## Installation

1. **Site administration → Plugins → Install plugins**, upload the ZIP and follow the upgrade screens.
   (Or unzip into `mod/airoleplay` — `public/mod/airoleplay` on Moodle 5.1+ — and visit **Site administration → Notifications**.)
2. Open **Site administration → Plugins → Activity modules → AI Roleplay**.
3. Paste your **licence key**. The status line must show *Valid*.
4. Paste the **API key** of your AI provider and choose it as **Chat provider**.
5. Choose the **voice provider**: OpenAI or Gemini (natural voices, small cost per reply), *Browser voices* (free) or *No voice*.
6. Press **Run connection test**. Every row must show *OK*.

The recommended models are preselected: they give natural, low-latency conversations and reliable grading.

| Provider | Roleplay (default) | Evaluation (default) | Also available |
|---|---|---|---|
| OpenAI | GPT-6 Sol | GPT-6 Sol | GPT-6 Luna (fastest), GPT-6 Astra (premium) |
| Anthropic | Claude Sonnet 5 | Claude Sonnet 5 | Claude Haiku 4.5 (fastest), Claude Opus 5.5, Claude Opus 5 |
| Google | Gemini 3.8 Flash | Gemini 3.8 Flash | Gemini 3.5 Flash-Lite, Gemini 3.1 Pro (preview) |
| DeepSeek | DeepSeek V4.1 Flash | DeepSeek V4 Pro | |

Activities created with earlier models keep working. Retired model ids (for example `deepseek-chat`) are mapped to their current replacement automatically.

---

## Creating an activity

**Add an activity or resource → AI Roleplay**, then fill in:

- **Scenario** and **participant's role**: what the student sees and what the avatars know.
- **Avatars** (1–3): name, role, personality, voice and look (three built-in animated avatars or your own image).
- **Session duration**, **maximum attempts** and, optionally, **availability dates**.
- **Evaluation instructions** (optional): extra criteria for the AI grader.
- **Grading workflow**: when enabled, grades wait for the teacher before students see them.

User and group **overrides** (extra attempts, different dates) are under the activity's *More* menu.

---

## What students experience

1. Accept the privacy notice (once).
2. Press **Start**. The browser asks for the microphone *before* the timer starts. If there is no microphone, permission is denied or the browser cannot recognise speech, the page says why and switches to typed replies.
3. Hold **Hold to speak** (or the Space key) while answering. Address an avatar by name to make that avatar reply.
4. Reloading the page resumes the session with the time that is really left.
5. When time runs out the avatars close the scene and the evaluation starts. Results appear on the same page: grade, feedback, strengths, areas to improve and the rubric breakdown.

## Grading and review

- Rubric: communication 30 %, role adherence 25 %, scenario handling 25 %, language quality 20 %. The server recomputes the grade from the components.
- **Only the student's words are graded.** A session in which the student said nothing gets 0 and is always sent to teacher review (it is often a microphone problem, not a lack of effort). Very short participation, suspected prompt manipulation or an incomplete AI answer are also flagged for review.
- In **Submissions** teachers read each transcript, see the flags, regenerate an evaluation, edit the grade and feedback, publish or withdraw.
- With several attempts, the best published grade goes to the gradebook.
- Completion: "complete a roleplay session", plus the standard grade conditions.

---

## Privacy and security

- Implements the Moodle Privacy API (export and deletion). Declares every external provider.
- Before any text leaves Moodle, the student's last name, username and email are replaced by an anonymous `STUDENT-…` token. The first name is kept so the avatars can greet the student.
- Students can withdraw consent at any time; their conversations and AI feedback are deleted.
- API keys are stored encrypted. Provider errors are logged on the server and never shown to students.
- Rate limits per user and per site, plus a cooldown on teacher regenerations, cap AI spend.
- DeepSeek processes data in China: check your data-protection requirements before choosing it.

---

## Support

Pluginia — support contact and documentation are listed on the Moodle Marketplace page of the plugin.

## Licence

The plugin code is licensed under the GNU GPL v3 or later. Use of the service requires a paid licence key for each Moodle site.
