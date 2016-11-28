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
 * Plugin for offline player that allows sync with mothership.
 * install locally.
 *
 * @package    local_offlineplayer
 * @copyright  2015 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Dan Marsden <dan@danmarsden.com>
 */

require_once('../../config.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');

local_offline_requires_setup(); // Make sure mothership is configured.
local_offline_requires_usermanagement(); // Only allow this page to be used if altlogin set to use offlineplayer.

$PAGE->set_url('/local/offlineplayer/deleteaccount.php');
$PAGE->set_course($SITE);

$userid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($userid < 3) {
    print_error('invalidaccount', 'local_offlineplayer');
}

$user = $DB->get_record('user', array('id' => $userid));
if (empty($user)) {
    print_error('invalidaccount', 'local_offlineplayer');
}
$courses = enrol_get_users_courses($user->id);

$confirmurl = new moodle_url($PAGE->url, array('id' => $userid, 'confirm' => 1));

if (!$confirm) {
    echo $OUTPUT->header();
    // Get courses this user is associated with that will be deleted.

    if (!empty($courses)) {
        echo $OUTPUT->heading(get_string('thefollowingcourseswillberemoved', 'local_offlineplayer'));
        echo "<ul class='offlinecoursestoremove'>";
        foreach ($courses as $course) {
            echo "<li>".$course->fullname."</li>";
        }
        echo "</ul>";
    }
    echo $OUTPUT->confirm(get_string('confirmdeleteuser', 'local_offlineplayer', fullname($user)), $confirmurl, $CFG->wwwroot);
    echo $OUTPUT->footer();
} else if (data_submitted() && confirm_sesskey()) {
    foreach ($courses as $course) {
        delete_course($course, false);
    }
    fix_course_sortorder();
    delete_user($user);
    redirect($CFG->wwwroot, get_string('userdeleted', 'local_offlineplayer'), 2);
}
