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
 * Settings used by local_offlineplayer
 *
 * @package    local_offlineplayer
 * @copyright  2014 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Dan Marsden <dan@danmarsden.com>
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_offlineplayer', 'Offline Player');
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_offlineplayer/mothershipname',
        get_string('mothershipname', 'local_offlineplayer'),
        get_string('mothershipname_desc', 'local_offlineplayer'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_offlineplayer/mothership',
        get_string('mothershipurl', 'local_offlineplayer'),
        get_string('mothershipurl_desc', 'local_offlineplayer'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_offlineplayer/usersalt',
        get_string('usersalt', 'local_offlineplayer'), get_string('usersalt_desc', 'local_offlineplayer'), '', PARAM_TEXT));
}
