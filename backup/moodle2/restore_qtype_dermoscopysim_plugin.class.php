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
 * Restore support for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_extrafields_plugin.class.php');

/**
 * Provides restore of dermoscopy simulator question options.
 *
 * All option persistence uses the extra-question-fields convention, so
 * the extrafields base class handles the structure automatically.
 *
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class restore_qtype_dermoscopysim_plugin extends restore_qtype_extrafields_plugin {

    /**
     * Restore the dermoscopy simulator options for one question.
     *
     * The parent class defines the restore path element (named after the
     * question type) but leaves the matching process_<qtype>() handler to the
     * plugin. Delegating to really_process_extra_question_fields() restores
     * every column declared in extra_question_fields().
     *
     * @param array $data the parsed options data for one question
     * @return void
     */
    public function process_dermoscopysim($data) {
        $this->really_process_extra_question_fields($data);
    }
}
