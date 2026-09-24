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
 * Backup and restore tests for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_dermoscopysim;

use question_bank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests that a question's clinical photograph survives backup and restore.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_test extends \advanced_testcase {
    /**
     * The backup plugin declares the base image file area so it is included in
     * backup and restore.
     */
    public function test_get_qtype_fileareas_includes_baseimage(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/moodle2/backup_qtype_plugin.class.php');
        require_once(
            $CFG->dirroot . '/question/type/dermoscopysim/backup/moodle2/backup_qtype_dermoscopysim_plugin.class.php'
        );

        $fileareas = \backup_qtype_dermoscopysim_plugin::get_qtype_fileareas();
        $this->assertArrayHasKey('baseimage', $fileareas);
        $this->assertSame('question_created', $fileareas['baseimage']);
    }

    /**
     * Duplicating a quiz that embeds a dermoscopy question copies the clinical
     * photograph to the new question, and keeps the question options.
     */
    public function test_duplicate_keeps_base_image(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $questiongenerator = $generator->get_plugin_generator('core_question');

        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $quizcontext = \context_module::instance($quiz->cmid);

        $cat = $questiongenerator->create_question_category(['contextid' => $quizcontext->id]);
        $question = $questiongenerator->create_question('dermoscopysim', 'lesion_centred', ['category' => $cat->id]);

        // The freshly created question owns exactly one base image file.
        $this->assertCount(1, $this->base_image_files($question->id));

        $numdermoscopy = $DB->count_records('question', ['qtype' => 'dermoscopysim']);

        // Duplicate the quiz module, which runs a full backup then restore.
        duplicate_module($course, get_fast_modinfo($course)->get_cm($quiz->cmid));

        // A new dermoscopy question now exists.
        $this->assertEquals($numdermoscopy + 1, $DB->count_records('question', ['qtype' => 'dermoscopysim']));
        $newid = (int) $DB->get_field_sql(
            'SELECT MAX(id) FROM {question} WHERE qtype = ?',
            ['dermoscopysim']
        );
        $this->assertNotEquals($question->id, $newid);

        // The restored question carries its own copy of the clinical photograph.
        $restoredfiles = $this->base_image_files($newid);
        $this->assertCount(1, $restoredfiles, 'The base image was not restored with the duplicated question.');
        $restored = reset($restoredfiles);
        $this->assertSame('base.png', $restored->get_filename());
        $this->assertGreaterThan(0, $restored->get_filesize());

        // The options survived too.
        $newdata = question_bank::load_question_data($newid);
        $this->assertEqualsWithDelta(0.1, (float) $newdata->options->mmperpx, 0.0001);
        $this->assertSame('distance', $newdata->options->marginmethod);
    }

    /**
     * Return the stored base image files for a question, excluding directories.
     *
     * @param int $questionid the question id
     * @return \stored_file[] the base image files
     */
    protected function base_image_files(int $questionid): array {
        $qdata = question_bank::load_question_data($questionid);
        $fs = get_file_storage();
        return $fs->get_area_files(
            $qdata->contextid,
            'qtype_dermoscopysim',
            'baseimage',
            $questionid,
            'itemid, filepath, filename',
            false
        );
    }
}
