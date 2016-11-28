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

// Script to download selected course from mothership.

require_once('../../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');
require_login();

local_offline_requires_setup(); // Make sure mothership is configured.

@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering ', '0');
@ini_set('implicit_flush', '1');
ob_implicit_flush(true);

$offlinecfg = get_config('local_offlineplayer');

$PAGE->set_url('/local/offlineplayer/downloadcourse.php');
$PAGE->set_course($SITE);

$token = required_param('token', PARAM_ALPHANUM);
$courseid = required_param('param', PARAM_INT);


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pleasewait', 'local_offlineplayer'));

$pbar = new progress_bar('coursedownload', 500, true);
$pbar->update(0, 100, get_string('downloadingcourse', 'local_offlineplayer')); // Set progress bar to 0 to begin.

force_flush_buffers();
$downloadurl = $offlinecfg->mothership.'/local/offline/getcourses.php?token='.$token.'&release='.$offlinecfg->version;
$courselist = download_file_content($downloadurl);
$object = json_decode($courselist);
if (empty($object->courses->$courseid)) {
    redirect($CFG->wwwroot, get_string('coursenotavailable', 'local_offlineplayer'), 2);
    exit;
}


$url = $offlinecfg->mothership.'/local/offline/getcourse.php?token='.$token.'&course='.$courseid.'&release='.$offlinecfg->version;
$curl = new local_offlineplayer_curlresume();
$tofile = "$CFG->tempdir/offline";
check_dir_exists($tofile);
$tofile .= '/'.$courseid.'.mbz'; // Name of file after succesful download.
$tmpfile = $tofile . '.tmp'; // name of tmp file to use while downloading.

@unlink($tofile); // Delete old file if one exists.

$curlopt = array('filepath' => $tmpfile,
                 'CURLOPT_PROGRESSFUNCTION' => 'update_offline_progress',
                 'CURLOPT_NOPROGRESS' => false,
                 'CURLOPT_CONNECTTIMEOUT' => 70,
                 'CURLOPT_LOW_SPEED_TIME' => 60, // Check speed every 10 seconds
                 'CURLOPT_LOW_SPEED_LIMIT' => 500 // If average download speed is less than 500 bytes/second it's probably too slow.
);

// Check if partially downloaded file exists and try to resume if possible.
if (file_exists($tmpfile)) {
    $fromsize = filesize($tmpfile);
    if (!empty($fromsize)) {
        $curlopt['CURLOPT_RANGE'] = "$fromsize" . "-";
    } else {
        @unlink($tmpfile);
    }
}

set_time_limit(60 * 30); // Set high timelimit for download.

$result = $curl->download_one($url, array(), $curlopt);
if ($result == '1') {
    // Rename file as we have completed the download.
    rename($tmpfile, $tofile);
} else {
    // This is a failed download - show a message and ask the user to try again.
    $filesize = filesize($tmpfile);
    if (!empty($filesize)) {
        echo $OUTPUT->notification(get_string('downloadfailedresume', 'local_offlineplayer', display_size($filesize)).
                                   ' <br/><br/>'. $result);
        echo '<div id="morecoures">
              <a href="'. $CFG->wwwroot.'/local/offlineplayer/checkinternet.php?action=remotecourses">'.
              get_string('downloadmoreactivities', 'local_offlineplayer').'</a></div>';
    } else {
        echo $OUTPUT->notification(get_string('downloadfailed', 'local_offlineplayer')).' <br/><br/>'. $result;
        '<div id="morecoures">
         <a href="'. $CFG->wwwroot.'/local/offlineplayer/checkinternet.php?action=remotecourses">'.
        get_string('downloadmoreactivities', 'local_offlineplayer').'</a></div>';
    }
    $pbar->update_full(0, get_string('downloadingcourse', 'local_offlineplayer'));
    exit;
}

$pbar->update_full(100, get_string('downloadingcourse', 'local_offlineplayer'));

if ($result != true) {
    redirect($CFG->wwwroot, $result, 2);
    exit;
}
// Sanity check to make sure we have a real backup file.
try {
    $bcinfo = backup_general_helper::get_backup_information_from_mbz($tofile);
} catch (backup_helper_exception $e) {
    // File is not a valid backup.
    @unlink($tofile);
    redirect($CFG->wwwroot, get_string('coursenotvalid', 'local_offlineplayer'), 4);
    exit;
}
echo $OUTPUT->heading(get_string('importingcourse', 'local_offlineplayer'));
echo $OUTPUT->pix_icon('i/loading', get_string('importingcourse', 'local_offlineplayer'), 'moodle', array('class' => 'loadingicon'));

