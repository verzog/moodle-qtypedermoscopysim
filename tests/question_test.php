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
 * PHPUnit tests for qtype_dermoscopysim question grading.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace qtype_dermoscopysim;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/dermoscopysim/question.php');

/**
 * Unit tests for the dermoscopic simulator question definition grading.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
#[CoversClass(\qtype_dermoscopysim_question::class)]
final class question_test extends \advanced_testcase {

    /**
     * Returns a centred-lesion question for use in tests.
     *
     * @return \qtype_dermoscopysim_question
     */
    protected function get_centred_question(): \qtype_dermoscopysim_question {
        return \test_question_maker::make_question('dermoscopysim', 'lesion_centred');
    }

    /**
     * Encodes a list of [x, y] pairs as the JSON written by the student JS.
     *
     * @param array $pts List of [x, y] pairs.
     * @return string
     */
    protected function encode_points(array $pts): string {
        return json_encode($pts);
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
     * Builds a margin-only response array as sent by the student JS.
     *
     * @param array $pts List of [x, y] pairs describing the margin polygon.
     * @return array the response array
     */
    protected function margin_response(array $pts): array {
        return ['margindata' => $this->encode_points($pts)];
    }

    // Capture grading tests.

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

    // Margin grading tests: distance method.

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

    // Margin grading tests: zones method.

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

    // Margin grading tests: ideal method.

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

    /**
     * A student margin far from the ideal polygon scores below full marks.
     */
    public function test_margin_ideal_deviation_scores_lower(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod     = 'ideal';
        $q->idealtolerancemm = 1.0;

        // A much larger square than the 60×60 ideal: mean radial deviation is
        // well beyond the 1 mm tolerance, so the score drops below full marks.
        $response = $this->margin_response([
            [250, 170], [390, 170], [390, 310], [250, 310],
        ]);
        $score = $q->grade_margin($response);
        $this->assertLessThan(1.0, $score);
    }

    // Combined grading tests.

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

    /**
     * A capture weighting of zero grades entirely on the margin sub-score.
     */
    public function test_grade_response_zero_capture_weight_uses_margin_only(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->captureweight = 0;
        $q->marginmaxmm   = 6.0;

        // Deliberately bad capture, perfect margin: overall follows the margin.
        $response = [
            'capturex'   => '10',
            'capturey'   => '10',
            'margindata' => $this->encode_points([
                [270, 190], [370, 190], [370, 290], [270, 290],
            ]),
        ];
        [$fraction, $state] = $q->grade_response($response);
        unset($state);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.05);
    }

    /**
     * A capture weighting of 100 grades entirely on the capture sub-score.
     */
    public function test_grade_response_full_capture_weight_uses_capture_only(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->captureweight = 100;

        // Perfect capture, margin clipping the lesion: overall follows capture.
        $response = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([
                [310, 230], [330, 230], [330, 250], [310, 250],
            ]),
        ];
        [$fraction, $state] = $q->grade_response($response);
        unset($state);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.001);
    }

    // Response predicate tests.

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

    /**
     * A response is gradable as soon as a capture position is present.
     */
    public function test_is_gradable_response(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $this->assertTrue($q->is_gradable_response($this->capture_response(320, 240)));
        $this->assertFalse($q->is_gradable_response(['capturex' => '', 'capturey' => '']));
    }

    /**
     * Two identical responses are recognised as the same; a changed one is not.
     */
    public function test_is_same_response(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $a = [
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([[1, 1], [2, 2], [3, 3]]),
        ];
        $b = $a;
        $this->assertTrue($q->is_same_response($a, $b));

        $b['capturex'] = '321';
        $this->assertFalse($q->is_same_response($a, $b));
    }

    /**
     * The expected response fields are the capture coordinates and the margin.
     */
    public function test_get_expected_data(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $this->assertEquals(
            ['capturex', 'capturey', 'margindata'],
            array_keys($q->get_expected_data())
        );
    }

    /**
     * The validation error asks for a capture first, then for a margin.
     */
    public function test_get_validation_error_messages(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $nocapture = $q->get_validation_error(['capturex' => '', 'capturey' => '']);
        $this->assertEquals(get_string('pleasecapture', 'qtype_dermoscopysim'), $nocapture);

        $nomargin = $q->get_validation_error([
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => '',
        ]);
        $this->assertEquals(get_string('pleasemargin', 'qtype_dermoscopysim'), $nomargin);
    }

    /**
     * The response summary reports the capture point and the margin point count,
     * and is null when there is no capture.
     */
    public function test_summarise_response(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $this->assertNull($q->summarise_response(['capturex' => '', 'capturey' => '']));

        $summary = $q->summarise_response([
            'capturex'   => '320',
            'capturey'   => '240',
            'margindata' => $this->encode_points([[1, 1], [2, 2], [3, 3]]),
        ]);
        $this->assertIsString($summary);
        $this->assertStringContainsString('320', $summary);
        $this->assertStringContainsString('3', $summary);
    }

    // Correct-response construction tests.

    /**
     * The distance-method correct response captures at the lesion centroid and
     * returns a margin polygon offset outward from the lesion.
     */
    public function test_get_correct_response_distance(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $correct = $q->get_correct_response();
        $this->assertEqualsWithDelta(320.0, (float) $correct['capturex'], 0.5);
        $this->assertEqualsWithDelta(240.0, (float) $correct['capturey'], 0.5);
        $this->assertCount(4, $q->decode_points($correct['margindata']));
    }

    /**
     * The ideal-method correct response returns the instructor's ideal polygon.
     */
    public function test_get_correct_response_ideal(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->marginmethod = 'ideal';

        $correct = $q->get_correct_response();
        $this->assertEquals(
            $q->decode_points($q->idealmargindata),
            $q->decode_points($correct['margindata'])
        );
    }

    /**
     * A question with no traced lesion has no correct response to offer.
     */
    public function test_get_correct_response_without_lesion_is_null(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();
        $q->lesiondata = '';
        $this->assertNull($q->get_correct_response());
    }

    // Geometry helper tests.

    /**
     * The static centroid() returns the area-weighted centroid of a polygon.
     */
    public function test_centroid_of_square(): void {
        $pts = [[0, 0], [4, 0], [4, 4], [0, 4]];
        [$cx, $cy] = \qtype_dermoscopysim_question::centroid($pts);
        $this->assertEqualsWithDelta(2.0, $cx, 0.001);
        $this->assertEqualsWithDelta(2.0, $cy, 0.001);
    }

    /**
     * centroid() falls back to the vertex mean for a degenerate (collinear) polygon.
     */
    public function test_centroid_degenerate_uses_vertex_mean(): void {
        $pts = [[0, 0], [2, 0], [4, 0]];
        [$cx, $cy] = \qtype_dermoscopysim_question::centroid($pts);
        $this->assertEqualsWithDelta(2.0, $cx, 0.001);
        $this->assertEqualsWithDelta(0.0, $cy, 0.001);
    }

    /**
     * radius_at_angle() returns the ray distance to a square boundary.
     */
    public function test_radius_at_angle_square(): void {
        // Square 0..10 centred at (5, 5): the ray due east hits x = 10 at r = 5.
        $square = [[0, 0], [10, 0], [10, 10], [0, 10]];
        $r = \qtype_dermoscopysim_question::radius_at_angle($square, 5, 5, 0.0);
        $this->assertEqualsWithDelta(5.0, $r, 0.001);
    }

    /**
     * decode_points() accepts a valid polygon and rejects malformed input.
     */
    public function test_decode_points_validation(): void {
        $this->resetAfterTest();
        $q = $this->get_centred_question();

        $this->assertSame([[1.0, 2.0], [3.0, 4.0]], $q->decode_points('[[1,2],[3,4]]'));
        $this->assertSame([], $q->decode_points(''));
        $this->assertSame([], $q->decode_points(null));
        $this->assertSame([], $q->decode_points('not json'));
        $this->assertSame([], $q->decode_points('{"x":1}'));
        $this->assertSame([], $q->decode_points('[[1,2],[3]]'));
    }
}
