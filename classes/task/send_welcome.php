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
 * Process expirations task.
 *
 * @package   enrol_coursecompleted
 * @copyright 2020 eWallah (www.eWallah.net)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_coursecompleted\task;

use stdClass;
use moodle_url;
use html_writer;
use core_user;

defined('MOODLE_INTERNAL') || die();

/**
 * Process expirations task.
 *
 * @package   enrol_coursecompleted
 * @copyright 2020 eWallah (www.eWallah.net)
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_welcome extends \core\task\adhoc_task {

    /**
     * Execute scheduled task
     *
     * @return boolean
     */
    public function execute() {
        global $CFG, $DB;
        $data = $this->get_custom_data();
        if ($user = \core_user::get_user($data->userid)) {
            if ($course = $DB->get_record('course', ['id' => $data->courseid])) {
                $context = \context_course::instance($course->id);
                if ($complcourse = $DB->get_record('course', ['id' => $data->completedid])) {
                    $context2 = \context_course::instance($complcourse->id);
                    $a = new stdClass();
                    $a->coursename = format_string($course->fullname, true, ['context' => $context]);
                    $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id&course=$course->id";
                    $a->completed = format_string($complcourse->fullname, true, ['context' => $context2]);
                    $custom = $DB->get_field('enrol', 'customtext1', ['id' => $data->enrolid]);
                    if (trim($custom) !== '') {
                        $key = ['{$a->coursename}',  '$a->completed', '{$a->profileurl}', '{$a->fullname}', '{$a->email}'];
                        $value = [$a->coursename, $a->completed, $a->profileurl, fullname($user), $user->email];
                        $message = str_replace($key, $value, $custom);
                        if (strpos($message, '<') === false) {
                            // Plain text only.
                            $messagetext = $message;
                            $messagehtml = text_to_html($messagetext, null, false, true);
                        } else {
                            // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                            $messagehtml = format_text($message, FORMAT_MOODLE,
                               ['context' => $context, 'para' => false, 'newlines' => true, 'filter' => true]);
                            $messagetext = html_to_text($messagehtml);
                        }
                    } else {
                        $messagetext = get_string('welcometocourse', 'enrol_coursecompleted', $a);
                        $messagehtml = text_to_html($messagetext, null, false, true);
                    }

                    $subject = get_string('welcometocourse', 'enrol_coursecompleted',
                        format_string($course->fullname, true, ['context' => $context]));
                    // Directly emailing welcome message rather than using messaging.
                    email_to_user($user, core_user::get_noreply_user(), $subject, $messagetext, $messagehtml);
                }
            }
        }
    }
}