<?php
// Copyright (c) Skin Cancer College Australasia.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Skin Cancer
// College Australasia for use with Moodle. It is NOT free software and is
// NOT released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Skin Cancer College Australasia. The software
// is provided "as is", without warranty of any kind, express or implied.

/**
 * Serves files for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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
