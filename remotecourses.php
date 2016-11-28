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

// File that lists the remote courses available for download.
require_once('../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');

local_offline_requires_setup(); // Make sure mothership is configured.

// Prevent caching of this page to stop confusion when new enrolments occur.
$PAGE->set_cacheable(false);

require_login();
$PAGE->set_url('/local/offlineplayer/remotecourses.php');
$PAGE->set_course($SITE);
$token = required_param('token', PARAM_ALPHANUM);
$offlinecfg = get_config('local_offlineplayer');
$mothershipname = format_string($offlinecfg->mothershipname);

// Add timestamp to end of url to prevent caching.
$url = $offlinecfg->mothership.'/local/offline/getcourses.php?token='.$token.'&release='.$offlinecfg->version.'&timestamp='.time();
$courselist = download_file_content($url);
$object = json_decode($courselist);
// Get list of local courses.
$localcourses = $DB->get_records('course', array(), '', 'shortname');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('remotecourses', 'local_offlineplayer'));

if (empty($object->courses)) {
    echo $OUTPUT->notification(get_string('mustenrolonmothership', 'local_offlineplayer', $mothershipname));
} else {
    foreach ($object->courses as $course) {
        echo $OUTPUT->box_start('generalbox info');

        echo html_writer::start_tag('div', array(
            'class' => 'remotecourse',
            'data-courseid' => $course->id,
        ));

        echo html_writer::start_tag('div', array('class' => 'info'));

        // Course name.
        $coursename = format_string($course->fullname, true);
        $courseshortname = $USER->id.'_'.$course->shortname; // Courseshortname is prefixed with userid.
        echo  html_writer::tag('h4', $coursename, array('class' => 'coursename'));
        // If we display course in collapsed form but the course has summary or course contacts, display the link to the info page.
        $downloadlink = $offlinecfg->mothership.'/local/offline/checklogin.php?token='.$token.'&action=downloadcourse&param='.$course->id.'&release='.$offlinecfg->version;
        if (isset($localcourses[$courseshortname])) {
            $hlink = html_writer::tag('a', get_string('updatecourse', 'local_offlineplayer'), array('href' => $downloadlink, 'class' => 'downloadlink'));
        } else {
            $hlink = html_writer::tag('a', get_string('download'), array('href' => $downloadlink, 'class' => 'downloadlink'));
        }

        echo html_writer::span($hlink, 'downloadbutton');

        echo html_writer::start_tag('div', array('class' => 'moreinfo'));
        if (!empty($course->summary)) {
            echo format_string($course->summary, true);
        }
        echo html_writer::end_tag('div'); // End moreinfo.
        echo  html_writer::end_tag('div'); // End info.
        echo html_writer::end_tag('div'); // End coursebox.
        echo $OUTPUT->box_end();
    }
}

if (!empty($object->courses)) {
    echo html_writer::tag('div', get_string('toaddmorecourses', 'local_offlineplayer', $mothershipname));
}

echo $OUTPUT->footer();

