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
 * Administrator tool: checks that the configured AI providers answer.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

use mod_airoleplay\api\provider\chat_provider;
use mod_airoleplay\api\provider_factory;
use mod_airoleplay\form\mod_form_helper;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$run = optional_param('run', 0, PARAM_BOOL);

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/mod/airoleplay/testconnection.php'));
navigation_node::override_active_url(new moodle_url('/admin/settings.php', ['section' => 'modsettingairoleplay']));
$PAGE->set_title(get_string('testconnection', 'mod_airoleplay'));
$PAGE->set_heading(get_string('testconnection', 'mod_airoleplay'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testconnection', 'mod_airoleplay'));
echo html_writer::div(get_string('testconnection_desc', 'mod_airoleplay'), 'mb-3');

if (!$run) {
    echo $OUTPUT->single_button(
        new moodle_url('/mod/airoleplay/testconnection.php', ['run' => 1, 'sesskey' => sesskey()]),
        get_string('testconnection_run', 'mod_airoleplay'),
        'post',
        ['type' => single_button::BUTTON_PRIMARY]
    );
    echo $OUTPUT->footer();
    exit;
}

require_sesskey();
\core_php_time_limit::raise(180);

$results = [];

// License.
$license = \mod_airoleplay\license\validator::get_settings_status();
$results[] = [
    get_string('license_heading', 'mod_airoleplay'),
    \mod_airoleplay\license\validator::is_valid(),
    s($license['text']),
];

// Chat: the recommended roleplay and evaluation models of the site provider.
$provider = mod_form_helper::chat_provider();
$models = array_unique([
    mod_form_helper::default_model(mod_form_helper::PURPOSE_ROLEPLAY, $provider),
    mod_form_helper::default_model(mod_form_helper::PURPOSE_EVALUATION, $provider),
]);
foreach ($models as $model) {
    $label = get_string('testconnection_chat', 'mod_airoleplay', $model);
    try {
        $start = microtime(true);
        $reply = provider_factory::chat_provider_for_model($model)->chat(
            [
                ['role' => 'system', 'content' => 'You are a connectivity check.'],
                ['role' => 'user', 'content' => 'Reply with the single word OK.'],
            ],
            $model,
            ['max_tokens' => 32, 'profile' => chat_provider::PROFILE_REALTIME]
        );
        $ms = (int)round((microtime(true) - $start) * 1000);
        $results[] = [$label, true, get_string('testconnection_ok', 'mod_airoleplay', [
            'ms'    => $ms,
            'reply' => s(shorten_text($reply, 40)),
        ])];
    } catch (\Throwable $e) {
        $results[] = [$label, false, s($e->getMessage() . (!empty($e->debuginfo) ? ' — ' . $e->debuginfo : ''))];
    }
}

// Voice.
$tts = provider_factory::tts_provider();
$ttslabel = get_string('testconnection_tts', 'mod_airoleplay', mod_form_helper::tts_provider());
if ($tts === null) {
    $results[] = [$ttslabel, true, get_string('testconnection_tts_browser', 'mod_airoleplay')];
} else {
    try {
        $voices = mod_form_helper::get_default_voices();
        $audio  = $tts->speak('OK', $voices[1]);
        $results[] = [$ttslabel, true, get_string('testconnection_tts_ok', 'mod_airoleplay', [
            'bytes' => display_size(strlen($audio['audio'])),
            'mime'  => s($audio['mime']),
        ])];
    } catch (\Throwable $e) {
        $results[] = [$ttslabel, false, s($e->getMessage() . (!empty($e->debuginfo) ? ' — ' . $e->debuginfo : ''))];
    }
}

$table = new html_table();
$table->head = [
    get_string('testconnection_check', 'mod_airoleplay'),
    get_string('status'),
    get_string('details'),
];
foreach ($results as [$label, $ok, $details]) {
    $table->data[] = [
        s($label),
        html_writer::span($ok ? get_string('ok') : get_string('error'), 'badge ' . ($ok ? 'bg-success' : 'bg-danger')),
        $details,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->single_button(
    new moodle_url('/mod/airoleplay/testconnection.php', ['run' => 1, 'sesskey' => sesskey()]),
    get_string('testconnection_run', 'mod_airoleplay')
);
echo $OUTPUT->footer();
