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
 * Question definition class for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Represents one dermoscopy simulator question.
 *
 * The response consists of the capture position (capturex, capturey — the
 * lens centre in image pixels when the student pressed capture) and the
 * excision margin polygon (margindata — a JSON array of [x, y] image-pixel
 * points). All grading is performed server-side from these coordinates.
 *
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_dermoscopysim_question extends question_graded_automatically {
    /** @var float millimetres represented by one image pixel. */
    public $mmperpx;

    /** @var float dermoscope faceplate diameter in millimetres. */
    public $lensdiametermm;

    /** @var float nominal viewfinder magnification. */
    public $magnification;

    /** @var float distance from lesion centre (mm) earning full capture marks. */
    public $capturetolerancemm;

    /** @var int percentage of the grade awarded for the capture step. */
    public $captureweight;

    /** @var string margin assessment method: ideal, distance or zones. */
    public $marginmethod;

    /** @var float minimum acceptable clearance from the lesion edge (mm). */
    public $marginmm;

    /** @var float maximum acceptable clearance from the lesion edge (mm). */
    public $marginmaxmm;

    /** @var float mean deviation from the ideal margin (mm) earning full marks. */
    public $idealtolerancemm;

    /** @var string JSON polygon of the lesion boundary in image pixels. */
    public $lesiondata;

    /** @var string JSON polygon of the instructor-drawn ideal margin. */
    public $idealmargindata;

    /** @var int number of radial samples used when scoring margins. */
    const RAY_SAMPLES = 72;

    /**
     * Declare the fields this question type expects in a response.
     *
     * @return array field name to PARAM type map
     */
    public function get_expected_data() {
        return [
            'capturex' => PARAM_RAW_TRIMMED,
            'capturey' => PARAM_RAW_TRIMMED,
            'margindata' => PARAM_RAW_TRIMMED,
        ];
    }

    /**
     * Produce a plain-text summary of a response for reports.
     *
     * @param array $response the response data
     * @return string|null a summary, or null if there is no response
     */
    public function summarise_response(array $response) {
        if (!$this->has_capture($response)) {
            return null;
        }

        $points = $this->decode_points($response['margindata'] ?? '');
        $a = new stdClass();
        $a->x = round((float) $response['capturex']);
        $a->y = round((float) $response['capturey']);
        $a->count = count($points);
        return get_string('responsesummary', 'qtype_dermoscopysim', $a);
    }

    /**
     * Decide whether a response is complete enough to be graded as final.
     *
     * @param array $response the response data
     * @return bool true if the capture and a usable margin polygon are present
     */
    public function is_complete_response(array $response) {
        return $this->has_capture($response)
            && count($this->decode_points($response['margindata'] ?? '')) >= 3;
    }

    /**
     * Decide whether a response contains any gradable data at all.
     *
     * @param array $response the response data
     * @return bool true if any part of the response has been attempted
     */
    public function is_gradable_response(array $response) {
        return $this->has_capture($response);
    }

    /**
     * Explain what is missing from an incomplete response.
     *
     * @param array $response the response data
     * @return string a validation message for the student
     */
    public function get_validation_error(array $response) {
        if (!$this->has_capture($response)) {
            return get_string('pleasecapture', 'qtype_dermoscopysim');
        }
        return get_string('pleasemargin', 'qtype_dermoscopysim');
    }

    /**
     * Decide whether two responses are effectively the same.
     *
     * @param array $prevresponse the earlier response
     * @param array $newresponse the later response
     * @return bool true if nothing has changed
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach (['capturex', 'capturey', 'margindata'] as $field) {
            $same = question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse,
                $newresponse,
                $field
            );
            if (!$same) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build the model answer used by "fill in correct responses".
     *
     * @return array|null the correct response fields
     */
    public function get_correct_response() {
        $lesion = $this->decode_points($this->lesiondata);
        if (count($lesion) < 3) {
            return null;
        }

        [$cx, $cy] = self::centroid($lesion);

        $ideal = $this->decode_points($this->idealmargindata);
        if ($this->marginmethod === 'ideal' && count($ideal) >= 3) {
            $margin = $ideal;
        } else {
            $clearancepx = (($this->marginmm + $this->marginmaxmm) / 2) / $this->mmperpx;
            $margin = [];
            foreach ($lesion as $p) {
                $dx = $p[0] - $cx;
                $dy = $p[1] - $cy;
                $len = sqrt($dx * $dx + $dy * $dy);
                if ($len < 0.001) {
                    $margin[] = [$p[0], $p[1] + $clearancepx];
                    continue;
                }
                $margin[] = [
                    round($cx + $dx * (($len + $clearancepx) / $len), 1),
                    round($cy + $dy * (($len + $clearancepx) / $len), 1),
                ];
            }
        }

        return [
            'capturex' => round($cx, 1),
            'capturey' => round($cy, 1),
            'margindata' => json_encode($margin),
        ];
    }

    /**
     * Grade a response and return the resulting fraction and state.
     *
     * @param array $response the response data
     * @return array the fraction achieved and the matching question state
     */
    public function grade_response(array $response) {
        $capturescore = $this->grade_capture($response);
        $marginscore = $this->grade_margin($response);

        $weight = max(0, min(100, (int) $this->captureweight)) / 100;
        $fraction = ($weight * $capturescore) + ((1 - $weight) * $marginscore);
        $fraction = max(0, min(1, $fraction));

        return [$fraction, question_state::graded_state_for_fraction($fraction)];
    }

    /**
     * Score the capture step from 0 to 1 based on centring accuracy.
     *
     * Full marks inside the tolerance radius, falling linearly to zero at
     * the edge of the faceplate.
     *
     * @param array $response the response data
     * @return float the capture sub-score between 0 and 1
     */
    public function grade_capture(array $response) {
        if (!$this->has_capture($response)) {
            return 0.0;
        }

        $lesion = $this->decode_points($this->lesiondata);
        if (count($lesion) < 3) {
            return 0.0;
        }

        [$cx, $cy] = self::centroid($lesion);
        $dx = ((float) $response['capturex']) - $cx;
        $dy = ((float) $response['capturey']) - $cy;
        $distmm = sqrt($dx * $dx + $dy * $dy) * $this->mmperpx;

        $tolerance = max(0.01, $this->capturetolerancemm);
        $limit = max($tolerance + 0.01, $this->lensdiametermm / 2);

        if ($distmm <= $tolerance) {
            return 1.0;
        }
        if ($distmm >= $limit) {
            return 0.0;
        }
        return 1.0 - (($distmm - $tolerance) / ($limit - $tolerance));
    }

    /**
     * Score the margin step from 0 to 1 using the configured method.
     *
     * Clearance is sampled along evenly spaced rays from the lesion
     * centroid, comparing the student polygon radius with the lesion (or
     * ideal margin) radius at each angle.
     *
     * @param array $response the response data
     * @return float the margin sub-score between 0 and 1
     */
    public function grade_margin(array $response) {
        $student = $this->decode_points($response['margindata'] ?? '');
        $lesion = $this->decode_points($this->lesiondata);
        if (count($student) < 3 || count($lesion) < 3) {
            return 0.0;
        }

        [$cx, $cy] = self::centroid($lesion);

        if ($this->marginmethod === 'ideal') {
            return $this->grade_margin_ideal($student, $cx, $cy);
        }

        $inband = 0;
        $cutslesion = false;
        $samples = 0;
        for ($i = 0; $i < self::RAY_SAMPLES; $i++) {
            $angle = ($i / self::RAY_SAMPLES) * 2 * M_PI;
            $rlesion = self::radius_at_angle($lesion, $cx, $cy, $angle);
            $rstudent = self::radius_at_angle($student, $cx, $cy, $angle);
            if ($rlesion === null) {
                continue;
            }
            $samples++;
            if ($rstudent === null) {
                $cutslesion = true;
                continue;
            }
            $clearance = ($rstudent - $rlesion) * $this->mmperpx;
            if ($clearance < 0) {
                $cutslesion = true;
            }
            if ($clearance >= $this->marginmm && $clearance <= $this->marginmaxmm) {
                $inband++;
            }
        }

        if ($samples === 0) {
            return 0.0;
        }

        if ($this->marginmethod === 'zones') {
            return (!$cutslesion && $inband === $samples) ? 1.0 : 0.0;
        }

        return $inband / $samples;
    }

    /**
     * Score the student margin against the instructor-drawn ideal margin.
     *
     * Full marks when the mean radial deviation is within the tolerance,
     * falling linearly to zero at three times the tolerance.
     *
     * @param array $student the student's margin polygon points
     * @param float $cx the lesion centroid x coordinate
     * @param float $cy the lesion centroid y coordinate
     * @return float the sub-score between 0 and 1
     */
    protected function grade_margin_ideal(array $student, $cx, $cy) {
        $ideal = $this->decode_points($this->idealmargindata);
        if (count($ideal) < 3) {
            return 0.0;
        }

        $totaldev = 0.0;
        $samples = 0;
        for ($i = 0; $i < self::RAY_SAMPLES; $i++) {
            $angle = ($i / self::RAY_SAMPLES) * 2 * M_PI;
            $rideal = self::radius_at_angle($ideal, $cx, $cy, $angle);
            $rstudent = self::radius_at_angle($student, $cx, $cy, $angle);
            if ($rideal === null) {
                continue;
            }
            $samples++;
            if ($rstudent === null) {
                $totaldev += $rideal;
                continue;
            }
            $totaldev += abs($rstudent - $rideal);
        }

        if ($samples === 0) {
            return 0.0;
        }

        $meandevmm = ($totaldev / $samples) * $this->mmperpx;
        $tolerance = max(0.01, $this->idealtolerancemm);

        if ($meandevmm <= $tolerance) {
            return 1.0;
        }
        if ($meandevmm >= 3 * $tolerance) {
            return 0.0;
        }
        return 1.0 - (($meandevmm - $tolerance) / (2 * $tolerance));
    }

    /**
     * Control access to files served for this question.
     *
     * @param question_attempt $qa the question attempt
     * @param question_display_options $options the display options
     * @param string $component the component the file belongs to
     * @param string $filearea the file area
     * @param array $args remaining pluginfile arguments
     * @param bool $forcedownload whether a download is being forced
     * @return bool true if access should be allowed
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component === 'qtype_dermoscopysim' && $filearea === 'baseimage') {
            return $args[0] == $this->id;
        }
        return parent::check_file_access(
            $qa,
            $options,
            $component,
            $filearea,
            $args,
            $forcedownload
        );
    }

    /**
     * Decide whether a response includes a valid capture position.
     *
     * @param array $response the response data
     * @return bool true if both capture coordinates are numeric
     */
    protected function has_capture(array $response) {
        return isset($response['capturex']) && is_numeric($response['capturex'])
            && isset($response['capturey']) && is_numeric($response['capturey']);
    }

    /**
     * Decode a JSON polygon string into an array of [x, y] float pairs.
     *
     * @param string|null $json the JSON string to decode
     * @return array a list of [x, y] pairs; empty if the input is invalid
     */
    public function decode_points($json) {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $points = [];
        foreach ($data as $pair) {
            $valid = is_array($pair) && count($pair) >= 2
                && is_numeric($pair[0]) && is_numeric($pair[1]);
            if (!$valid) {
                return [];
            }
            $points[] = [(float) $pair[0], (float) $pair[1]];
        }
        return $points;
    }

    /**
     * Compute the centroid of a polygon.
     *
     * Uses the standard area-weighted formula, falling back to the vertex
     * mean for degenerate (near-zero-area) polygons.
     *
     * @param array $points the polygon vertices as [x, y] pairs
     * @return float[] the centroid as [x, y]
     */
    public static function centroid(array $points) {
        $n = count($points);
        $area = 0.0;
        $cx = 0.0;
        $cy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $cross = ($points[$i][0] * $points[$j][1]) - ($points[$j][0] * $points[$i][1]);
            $area += $cross;
            $cx += ($points[$i][0] + $points[$j][0]) * $cross;
            $cy += ($points[$i][1] + $points[$j][1]) * $cross;
        }
        if (abs($area) < 0.0001) {
            $sx = 0.0;
            $sy = 0.0;
            foreach ($points as $p) {
                $sx += $p[0];
                $sy += $p[1];
            }
            return [$sx / $n, $sy / $n];
        }
        $area *= 0.5;
        return [$cx / (6 * $area), $cy / (6 * $area)];
    }

    /**
     * Find the distance from a point to a polygon boundary along a ray.
     *
     * Casts a ray from (cx, cy) at the given angle and returns the distance
     * to the furthest intersection with the polygon, or null if the ray
     * never crosses it.
     *
     * @param array $points the polygon vertices as [x, y] pairs
     * @param float $cx the ray origin x coordinate
     * @param float $cy the ray origin y coordinate
     * @param float $angle the ray angle in radians
     * @return float|null the distance in pixels, or null if there is no hit
     */
    public static function radius_at_angle(array $points, $cx, $cy, $angle) {
        $dx = cos($angle);
        $dy = sin($angle);
        $n = count($points);
        $best = null;

        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $ex = $points[$j][0] - $points[$i][0];
            $ey = $points[$j][1] - $points[$i][1];
            $denom = ($dx * $ey) - ($dy * $ex);
            if (abs($denom) < 0.0000001) {
                continue;
            }
            $ox = $points[$i][0] - $cx;
            $oy = $points[$i][1] - $cy;
            $t = (($ox * $ey) - ($oy * $ex)) / $denom;
            $u = (($ox * $dy) - ($oy * $dx)) / $denom;
            if ($t > 0 && $u >= 0 && $u <= 1) {
                if ($best === null || $t > $best) {
                    $best = $t;
                }
            }
        }

        return $best;
    }
}
