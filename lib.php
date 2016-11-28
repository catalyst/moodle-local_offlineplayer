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

// Library of functions used by the offline player sync.

function force_flush_buffers() {
    @ob_end_flush();
    @ob_flush();
    @flush();
    @ob_start();
}

// Function used by curl file download.
function update_offline_progress($downloadexpected, $downloaded, $upload_size, $uploaded) {
    global $pbar; // Not a great way to manage this but simplifies code.
    if($downloadexpected > 0 && !empty($downloaded)) {
        $pbar->update($downloaded, $downloadexpected, get_string('downloadingcourse', 'local_offlineplayer'));
        force_flush_buffers();
    }
}

// Function used by curl file upload.
function update_sync_progress($downloadexpected, $downloaded, $upload_size, $uploaded) {
    global $pbar2; // Not a great way to manage this but simplifies code.
    if($upload_size > 0 && !empty($uploaded)) {
        $a = format_string(get_config('local_offlineplayer', 'mothershipname'));
        $pbar2->update($uploaded, $upload_size, get_string('uploadingdata', 'local_offlineplayer', $a));
        force_flush_buffers();
    }
}

// Function to check parameters passed in url are valid.
function local_offline_check_hash() {
    $uid = optional_param('uid', 0, PARAM_INT);
    $uemail = optional_param('uemail', '', PARAM_TEXT);
    $uhash = optional_param('uhash', '', PARAM_ALPHANUMEXT);
    $usersalt = get_config('local_offlineplayer', 'usersalt');
    // prevent uid for guest/admin users.
    if ($uid > 2 && md5($uid.rawurldecode($uemail).$usersalt) == $uhash) {
        return true;
    } else {
        return false;
    }
}

// Function to calculate disk usage by courses.
// TODO: this could be optimised to cache the data in a table.
function offline_activity_get_course_sizes() {
    global $DB;

    // Copied from coursesize report from Peter Bulmer.

    // Generate a full list of context sitedata usage stats
    $subsql = 'SELECT f.contextid, sum(f.filesize) as filessize' .
        ' FROM {files} f';
    $wherebackup = ' WHERE component like \'backup\'';
    $groupby = ' GROUP BY f.contextid';
    $sizesql = 'SELECT cx.id, cx.contextlevel, cx.instanceid, cx.path, cx.depth, size.filessize, backupsize.filessize as backupsize' .
        ' FROM {context} cx ' .
        ' INNER JOIN ( ' . $subsql . $groupby . ' ) size on cx.id=size.contextid' .
        ' LEFT JOIN ( ' . $subsql . $wherebackup . $groupby . ' ) backupsize on cx.id=backupsize.contextid' .
        ' ORDER by cx.depth ASC, cx.path ASC';
    $cxsizes = $DB->get_records_sql($sizesql);
    $coursesizes = array(); // To track a mapping of courseid to filessize
    $coursebackupsizes = array(); // To track a mapping of courseid to backup filessize
    $usersizes = array(); // To track a mapping of users to filesize
    $systemsize = $systembackupsize = 0;
    $coursesql = 'SELECT cx.id, c.id as courseid ' .
        'FROM {course} c ' .
        ' INNER JOIN {context} cx ON cx.instanceid=c.id AND cx.contextlevel = ' . CONTEXT_COURSE;
    $courselookup = $DB->get_records_sql($coursesql);
    foreach($cxsizes as $cxid => $cxdata) {
        $contextlevel = $cxdata->contextlevel;
        $instanceid = $cxdata->instanceid;
        $contextsize = $cxdata->filessize;
        $contextbackupsize = (empty($cxdata->backupsize) ? 0 : $cxdata->backupsize);
        if ($contextlevel == CONTEXT_USER) {
            $usersizes[$instanceid] = $contextsize;
            $userbackupsizes[$instanceid] = $contextbackupsize;
            continue;
        }
        if ($contextlevel == CONTEXT_COURSE) {
            $coursesizes[$instanceid] = $contextsize;
            $coursebackupsizes[$instanceid] = $contextbackupsize;
            continue;
        }
        if (($contextlevel == CONTEXT_SYSTEM) || ($contextlevel == CONTEXT_COURSECAT)) {
            $systemsize = $contextsize;
            $systembackupsize = $contextbackupsize;
            continue;
        }
        // Not a course, user, system, category, see it it's something that should be listed under a course:
        // Modules & Blocks mostly:
        $path = explode('/', $cxdata->path);
        array_shift($path); // get rid of the leading (empty) array item
        array_pop($path); // Trim the contextid of the current context itself

        $success = false; // Course not yet found.
        // Look up through the parent contexts of this item until a course is found:
        while(count($path)) {
            $contextid = array_pop($path);
            if (isset($courselookup[$contextid])) {
                $success = true; //Course found
                // record the files for the current context against the course
                $courseid = $courselookup[$contextid]->courseid;
                if (!empty($coursesizes[$courseid])) {
                    $coursesizes[$courseid] += $contextsize;
                    $coursebackupsizes[$courseid] += $contextbackupsize;
                } else {
                    $coursesizes[$courseid] = $contextsize;
                    $coursebackupsizes[$courseid] = $contextbackupsize;
                }
                break;
            }
        }
        if (!$success) {
            // Didn't find a course
            // A module or block not under a course?
            $systemsize += $contextsize;
            $systembackupsize += $contextbackupsize;
        }
    }
    return $coursesizes;
}

