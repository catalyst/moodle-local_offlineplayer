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

// Script to log user in via mothership if required.

require_once('../../config.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');
require_once($CFG->dirroot .'/user/lib.php');

$uid = optional_param('uid', 0, PARAM_INT);
$fname = optional_param('uname', '', PARAM_TEXT);
$lname = optional_param('lname', '', PARAM_TEXT);
$uemail = optional_param('uemail', '', PARAM_TEXT);
$uhash = optional_param('uhash', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_ALPHANUMEXT);
$logmein = optional_param('logmein', 0, PARAM_INT);

local_offline_requires_setup(); // Make sure mothership is configured.
local_offline_requires_usermanagement(); // Only allow this page to be used if altlogin set to use offlineplayer.
$offlinecfg = get_config('local_offlineplayer');
$mothershipname = format_string($offlinecfg->mothershipname);

// If this is a login request log the user in.
if (!empty($logmein) && $logmein > 2) {
    // Prevent ability to login as admin.
    $user = get_complete_user_data('id', $logmein);
    complete_user_login($user);
    redirect($CFG->wwwroot);
}

$context = context_system::instance();
$PAGE->set_url("$CFG->httpswwwroot/local/offlineplayer/login.php");
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');

if (!empty($uid) and $uid < 3) {
    print_error('cannotuseadmin', 'local_offlineplayer');
}

if (!empty($uemail) && !empty($token) && local_offline_check_hash()) {
    // Check for existing user.
    $user = $DB->get_record('user', array('idnumber' => $uid));
    if (empty($user)) {
        // create a new user.
        $newuser = new stdClass();
        $newuser->idnumber = $uid; // Use the id used on mothership to match this account.
        $newuser->firstname = $fname;
        $newuser->lastname = $lname;
        $newuser->email = $uemail;
        $newuser->username = str_replace('+', '', rawurldecode($uemail));
        $newuser->auth = 'manual';
        $userid = user_create_user($newuser);
        // now log this user in automatically.
        $user = get_complete_user_data('id', $userid);
        complete_user_login($user);
        redirect($CFG->wwwroot);
    } else {
        // this user exists.
        $user = get_complete_user_data('id', $user->id);
        complete_user_login($user);
        redirect($CFG->wwwroot);
    }
}

// get current users (exclude guest/admin accounts)
$users = $DB->get_records_select('user', 'id > 2 AND deleted = 0');

if (empty($users)) {
    // show link to create user via mothership.
    echo $OUTPUT->header();
    $a = new stdClass();
    $a->quickstart = $CFG->wwwroot.'/local/offlineplayer/Quick_start_guide_offline_player.pdf';
    $a->createaccount = $offlinecfg->mothership. '/local/offline/checklogin.php?action=login&release='.$offlinecfg->version;
    $a->mothershipname =$mothershipname;
    echo $OUTPUT->box(get_string('nolocaluser', 'local_offlineplayer', $a));
    echo $OUTPUT->footer();
    exit;
} else {
    // Show list of users and ability to create another account.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('chooseuser', 'local_offlineplayer'));
    echo "<ul id='userlist'>";
    foreach ($users as $user) {
        echo "<li class='offlineuser clearfix'>";
        echo "<div class='offlineusername'><a href='login.php?logmein=".$user->id."'>".fullname($user)."</a></div>";
        if (!empty($user->currentlogin)) {
            echo "<div class='offlinelastlogin'>".get_string('lastlogin', 'local_offlineplayer', userdate($user->currentlogin))."</div>";
        }
        // Now show last synchronised date.
        $lastsync = get_config('local_offlineplayer', 'lastsyncuser_'.$user->id, time());
        if (!empty($lastsync)) {
            echo "<div class='offlinelastsync'>".get_string('thiswaslastsync', 'local_offlineplayer', userdate($lastsync))."</div>";
        } else {
            echo "<div class='offlinelastsync'>".get_string('neversync', 'local_offlineplayer', $mothershipname)."</div>";
        }
        echo "<div class='offlinedeleteaccount'><a href='deleteaccount.php?id=".$user->id."'>".get_string('deleteaccount', 'local_offlineplayer')."</a></div>";
        echo "</li>";
    }
    echo "</ul>";
    echo "<div id='newuserlink'>";
    $loginlink = $offlinecfg->mothership. '/local/offline/checklogin.php?action=login&release='.$offlinecfg->version;
    echo html_writer::tag('a', get_string('createnewuser', 'local_offlineplayer'), array('href' => $loginlink, 'class' => 'link-as-button'));
    echo "<span class='description'>(".get_string("requiresconnection", "local_offlineplayer", $mothershipname).")</span>";
    echo "</div>";
    echo $OUTPUT->footer();
}
