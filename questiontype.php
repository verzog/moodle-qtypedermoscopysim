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
 * Question type class for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * The dermoscopy simulator question type.
 *
 * Students position a simulated dermoscope over a clinical photograph,
 * capture a dermoscopic image, and then mark excision margins which are
 * graded automatically against instructor-defined criteria.
 *
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class qtype_dermoscopysim extends question_type {

    /**
     * Name the table and columns that hold this question type's options.
     *
     * Using the extra-question-fields convention gives us automatic
     * database persistence, Moodle XML import/export and most of the
     * backup/restore handling.
     *
     * @return string[] table name followed by column names
     */
    public function extra_question_fields() {
        return [
            'qtype_dermoscopysim',
            'mmperpx',
            'lensdiametermm',
            'magnification',
            'capturetolerancemm',
            'captureweight',
            'marginmethod',
            'marginmm',
            'marginmaxmm',
            'idealtolerancemm',
            'lesiondata',
            'idealmargindata',
        ];
    }

    /**
     * Save the question options, including the uploaded clinical photograph.
     *
     * @param object $question the question data from the editing form
     * @return object|null result of the parent save, if any
     */
    public function save_question_options($question) {
        $result = parent::save_question_options($question);

        if (isset($question->baseimage)) {
            file_save_draft_area_files(
                $question->baseimage,
                $question->context->id,
                'qtype_dermoscopysim',
                'baseimage',
                (int) $question->id,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
        }

        return $result;
    }

    /**
     * Initialise the question definition instance from the question data.
     *
     * @param question_definition $question the question definition to populate
     * @param object $questiondata the raw question data loaded from the database
     * @return void
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->mmperpx = (float) $questiondata->options->mmperpx;
        $question->lensdiametermm = (float) $questiondata->options->lensdiametermm;
        $question->magnification = (float) $questiondata->options->magnification;
        $question->capturetolerancemm = (float) $questiondata->options->capturetolerancemm;
        $question->captureweight = (int) $questiondata->options->captureweight;
        $question->marginmethod = $questiondata->options->marginmethod;
        $question->marginmm = (float) $questiondata->options->marginmm;
        $question->marginmaxmm = (float) $questiondata->options->marginmaxmm;
        $question->idealtolerancemm = (float) $questiondata->options->idealtolerancemm;
        $question->lesiondata = (string) $questiondata->options->lesiondata;
        $question->idealmargindata = (string) $questiondata->options->idealmargindata;
    }

    /**
     * Move this question type's files when a question is moved between contexts.
     *
     * @param int $questionid the question id
     * @param int $oldcontextid the context the files are moving from
     * @param int $newcontextid the context the files are moving to
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);

        $fs = get_file_storage();
        $fs->move_area_files_to_new_context(
            $oldcontextid,
            $newcontextid,
            'qtype_dermoscopysim',
            'baseimage',
            $questionid
        );
    }

    /**
     * Delete this question type's files when a question is deleted.
     *
     * @param int $questionid the question id
     * @param int $contextid the context the question belongs to
     * @return void
     */
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);

        $fs = get_file_storage();
        $fs->delete_area_files($contextid, 'qtype_dermoscopysim', 'baseimage', $questionid);
    }

    /**
     * Report a random guess score of zero for this question type.
     *
     * @param object $questiondata the question data
     * @return float the expected score from random guessing
     */
    public function get_random_guess_score($questiondata) {
        return 0;
    }
}