// Function to check if courses have data that can be synced to the mothersip
function offlineplayer_checkforsyncdata() {
    global $DB, $USER;
    $offlineconfig = get_config('local_offlineplayer');

    $hasdatatosync = false;
    $coursestosync = array();
// Check for SCORMS.
    $sql = "SELECT s.id as scormid, c.id as courseid, c.fullname as coursename, c.shortname as shortname,
                   s.name as scormname, c.idnumber as idnumber, cm.id as cmid, MAX(t.timemodified) as lasttime
          FROM {scorm} s
          JOIN {scorm_scoes_track} t ON t.scormid = s.id
          JOIN {course} c ON c.id = s.course
          JOIN {course_modules} cm ON cm.instance = s.id AND cm.course = c.id
          JOIN {modules} m ON m.id = cm.module
         WHERE t.userid = ? AND m.name = 'scorm'
         GROUP BY s.id, c.id, c.fullname, c.shortname, s.name, c.idnumber, cm.id";
    $scorms = $DB->get_recordset_sql($sql, array($USER->id));
    if ($scorms->valid()) {
        // There is some data we need to sync.
        // Get all SCORMS with data to sync.
        foreach ($scorms as $scorm) {
            // Check that timemodified is after the lastsync time.
            $lastsync = isset($offlineconfig->{'lastsynccm_'.$scorm->cmid}) ? $offlineconfig->{'lastsynccm_'.$scorm->cmid} : 0;
            if ($scorm->lasttime > $lastsync) {
                $coursestosync = checkcourseobject($coursestosync, $scorm);
                $coursestosync[$scorm->courseid]->scorms[$scorm->scormid] = $scorm->scormname;
                $coursestosync[$scorm->courseid]->cmstoset[$scorm->cmid] = 'scorm_' . $scorm->cmid . '_userinfo';
				$coursestosync[$scorm->courseid]->idnumber = $scorm->idnumber;
            }
        }
    }
    $scorms->close();

    // Check for feedback content
    $sql = "SELECT f.id as feedbackid, c.id as courseid, c.fullname as coursename, c.shortname as shortname,
                   f.name as feedbackname, c.idnumber as idnumber, cm.id as cmid, fc.timemodified as lasttime
          FROM {feedback} f
          JOIN {feedback_completed} fc ON fc.feedback = f.id
          JOIN {course} c ON c.id = f.course
          JOIN {course_modules} cm ON cm.instance = f.id AND cm.course = c.id
          JOIN {modules} m ON m.id = cm.module
         WHERE fc.userid = ? AND m.name='feedback'";
    $feedbacks = $DB->get_recordset_sql($sql, array($USER->id));
    if ($feedbacks->valid()) {
        // There is some feedback data to sync.
        foreach ($feedbacks as $feedback) {
            // Check that timemodified is after the lastsync time.
            $lastsync = isset($offlineconfig->{'lastsynccm_'.$feedback->cmid}) ? $offlineconfig->{'lastsynccm_'.$feedback->cmid} : 0;
            if ($feedback->lasttime > $lastsync) {
                $coursestosync = checkcourseobject($coursestosync, $feedback);
                $coursestosync[$feedback->courseid]->feedbacks[$feedback->feedbackid] = $feedback->feedbackname;
                $coursestosync[$feedback->courseid]->cmstoset[$feedback->cmid] = 'feedback_' . $feedback->cmid . '_userinfo';
				$coursestosync[$feedback->courseid]->idnumber = $feedback->idnumber;
            }
        }
    }

    // Check for certificates
    $sql = "SELECT cf.id as certificateid, c.id as courseid, c.fullname as coursename, c.shortname as shortname,
                   cf.name as certificatename, c.idnumber as idnumber, cm.id as cmid, ci.timecreated as lasttime
          FROM {certificate} cf
          JOIN {certificate_issues} ci ON ci.certificateid = cf.id
          JOIN {course} c ON c.id = cf.course
          JOIN {course_modules} cm ON cm.instance = cf.id AND cm.course = c.id
          JOIN {modules} m ON m.id = cm.module
         WHERE ci.userid = ? AND m.name='certificate'";
    $certificates = $DB->get_recordset_sql($sql, array($USER->id));
    if ($certificates->valid()) {
        // There is some feedback data to sync.
        foreach ($certificates as $certificate) {
            // Check that timemodified is after the lastsync time.
            $lastsync = isset($offlineconfig->{'lastsynccm_'.$certificate->cmid}) ? $offlineconfig->{'lastsynccm_'.$certificate->cmid} : 0;
            if ($certificate->lasttime > $lastsync) {
                $coursestosync = checkcourseobject($coursestosync, $certificate);
                $coursestosync[$certificate->courseid]->certificates[$certificate->certificateid] = $certificate->certificatename;
                $coursestosync[$certificate->courseid]->cmstoset[$certificate->cmid] = 'certificate_' . $certificate->cmid . '_userinfo';
				$coursestosync[$certificate->courseid]->idnumber = $certificate->idnumber;
            }
        }
    }


    // TODO: Check for other items that can be syncned.
    return $coursestosync;
}


