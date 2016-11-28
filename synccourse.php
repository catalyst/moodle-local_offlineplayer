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

// File that syncs local course data with the remote site.
require_once('../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');

@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering ', '0');
@ini_set('implicit_flush', '1');
ob_implicit_flush(true);

local_offline_requires_setup(); // Make sure mothership is configured.

require_login();
$token = optional_param('token', '', PARAM_ALPHANUM);
$confirm = optional_param('param', false, PARAM_BOOL);
$mothershipuserid = optional_param('uid', '', PARAM_INT);

$PAGE->set_url('/local/offlineplayer/synccourse.php');
$PAGE->set_course($SITE);

// First lets check to see if there is any data to sync.
$offlinecfg = get_config('local_offlineplayer');
$mothershipname = format_string($offlinecfg->mothershipname);
$coursestosync = offlineplayer_checkforsyncdata();
if (empty($coursestosync)) {
    $a = format_string(get_config('local_offlineplayer', 'mothershipname'));
    redirect($CFG->wwwroot, get_string('synccoursenodata', 'local_offlineplayer', $mothershipname), 3);
}
$numcourses = count($coursestosync);
if ($confirm && !empty($token)) {
    // Sanity check on logged in user.
    if ($USER->idnumber != $mothershipuserid) {
        redirect($CFG->wwwroot, get_string('wrongmothershipuser', 'local_offlineplayer', $mothershipname), 3);
    }
    echo $OUTPUT->header();
    force_flush_buffers();
    // Now send this backup file to the mothership.
    $pbar = new progress_bar('generatingupload', 500, true);
    $pbar->update(0, 100, get_string('generatingupload', 'local_offlineplayer')); // Set progress bar to 0 to begin
    $barcount = 0;
    $barinc = 80/$numcourses;
    $files = array();
    //cheat and use admin user to generate backup.
    $adminid = $DB->get_field('user', 'id', array('username' => 'admin'), MUST_EXIST);
    // Trigger the sync process.
    $lastsync = array();
    foreach ($coursestosync as $courseid => $data) {
        $files[$courseid] = array();
        // $data->idnumber contains the id of the course on the mothership.
        $lastsync[$data->idnumber] = local_offlineplayer_getsyncdates($courseid);
        // Now generate a backup for each course listed.
        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $adminid);

        $backupid       = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);

        // We need to explicitly set each module to include user data.
        foreach ($data->cmstoset as $cmtoset) {
            $bc->get_plan()->get_setting($cmtoset)->set_value(true);
        }
        $bc->execute_plan();

        $bc->destroy();
        $files[$courseid] = $backupbasepath;

        $barcount = $barcount + $barinc;
        $pbar->update($barcount, 100, get_string('generatingupload', 'local_offlineplayer', $mothershipname)); // Set progress bar to 0 to begin
    }

    // Now retrieve all activity xml files and course backup file to package into a single zip to send to mothership
    $zipfiles = array();

    $supportedactivities = array('scorm', 'feedback', 'certificate');

    foreach ($files as $cid => $f) {
        // Use the mothership courseid as the main folder name.
        $archivepath = $coursestosync[$cid]->idnumber;
        $zipfiles[$archivepath.'/moodle_backup.xml'] = $f .'/moodle_backup.xml';

        foreach ($supportedactivities as $activity) {
            // now find all activity xml files.
            $searchpath = $f.'/activities/'.$activity.'_*/'.$activity.'.xml';
            foreach (glob($searchpath) as $found) {
                $basepath = str_replace($f.'/activities', '', $found);
                $zipfiles[$archivepath.$basepath] = $found;
            }

        }
    }
    $tempzip = tempnam($CFG->tempdir.'/', 'offline');
    $zip = new zip_packer();
    $zip->archive_to_pathname($zipfiles, $tempzip);

    // Now delete all old data used to generate the backup:
    foreach ($files as $filepath) {
        fulldelete($filepath);
        fulldelete($filepath.'.log');
    }
    $pbar->update(100, 100, get_string('generatingupload', 'local_offlineplayer', $mothershipname)); // Set progress bar to 0 to begin
    // Now send this backup file to the mothership.
    $a = format_string(get_config('local_offlineplayer', 'mothershipname'));
    $pbar2 = new progress_bar('courseupload', 500, true);
    $pbar2->update(0, 100, get_string('uploadingdata', 'local_offlineplayer', $a)); // Set progress bar to 0 to begin

    force_flush_buffers();

    set_time_limit(60*30); // set high timelimit for upload.
    $curl = new curl();
    $url = $offlinecfg->mothership.'/local/offline/sync.php?token='.$token.'&release='.$offlinecfg->version;
    $params = array('lastsync' => serialize($lastsync),
                    'extra_info' => filesize($tempzip));

    if (function_exists('curl_file_create')) { // PHP 5.5 and higher (PHP 5.6 does not support @filename method.)
        $params['filecontents'] = curl_file_create($tempzip);
    } else {
        $params['filecontents'] = '@'.$tempzip;
    }
    $options = array('CURLOPT_PROGRESSFUNCTION' => 'update_sync_progress',
                     'CURLOPT_NOPROGRESS' => false);
    $json_response = $curl->post($url, $params, $options);
    $response = json_decode($json_response);
    if (!empty($response->errors)) {
        foreach ($response->errors as $eid => $error) {
            echo $OUTPUT->notification("An Error occured during sync: ". $eid .":".$error);
        }

    }
    if (!empty($response->success)) {
        // Save the time that sync occurred so we can display information about it.
        $synctime = time();
        set_config('lastsyncuser_'.$USER->id, $synctime, 'local_offlineplayer');
        foreach ($response->success as $cmid => $name) {
            set_config('lastsynccm_'.$cmid, $synctime, 'local_offlineplayer');
        }
        redirect($CFG->wwwroot, get_string('synccompleted', 'local_offlineplayer'), 5);
    }

    echo $OUTPUT->footer();
    exit;

}

