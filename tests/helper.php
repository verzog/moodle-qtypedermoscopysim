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
 * Test helper for qtype_dermoscopysim.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Helpers for creating dermoscopysim question instances in PHPUnit tests.
 */
class qtype_dermoscopysim_test_helper extends question_test_helper {
    /**
     * Returns the list of test question variants this helper can make.
     *
     * @return string[]
     */
    public function get_test_questions() {
        return ['lesion_centred', 'lesion_offset'];
    }

    /**
     * Makes a question with the lesion centred at (320, 240) on a 640×480 image.
     *
     * Scale: 0.1 mm/px → lens 20 mm diameter.
     * Lesion: square 40×40 px polygon centred at (320, 240).
     * Ideal margin: square 60×60 px polygon centred at (320, 240).
     *
     * @return qtype_dermoscopysim_question
     */
    public function make_dermoscopysim_question_lesion_centred() {
        question_bank::load_question_definition_classes('dermoscopysim');
        $q = new qtype_dermoscopysim_question();

        // Core question properties.
        test_question_maker::initialise_a_question($q);
        $q->name            = 'Dermoscopy test — centred lesion';
        $q->questiontext    = 'Position the dermoscope over the lesion.';
        $q->generalfeedback = '';
        $q->qtype           = question_bank::get_qtype('dermoscopysim');

        // Image scale: 0.1 mm per pixel.
        $q->mmperpx          = 0.1;
        $q->lensdiametermm   = 20.0;
        $q->magnification    = 10;
        $q->capturetolerancemm = 2.0;
        $q->captureweight    = 30;

        // Margin grading — distance method.
        $q->marginmethod     = 'distance';
        $q->marginmm         = 2.0;
        $q->marginmaxmm      = 4.0;
        $q->idealtolerancemm = 1.0;

        // Lesion polygon: 40×40 px square centred at (320, 240).
        $q->lesiondata = json_encode([
            [300, 220], [340, 220], [340, 260], [300, 260],
        ]);

        // Ideal margin polygon: 60×60 px square centred at (320, 240).
        $q->idealmargindata = json_encode([
            [290, 210], [350, 210], [350, 270], [290, 270],
        ]);

        return $q;
    }

    /**
     * Makes a question with the lesion offset to (400, 300) — for partial-score tests.
     *
     * @return qtype_dermoscopysim_question
     */
    public function make_dermoscopysim_question_lesion_offset() {
        $q = $this->make_dermoscopysim_question_lesion_centred();
        $q->name       = 'Dermoscopy test — offset lesion';

        // Move lesion 80 px right and 60 px down.
        $q->lesiondata = json_encode([
            [380, 280], [420, 280], [420, 320], [380, 320],
        ]);
        $q->idealmargindata = json_encode([
            [370, 270], [430, 270], [430, 330], [370, 330],
        ]);

        return $q;
    }
}
