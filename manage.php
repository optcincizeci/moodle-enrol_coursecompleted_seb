<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Adds new or edit instance of enrol_coursecompleted to specified course
 *
 * @package   enrol_coursecompleted
 * @copyright 2017 eWallah (www.eWallah.net)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot . '/enrol/coursecompleted/lib.php');
global $DB, $OUTPUT, $PAGE;

$enrolid = required_param('enrolid', PARAM_INT);
$action = optional_param('action', '', PARAM_RAW);

if ($instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'coursecompleted'], '*', MUST_EXIST)) {
    $course = get_course($instance->courseid);
    $context = \context_course::instance($course->id, MUST_EXIST);
}
$canenrol = has_capability('enrol/coursecompleted:enrolpast', $context);
$canunenrol = has_capability('enrol/coursecompleted:unenrol', $context);

if (!$canenrol && !$canunenrol) {
    // No need to invent new error strings here...
    require_capability('enrol/manual:enrol', $context);
}
require_login($course);

$enrol = enrol_get_plugin('coursecompleted');
$instancename = $enrol->get_instance_name($instance);

$PAGE->set_url('/enrol/coursecompleted/manage.php', ['enrolid' => $instance->id]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($instancename);
$PAGE->set_heading($course->fullname);

$timeformat = get_string('strftimedatetimeshort');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrolusers', 'enrol'));

if ($enrolid > 0) {
    $br = \html_writer::empty_tag('br');
    $condition = 'course = ? AND timecompleted > 0';
    if ($action === 'enrol') {
        require_sesskey();
        if ($candidates = $DB->get_fieldset_select('course_completions', 'userid', $condition, [$instance->customint1])) {
            foreach ($candidates as $candidate) {
                $user = \core_user::get_user($candidate);
                if (!empty($user) && !$user->deleted) {
                    $enrol->enrol_user($instance,
                                       $candidate,
                                       $instance->roleid,
                                       $instance->enrolstartdate,
                                       $instance->enrolenddate);
                    \enrol_coursecompleted_plugin::keepingroup($instance, $candidate);
                    mark_user_dirty($candidate);
                    echo '.';
                }
            }
            echo $br . $br . get_string('usersenrolled', 'enrol_coursecompleted', count($candidates));
            $url = new \moodle_url('/enrol/instances.php', ['id' => $course->id]);
            echo $br . $br . $OUTPUT->continue_button($url);
        }
    } else {
        $cancelurl = new \moodle_url('/enrol/instances.php', ['id' => $instance->courseid]);
        if ($candidates = $DB->get_fieldset_select('course_completions', 'userid', $condition, [$instance->customint1])) {
            $allusers = [];
            foreach ($candidates as $candidate) {
                $user = \core_user::get_user($candidate);
                if (!empty($user) && !$user->deleted) {
                    $userurl = new \moodle_url('/user/view.php', ['course' => 1, 'id' => $candidate]);
                    $allusers[] = \html_writer::link($userurl, fullname($user));
                }
            }
            $link = new \moodle_url($PAGE->url, ['enrolid' => $enrolid, 'action' => 'enrol', 'sesskey' => sesskey()]);
            echo $OUTPUT->confirm(implode(', ', $allusers), new \single_button($link, get_string('manual:enrol', 'enrol_manual')),
                $cancelurl);
        } else {
            echo $OUTPUT->box(get_string('nousersfound')) . $br . $OUTPUT->single_button($cancelurl, get_string('cancel'));
        }
    }
}
echo $OUTPUT->footer();
