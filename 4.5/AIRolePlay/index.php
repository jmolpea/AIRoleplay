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
 * Lists all airoleplay activities in a course.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);
$PAGE->set_pagelayout('incourse');

\mod_airoleplay\event\course_module_instance_list_viewed::create_from_course($course)->trigger();

$PAGE->set_url('/mod/airoleplay/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_airoleplay'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('modulenameplural', 'mod_airoleplay'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_airoleplay'));

$modinfo  = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('airoleplay');

if (empty($instances)) {
    notice(get_string('noinstances', 'mod_airoleplay'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [
    get_string('section'),
    get_string('name'),
];
$table->attributes['class'] = 'generaltable mod_index';

$format = course_get_format($course);
foreach ($instances as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $link = html_writer::link(
        new moodle_url('/mod/airoleplay/view.php', ['id' => $cm->id]),
        format_string($cm->name, true, ['context' => $cm->context]),
        $cm->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [$format->get_section_name($cm->sectionnum), $link];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
