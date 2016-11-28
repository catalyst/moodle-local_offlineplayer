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
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot .'/local/offlineplayer/lib.php');
$action = optional_param('action', '', PARAM_ALPHA);

local_offline_requires_setup(); // Make sure mothership is configured.

$offlineconfig = get_config('local_offlineplayer');

$actionurl = '';
if ($action == 'remotecourses') {
    $actionurl = $offlineconfig->mothership.'/local/offline/checklogin.php?action=remotecourses&release='.$offlineconfig->version;
} else if ($action == 'sync') {
    $actionurl = $CFG->wwwroot.'/local/offlineplayer/synccourse.php';
} else {
    // Never show a white page - redirect to home if this happens.
    $actionurl = $CFG->wwwroot;
}

$curl = new curl();
$result = $curl->head($offlineconfig->mothership, array());
$info = $curl->get_info();
if ($info['http_code'] >= 200 & $info['http_code'] < 400) {
    // Redirect user automatically this as we have found an internet connection.
    redirect($actionurl);
} else {
    // No internet connection detected - show warning.
    $PAGE->set_url('/local/offlineplayer/checkinternet.php');
    $PAGE->set_course($SITE);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nointernets', 'local_offlineplayer'));
    echo "<span class='tryanyway'><a href='".$actionurl."'>".get_string('tryanyway', 'local_offlineplayer')."</a></span>";
    echo $OUTPUT->footer();
}