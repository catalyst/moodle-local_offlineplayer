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
 * local plugin offlineplayer upgrade
 *
 * @package    local_offlineplayer
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_offlineplayer_upgrade($oldversion) {
    if ($oldversion < 2015031900) {

        set_config('release', '2', 'local_offlineplayer');

        // Assign savepoint reached.
        upgrade_plugin_savepoint(true, 2015031900, 'local', 'offlineplayer');
    }
    return true;
}
