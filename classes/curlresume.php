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
 * @package    local_offlineplayer
 * @author     Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*******************************************************************/
// This copies the download_one function from the curl class and adds
// support for resuming the download if one fails mid-way.
/*******************************************************************/

defined('MOODLE_INTERNAL') || die();

class local_offlineplayer_curlresume extends curl {
    public function download_one($url, $params, $options = array()) {
        $options['CURLOPT_HTTPGET'] = 1;
        if (!empty($options['CURLOPT_RANGE'])) { // If this option is set, we are trying to resume a failed download/
            $mode = 'a'; // Add the content of the download to the end of the file.
        } else {
            $mode = 'w'; // Write a new file.
        }
        if (!empty($params)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params, '', '&');
        }
        if (!empty($options['filepath']) && empty($options['file'])) {
            // Open file.
            if (!($options['file'] = fopen($options['filepath'], $mode))) {
                $this->errno = 100;
                return get_string('cannotwritefile', 'error', $options['filepath']);
            }
            $filepath = $options['filepath'];
        }
        unset($options['filepath']);
        $result = $this->request($url, $options);
        if (isset($filepath)) {
            fclose($options['file']);

            // Normally if $result not true, we would delete the file
            // but we don't remove the file on failure as we try to resume failed downloads.
        }
        return $result;
    }
}