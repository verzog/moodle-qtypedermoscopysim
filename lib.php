<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Serves files for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Check access and serve question files through the question subsystem.
 *
 * @param stdClass $course the course record
 * @param stdClass $cm the course module record
 * @param context $context the context the file belongs to
 * @param string $filearea the file area being requested
 * @param array $args remaining pluginfile path arguments
 * @param bool $forcedownload whether a download is being forced
 * @param array $options additional file serving options
 * @return void
 */
function qtype_dermoscopysim_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile(
        $course,
        $context,
        'qtype_dermoscopysim',
        $filearea,
        $args,
        $forcedownload,
        $options
    );
}
