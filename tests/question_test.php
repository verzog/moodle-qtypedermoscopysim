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
class qtype_dermoscopysim_question_test extends advanced_testcase {

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Returns a centred-lesion question for use in tests.
     *
     * @return qtype_dermoscopysim_question
     */
    protected function get_centred_question() {
        return test_question_maker::make_question('dermoscopysim', 'lesion_centred');
    }

    /**
     * Builds a capture-only response array as sent by the student JS.
     *
     * @param float|string $x the capture x coordinate in image pixels
     * @param float|string $y the capture y coordinate in image pixels
     * @return array the response array
     */
    protected function capture_response($x, $y): array {
        return ['capturex' => (string) $x, 'capturey' => (string) $y];
    }

    /**
     * Encodes a list of [x, y] pairs as the JSON format written by the student JS.
     *
     * @param array $pts List of [x, y] pairs.
     * @return string
     */
    protected function encode_points(array $pts): string {
        return json_encode($pts);
    }

    /**
     * Builds a margin-only response array as sent by the student JS.
     *
     * @param array $pts List of [x, y] pairs describing the margin polygon.
     * @return array the response array
     */
    protected function margin_response(array $pts): array {
        return ['margindata' => $this->encode_points($pts)];
    }

    // -------------------------------------------------------------------
    // Capture grading
    // -------------------------------------------------------------------

    /**
     * A response with the lens exactly on the lesion centroid earns full capture marks.
     */
    public function test_capture_exact_centre_scores_full(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lesion centroid is (320, 240).
        $score = $q->grade_capture($this->capture_response(320, 240));
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    /**
     * A response with the lens within the tolerance radius earns full capture marks.
     */
    public function test_capture_within_tolerance_scores_full(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Tolerance is 2.0 mm; scale is 0.1 mm/px → 20 px tolerance radius.
        // Move 15 px right of centroid: 15 × 0.1 = 1.5 mm < 2.0 mm.
        $score = $q->grade_capture($this->capture_response(335, 240));
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    /**
     * A response beyond the lens radius earns zero capture marks.
     */
    public function test_capture_outside_lens_scores_zero(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lens diameter is 20 mm → radius 10 mm → 100 px.
        // Move 110 px right: well outside the lens.
        $score = $q->grade_capture($this->capture_response(430, 240));
        $this->assertEqualsWithDelta(0.0, $score, 0.001);
    }

    /**
     * A response halfway between tolerance edge and lens edge earns partial capture.
     */
    public function test_capture_mid_range_scores_partial(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Tolerance 2 mm (20 px), lens radius 10 mm (100 px).
        // At 60 px = 6 mm: fraction through falloff = (6 - 2) / (10 - 2) = 0.5.
        // Expected score = 1 - 0.5 = 0.5.
        $score = $q->grade_capture($this->capture_response(380, 240));
        $this->assertEqualsWithDelta(0.5, $score, 0.05);
    }

    /**
     * A response with no captured position earns zero capture marks.
     */
    public function test_capture_missing_scores_zero(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $score = $q->grade_capture(['capturex' => '', 'capturey' => '']);
        $this->assertEqualsWithDelta(0.0, $score, 0.001);
    }

    // -------------------------------------------------------------------
    // Margin grading — distance method
    // -------------------------------------------------------------------

    /**
     * A margin polygon that surrounds the lesion with clearance inside the band
     * earns high margin marks (distance method).
     */
    public function test_margin_distance_ideal_clearance_scores_full(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'distance';
        $q->marginmm     = 2.0;
        $q->marginmaxmm  = 4.0;

        // Lesion square: 300–340 × 220–260 (centroid 320, 240, half-width 20 px).
        // Margin 30 px = 3 mm extra on each side: 270–370 × 190–290. Radial
        // clearance stays inside the 2–4 mm band except close to the corners,
        // so the great majority of sampled rays are in band.
        $response = $this->margin_response([
            [270, 190], [370, 190], [370, 290], [270, 290],
        ]);
        $score = $q->grade_margin($response);
        $this->assertGreaterThan(0.8, $score);
    }

    /**
     * A margin polygon that clips the lesion earns zero margin marks.
     */
    public function test_margin_clips_lesion_scores_zero(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Margin polygon smaller than the lesion polygon.
        $response = $this->margin_response([
            [310, 230], [330, 230], [330, 250], [310, 250],
        ]);
        $score = $q->grade_margin($response);
        $this->assertEqualsWithDelta(0.0, $score, 0.05);
    }

    /**
     * The zones method awards full marks only when every ray is in band.
     */
    public function test_margin_zones_all_in_band_scores_full(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'zones';
        $q->marginmm     = 2.0;
        // Widen the band so the corners of a square margin also fall inside it:
        // a 3 mm nominal square reaches ~4.24 mm radial clearance at the corners.
        $q->marginmaxmm  = 6.0;

        $response = $this->margin_response([
            [270, 190], [370, 190], [370, 290], [270, 290],
        ]);
        $score = $q->grade_margin($response);
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    /**
     * The zones method awards zero when any ray falls outside the band.
     */
    public function test_margin_zones_partial_out_of_band_scores_zero(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'zones';
        $q->marginmm     = 2.0;
        $q->marginmaxmm  = 4.0;

        // A 3 mm nominal square exceeds 4 mm clearance at its corners, so at
        // least one ray is out of band and the zones method scores zero.
        $response = $this->margin_response([
            [270, 190], [370, 190], [370, 290], [270, 290],
        ]);
        $score = $q->grade_margin($response);
        $this->assertEqualsWithDelta(0.0, $score, 0.001);
    }

    // -------------------------------------------------------------------
    // Margin grading — ideal method
    // -------------------------------------------------------------------

    /**
     * A student margin matching the instructor's ideal polygon earns full marks.
     */
    public function test_margin_ideal_match_scores_full(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'ideal';

        // The helper's ideal margin polygon: 60×60 px square centred at (320, 240).
        $response = $this->margin_response([
            [290, 210], [350, 210], [350, 270], [290, 270],
        ]);
        $score = $q->grade_margin($response);
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    // -------------------------------------------------------------------
    // Combined grading
    // -------------------------------------------------------------------

    /**
     * Perfect capture + perfect margin = overall score of 1.0.
     */
    public function test_grade_response_perfect_scores_one(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        // Widen the band so a square margin scores full marks on every ray,
        // isolating this test to the capture/margin weighting arithmetic.
        $q->marginmaxmm = 6.0;

        $response = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([
                [270, 190], [370, 190], [370, 290], [270, 290],
            ]),
        ];
        [$fraction, $state] = $q->grade_response($response);
        unset($state);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.05);
    }

    /**
     * Poor capture + poor margin = overall score near 0.
     */
    public function test_grade_response_all_wrong_scores_near_zero(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        // Lens far from lesion, margin clipping the lesion.
        $response = [
            'capturex'   => '10',
            'capturey'   => '10',
            'margindata' => $this->encode_points([
                [310, 230], [330, 230], [330, 250], [310, 250],
            ]),
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
    public function test_missing_capture_is_not_complete(): void {
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
    public function test_too_few_margin_points_is_not_complete(): void {
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
    public function test_complete_response_is_recognised(): void {
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
    public function test_centroid_of_square(): void {
        $pts = [[0, 0], [4, 0], [4, 4], [0, 4]];
        [$cx, $cy] = qtype_dermoscopysim_question::centroid($pts);
        $this->assertEqualsWithDelta(2.0, $cx, 0.001);
        $this->assertEqualsWithDelta(2.0, $cy, 0.001);
    }
}
