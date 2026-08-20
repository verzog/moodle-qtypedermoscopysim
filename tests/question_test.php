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
 * PHPUnit tests for qtype_dermoscopysim grading.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for the dermoscopic simulator question type grading.
 *
 * @covers \qtype_dermoscopysim_question
 */
class qtype_dermoscopysim_question_test extends advanced_testcase
{
    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Returns a centred-lesion question for use in tests.
     *
     * @return qtype_dermoscopysim_question
     */
    protected function get_centred_question()
    {
        return test_question_maker::make_question('dermoscopysim', 'lesion_centred');
    }

    /**
     * Encodes a list of [x, y] pairs as the JSON format written by the student JS.
     *
     * @param array $pts List of [x, y] pairs.
     * @return string
     */
    protected function encode_points(array $pts): string
    {
        return json_encode($pts);
    }

    // -------------------------------------------------------------------
    // Capture grading
    // -------------------------------------------------------------------

    /**
     * A response with the lens exactly on the lesion centroid earns full capture marks.
     */
    public function test_capture_exact_centre_scores_full(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lesion centroid is (320, 240).
        $score = $q->grade_capture(320, 240);
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    /**
     * A response with the lens within the tolerance radius earns full capture marks.
     */
    public function test_capture_within_tolerance_scores_full(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Tolerance is 2.0 mm; scale is 0.1 mm/px → 20 px tolerance radius.
        // Move 15 px right of centroid: 15 × 0.1 = 1.5 mm < 2.0 mm.
        $score = $q->grade_capture(335, 240);
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    /**
     * A response beyond the lens radius earns zero capture marks.
     */
    public function test_capture_outside_lens_scores_zero(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lens diameter is 20 mm → radius 10 mm → 100 px.
        // Move 110 px right: well outside the lens.
        $score = $q->grade_capture(430, 240);
        $this->assertEqualsWithDelta(0.0, $score, 0.001);
    }

    /**
     * A response halfway between tolerance edge and lens edge earns partial capture.
     */
    public function test_capture_mid_range_scores_partial(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Tolerance 2 mm (20 px), lens radius 10 mm (100 px).
        // At 60 px = 6 mm: fraction through falloff = (6 - 2) / (10 - 2) = 0.5.
        // Expected score = 1 - 0.5 = 0.5.
        $score = $q->grade_capture(380, 240);
        $this->assertEqualsWithDelta(0.5, $score, 0.05);
    }

    // -------------------------------------------------------------------
    // Margin grading — distance method
    // -------------------------------------------------------------------

    /**
     * A margin polygon that perfectly surrounds the lesion with exactly the right
     * clearance earns full margin marks (distance method).
     */
    public function test_margin_distance_ideal_clearance_scores_full(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'distance';
        $q->marginmm     = 2.0;
        $q->marginmaxmm  = 4.0;

        // Lesion square: 300–340 × 220–260 (centroid 320, 240, half-diagonal ~28 px).
        // Margin 30 px = 3 mm extra on each side: 270–370 × 190–290.
        $margin = $this->encode_points([
            [270, 190], [370, 190], [370, 290], [270, 290],
        ]);
        $score = $q->grade_margin($margin);
        $this->assertGreaterThan(0.8, $score);
    }

    /**
     * A margin polygon that clips the lesion earns zero margin marks.
     */
    public function test_margin_clips_lesion_scores_zero(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Margin polygon smaller than the lesion polygon.
        $margin = $this->encode_points([
            [310, 230], [330, 230], [330, 250], [310, 250],
        ]);
        $score = $q->grade_margin($margin);
        $this->assertEqualsWithDelta(0.0, $score, 0.05);
    }

    // -------------------------------------------------------------------
    // Combined grading
    // -------------------------------------------------------------------

    /**
     * Perfect capture + perfect margin = overall score of 1.0.
     */
    public function test_grade_response_perfect_scores_one(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $margin = $this->encode_points([
            [270, 190], [370, 190], [370, 290], [270, 290],
        ]);
        $response = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $margin,
        ];
        [$fraction, $state] = $q->grade_response($response);
        unset($state);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.05);
    }

    /**
     * Poor capture + poor margin = overall score near 0.
     */
    public function test_grade_response_all_wrong_scores_near_zero(): void
    {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lens far from lesion.
        $margin = $this->encode_points([
            [310, 230], [330, 230], [330, 250], [310, 250],
        ]);
        $response = [
            'capturex'   => '10',
            'capturey'   => '10',
            'margindata' => $margin,
        ];
        [$fraction, $state] = $q->grade_response($response);
        unset($state);
        $this->assertLessThan(0.1, $fraction);
    }

    // -------------------------------------------------------------------
    // Completeness checks
    // -------------------------------------------------------------------

    /**
     * A response with no captured position is not complete.
     */
    public function test_missing_capture_is_not_complete(): void
    {
        $this->resetAfterTest();
        $q        = $this->get_centred_question();
        $response = [
            'capturex'   => '',
            'capturey'   => '',
            'margindata' => $this->encode_points([[1, 1], [2, 2], [3, 3]]),
        ];
        $this->assertFalse($q->is_complete_response($response));
    }

    /**
     * A response with fewer than three margin points is not complete.
     */
    public function test_too_few_margin_points_is_not_complete(): void
    {
        $this->resetAfterTest();
        $q        = $this->get_centred_question();
        $response = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([[1, 1], [2, 2]]),
        ];
        $this->assertFalse($q->is_complete_response($response));
    }

    /**
     * A response with a valid capture position and at least three margin points is complete.
     */
    public function test_complete_response_is_recognised(): void
    {
        $this->resetAfterTest();
        $q        = $this->get_centred_question();
        $response = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([[1, 1], [2, 2], [3, 3]]),
        ];
        $this->assertTrue($q->is_complete_response($response));
    }

    // -------------------------------------------------------------------
    // Centroid helper
    // -------------------------------------------------------------------

    /**
     * The static centroid() function returns the area-weighted centroid of a polygon.
     */
    public function test_centroid_of_square(): void
    {
        $pts = [[0, 0], [4, 0], [4, 4], [0, 4]];
        [$cx, $cy] = qtype_dermoscopysim_question::centroid($pts);
        $this->assertEqualsWithDelta(2.0, $cx, 0.001);
        $this->assertEqualsWithDelta(2.0, $cy, 0.001);
    }
}