force_flush_buffers();

// Prefix courseshortname with userid so that multi-users have their own version of each course.
$courseshortname = $USER->id.'_'.$object->courses->$courseid->shortname;
$coursefullname = $object->courses->$courseid->fullname;

$course = $DB->get_record('course', array('shortname' => $courseshortname));
// Get Admin user to use during restore.
$adminuser = get_admin();
if (!empty($course)) {
    // We need to update an existing course.
    $backupmode = backup::TARGET_EXISTING_DELETING;
} else {
    $course = new stdClass();
    $course->fullname = $coursefullname;
    $course->shortname = $courseshortname;
    $course->category = 1; // Miscellaneous.
    $course->enablecompletion = 0; // Course completion on.
    $course = create_course($course);
    $backupmode = backup::TARGET_NEW_COURSE;
}

$tmpdir = $CFG->tempdir . '/backup';
$filename = restore_controller::get_tempdir_name($course->id, $adminuser->id);
$pathname = $tmpdir . '/' . $filename;
$packer = get_file_packer('application/zip');
$packer->extract_to_pathname($tofile, $pathname);

// Restore the backup immediately.
$rc = new restore_controller($filename, $course->id,
    backup::INTERACTIVE_NO, backup::MODE_IMPORT, $adminuser->id, $backupmode);
// don't restore user data in this backup if it contains any.
$rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
$rc->get_plan()->get_setting('users')->set_value(false);

// Check if the format conversion must happen first.
if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
    $rc->convert();
}
if (!$rc->execute_precheck()) {
    $precheckresults = $rc->get_precheck_results();
    if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($pathname);
        }
        $renderer = $PAGE->get_renderer('core', 'backup');
        echo $renderer->precheck_notices($precheckresults);
        die();
    }
}
if ($backupmode == backup::TARGET_EXISTING_DELETING) {
    restore_dbops::delete_course_content($course->id, array('keep_roles_and_enrolments' => true));
}
$rc->execute_plan();
$rc->destroy();
if (empty($CFG->keeptempdirectoriesonbackup)) {
    fulldelete($pathname);
}
@unlink($tofile); // Remove downloaded backup file.

// Restore process uses shortname used in backup file, reset to use shortname so that it doesn't conflict with multi-users.
$course = $DB->get_record('course', array('id' => $course->id));
$course->shortname = $courseshortname;
$course->fullname = $coursefullname;
$course->timecreated = time(); // Set timecreated as now as this is when the course was created in the player.
$course->idnumber = $courseid;
$DB->update_record('course', $course);

// Now enrol this user as a student in the course.
$studentrole = $DB->get_record('role', array('shortname' => 'student'));
$manual = enrol_get_plugin('manual');
$maninstance1 = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'), '*');
if (empty($maninstance1)) {
    // Add new manual enrolment method to this course.
    $fields = array(
        'status'          => ENROL_INSTANCE_ENABLED,
        'roleid'          => $studentrole->id,
        'enrolperiod'     => 0,
        'expirynotify'    => 0,
        'notifyall'       => 0,
        'expirythreshold' => 86400,
    );
    $maninstance1 = $manual->add_instance($course, $fields);
}

$manual->enrol_user($maninstance1, $USER->id, $studentrole->id);

// Now find all Forums in this course and hide them as the offline player doesn't support them.
$modinfo = get_fast_modinfo($course, $USER->id);
if (isset($modinfo->instances['forum'])) {
    foreach ($modinfo->instances['forum'] as $cm) {
        if ($cm->uservisible) {
            // Add label to this location to mention that a forum is missing.
            offline_add_forum_missing_label($cm);
        }
        set_coursemodule_visible($cm->id, 0);
    }
}

// Now find all SCORMS and set the displayactivity name to 0 - display activity name is a new setting in 2.8 and
// the restore file comes from an older site.
$scorms = $DB->get_records('scorm', array('course' => $course->id));
foreach ($scorms as $scorm) {
    if (!empty($scorm->displayactivityname)) {
        $scorm->displayactivityname = 0; // Disable this setting.
        $DB->update_record('scorm', $scorm);
    }

}

redirect($CFG->wwwroot);