echo $OUTPUT->header();
$a = format_string(get_config('local_offlineplayer', 'mothershipname'));
echo $OUTPUT->heading(get_string('datatosync', 'local_offlineplayer', $a));

echo "<div id='coursestosync'>";
foreach ($coursestosync as $c) {
    echo "<div id='coursetosync'>";
    echo "<h3 class='coursename'>".$c->name."</h3>";
    foreach ($c->scorms as $sid => $sn) {
        echo "<div id='scormname'>";
        echo "<img src=\"" . $OUTPUT->pix_url('icon', 'scorm') . "\" ".
            "class=\"icon\" alt=\"\" />";
        echo $sn."</div>";
    }
    foreach ($c->certificates as $sid => $sn) {
        echo "<div id='certificatename'>";
        echo "<img src=\"" . $OUTPUT->pix_url('icon', 'certificate') . "\" ".
            "class=\"icon\" alt=\"\" />";
        echo $sn."</div>";
    }
    foreach ($c->feedbacks as $sid => $sn) {
        echo "<div id='feedbackname'>";
        echo "<img src=\"" . $OUTPUT->pix_url('icon', 'feedback') . "\" ".
            "class=\"icon\" alt=\"\" />";
        echo $sn."</div>";
    }
    echo "</div>";
}
echo "</div>";
$url = new moodle_url($offlinecfg->mothership.'/local/offline/checklogin.php', array('action' => 'synccourse', 'param' => '1', 'release' => $offlinecfg->version));
$a = format_string(get_config('local_offlineplayer', 'mothershipname'));
echo $OUTPUT->heading(get_string('synccourse_desc', 'local_offlineplayer', $a), 3);
echo $OUTPUT->single_button($url, get_string('sync', 'local_offlineplayer', $a));
echo $OUTPUT->footer();