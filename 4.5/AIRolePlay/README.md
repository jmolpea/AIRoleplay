# AI Roleplay — Moodle Activity Plugin (`mod_airoleplay`)

An AI-powered roleplay training plugin for Moodle 4.5 that immerses participants in conversational scenarios with up to three configurable AI avatars. Ideal for practising soft skills, professional communication, language learning, negotiation, customer interactions, and real-world meeting simulations.

---

## Requirements

| Requirement | Minimum |
|---|---|
| Moodle | 4.5 (build 2024042200) |
| PHP | 8.1+ |
| Database | MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 13+ |
| Browser | Chrome 90+, Edge 90+ (Web Speech API required for STT) |
| Server | HTTPS mandatory (Web Speech API requires a secure origin) |
| OpenAI API key | Required — GPT-4o, TTS endpoints |
| Moodle cron | Must be running regularly (fallback evaluation tasks) |

---

## Installation

### Method 1: Moodle Plugin Directory (recommended)

1. Download the plugin zip from the Moodle Plugin Directory.
2. In Moodle: **Site administration → Plugins → Install plugins**.
3. Upload the zip and follow the on-screen prompts.
4. Complete the database upgrade steps.

### Method 2: Manual installation

```bash
# Unzip into the Moodle mod directory
unzip mod_airoleplay.zip -d /path/to/moodle/mod/airoleplay

# Fix permissions (Linux)
chown -R www-data:www-data /path/to/moodle/mod/airoleplay
chmod -R 755 /path/to/moodle/mod/airoleplay
```

Then visit **Site administration → Notifications** to run the database installer.

---

## Post-Installation Configuration

1. **Configure the OpenAI API Key**
   Go to **Site administration → Plugins → Activity modules → AI Roleplay**.
   Enter your OpenAI API key in the "Primary OpenAI API Key" field.
   The key is stored encrypted using Moodle's built-in encryption.

2. **Select AI models**
   Choose the GPT model for roleplay conversations and for final evaluation.

3. **Customise the GDPR notice**
   Edit the privacy notice text to match your institution's data processing policies.

4. **Configure the anonymisation salt** (optional)
   A random salt is used when hashing student IDs before sending to OpenAI. Set this to a long random string specific to your installation.

5. **Verify cron is running**
   Evaluation fallback tasks run as Moodle adhoc tasks. Ensure `cron.php` or `cli/cron.php` runs regularly.

---

## Creating an Activity

1. In a course, **Add an activity → AI Roleplay**.
2. Configure the roleplay:
   - **Scenario**: Describe the situation the participant will enter.
   - **Participant role**: Describe the character/role the student will play.
   - **Number of avatars**: Choose 1, 2 or 3 AI interlocutors.
   - **Avatar configuration**: For each avatar set name, role, personality prompt, TTS voice, and avatar image.
   - **Session duration**: How many minutes the roleplay will last.
3. Set grading options (maximum grade, review workflow, notifications).
4. Save and return to course.

---

## Student Workflow

1. Student opens the activity and accepts the GDPR/privacy notice.
2. Reads the scenario and their assigned role.
3. Clicks **Start** to begin the timed session.
4. Interacts with the AI avatars using the push-to-talk button (voice) or by typing.
5. When time is up, the session ends automatically.
6. The AI generates a comprehensive grade and structured feedback.
7. If the grading workflow is disabled, the grade is published immediately.

---

## Teacher Workflow

1. Navigate to **AI Roleplay → Submissions** to see all student submissions.
2. Review each submission's transcript.
3. If grading workflow is enabled:
   - Review the AI-generated grade and feedback.
   - Adjust if needed and click **Publish grade** to release it to the student.
4. Use **Overrides** to give specific users or groups extended attempts or custom time windows.

---

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---|---|---|---|---|
| Web Speech API (STT) | ✅ | ⚠️ Partial | ✅ | ✅ |
| Audio playback (TTS) | ✅ | ✅ | ✅ | ✅ |
| Video avatar playback | ✅ | ✅ | ✅ | ✅ |

> **Note**: Web Speech API push-to-talk is fully supported in Chrome and Edge. Firefox support is partial.

---

## Security Notes

- Student names are **never** sent to OpenAI. They are replaced with `STUDENT-<sha256hash>`.
- API keys are stored encrypted using `\core\encryption`.
- CSRF protection (`sesskey`) is enforced on all write operations.
- Participant input is wrapped in security delimiters before being sent to the model to prevent prompt injection.
- Teacher-authored scenario and evaluation prompts are sanitised before use.
- Rate limiting prevents API abuse per user.

---

## Estimated OpenAI Costs (per student session)

| Component | GPT-4o | GPT-4o mini |
|---|---|---|
| Roleplay (10 min, ~10 turns) | ~$0.05–$0.10 | ~$0.01–$0.02 |
| TTS (~10 responses, ~80 words each) | ~$0.02 | ~$0.02 |
| Final evaluation | ~$0.02 | ~$0.005 |
| **Total per student** | **~$0.09–$0.15** | **~$0.03–$0.05** |

---

## Frequently Asked Questions

**Q: Can I use this without HTTPS?**
A: No. Web Speech API requires a secure (HTTPS) origin. You must have a valid SSL certificate.

**Q: What happens to student data sent to OpenAI?**
A: Content is anonymised before sending (student name replaced with a hash). Per OpenAI's API terms, data is not used to train models.

**Q: Can the avatars speak a language other than English?**
A: Yes. The avatar prompts, scenario, and participant role can all be written in any language. The plugin automatically detects the Moodle site language and instructs the AI to respond accordingly. The Web Speech API language attribute is also set to match the current Moodle language.

**Q: Can I have more than 3 avatars?**
A: The current version supports 1, 2, or 3 avatars per activity. This is a planned enhancement for a future release.

**Q: Can a student retake the activity?**
A: Yes. The `max_attempts` setting controls how many attempts a student can make. Set to 0 for unlimited.

---

## License

GNU General Public License v3 or later — see [LICENSE](https://www.gnu.org/copyleft/gpl.html).

---

## Support & Bug Reports

Please report issues at the [Moodle Plugin Directory](https://moodle.org/plugins) tracker or open a GitHub issue.
