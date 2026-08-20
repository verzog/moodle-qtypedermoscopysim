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
 * Test helper for qtype_dermoscopysim.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for creating dermoscopysim question instances in PHPUnit tests.
 */
class qtype_dermoscopysim_test_helper extends question_test_helper
{
    /**
     * Returns the list of test question variants this helper can make.
     *
     * @return string[]
     */
    public function get_test_questions()
    {
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
    public function make_dermoscopysim_question_lesion_centred()
    {
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
    public function make_dermoscopysim_question_lesion_offset()
    {
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
