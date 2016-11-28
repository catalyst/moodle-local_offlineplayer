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

require_login();

$PAGE->set_url('/local/offlineplayer/deletecourse.php');
$PAGE->set_course($SITE);

$courseid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$course = get_course($courseid);

$confirmurl = new moodle_url($PAGE->url, array('id' => $courseid, 'confirm' => 1));

if (!$confirm) {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('confirmdelete', 'local_offlineplayer', $course->fullname), $confirmurl, $CFG->wwwroot);
    echo $OUTPUT->footer();
} else if (data_submitted() && confirm_sesskey()) {
    delete_course($course, false);

    fix_course_sortorder();
    redirect($CFG->wwwroot, get_string('coursedeleted', 'local_offlineplayer'), 2);
}