// Helper function to instantiate data object.
function checkcourseobject($currentobject, $data) {
    if (!isset($currentobject[$data->courseid])) {
        $currentobject[$data->courseid] = new stdClass();
        $currentobject[$data->courseid]->scorms = array();
        $currentobject[$data->courseid]->feedbacks = array();
        $currentobject[$data->courseid]->certificates = array();
        $currentobject[$data->courseid]->name = $data->coursename;
        $currentobject[$data->courseid]->shortname = $data->shortname;
        $currentobject[$data->courseid]->cmstoset = array();
    }
    return $currentobject;
}

// Gets a list of the sync dates for the course.
function local_offlineplayer_getsyncdates($courseid) {
    // This is a bit hacky... we should probably create a new table for the offline player to track this stuff.
    global $DB;
    $conf = get_config('local_offlineplayer');
    $lastsync = array();
    // Get all cm's for this course.
    $cms = $DB->get_records('course_modules', array('course' => $courseid));
    foreach ($cms as $cm) {
        if (isset($conf->{'lastsynccm_'.$cm->id})) {
            $lastsync[$cm->id] = $conf->{'lastsynccm_'.$cm->id};
        }
    }
    return $lastsync;
}

// Function to add label to course when a forum is hidden.
function offline_add_forum_missing_label($forumcm) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/label/lib.php');

    $module = $DB->get_record('modules', array('name' => 'label'), '*', MUST_EXIST);

    //forumcm contains the section->id but to use core functions we need the relative section instead
    $section = $DB->get_record('course_sections', array('course' => $forumcm->course, 'id' => $forumcm->section));

    $mothershipname = format_string(get_config('local_offlineplayer', 'mothershipname'));

    // First add course_module record because we need the context.
    $newcm = new stdClass();
    $newcm->course = $forumcm->course;
    $newcm->module = $module->id;
    $newcm->instance = 0; // Not known yet, will be updated later (this is similar to restore code).
    $newcm->visible = 1;
    $newcm->visibleold = 1;
    $newcm->groupmode  = 0;
    $newcm->groupingid = 0;
    $newcm->showdescription = 0;
    $cmid = add_course_module($newcm);

    $data = new stdClass();
    $data->course = $forumcm->course;
    $data->intro = get_string('forummissingmessage', 'local_offlineplayer', $mothershipname);
    $data->introformat = 1;

    $label = label_add_instance($data);
    $DB->set_field('course_modules', 'instance', $label, array('id' => $cmid));
    course_add_cm_to_section($forumcm->course, $cmid, $section->section);

    rebuild_course_cache($forumcm->course, true);
}

// Function to check if offline player is configured.
function local_offline_requires_setup() {
    global $CFG;
    $mothership = get_config('local_offlineplayer', 'mothership');
    if (empty($mothership)) {
        redirect($CFG->wwwroot, get_string('offlineplayernotlinked', 'local_offlineplayer'));
    }
}

// Function used to check if the offline player is handling login/No password is required for user logins.
function local_offline_requires_usermanagement() {
    global $CFG;
    if (!empty($CFG->alternateloginurl) && $CFG->alternateloginurl != $CFG->wwwroot .'/local/offlineplayer/login.php') {
        redirect($CFG->wwwroot, get_string('offlineplayernotmanaginglogin', 'local_offlineplayer'));
    }
}