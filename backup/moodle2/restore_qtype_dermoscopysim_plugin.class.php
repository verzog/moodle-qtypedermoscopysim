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
 * Restore support for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_extrafields_plugin.class.php');

/**
 * Provides restore of dermoscopy simulator question options.
 *
 * All option persistence uses the extra-question-fields convention, so
 * the extrafields base class handles the structure automatically.
 *
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
