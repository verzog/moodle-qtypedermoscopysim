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
 * PHPUnit tests for the qtype_dermoscopysim question type class.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_dermoscopysim;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/dermoscopysim/questiontype.php');

/**
 * Tests for the dermoscopy simulator question type registration and options.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\qtype_dermoscopysim::class)]
final class questiontype_test extends \advanced_testcase {
    /**
     * Builds a fresh question type instance.
     *
     * @return \qtype_dermoscopysim
     */
    protected function qtype(): \qtype_dermoscopysim {
        return new \qtype_dermoscopysim();
    }

    /**
     * The question type reports its Frankenstyle short name.
     */
    public function test_name(): void {
        $this->assertEquals('dermoscopysim', $this->qtype()->name());
    }

    /**
     * The extra question fields name the options table first, then every
     * option column persisted for a question.
     */
    public function test_extra_question_fields(): void {
        $fields = $this->qtype()->extra_question_fields();
        $table = array_shift($fields);

        $this->assertEquals('qtype_dermoscopysim', $table);
        foreach (
            [
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
            ] as $column
        ) {
            $this->assertContains($column, $fields);
        }
    }

    /**
     * The random guess score is zero: nothing is gained by guessing.
     */
    public function test_random_guess_score_is_zero(): void {
        $this->assertEquals(0, $this->qtype()->get_random_guess_score(null));
    }

    /**
     * The question type is registered and resolvable through the question bank.
     */
    public function test_question_type_is_registered(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(
            \qtype_dermoscopysim::class,
            \question_bank::get_qtype('dermoscopysim')
        );
    }
}
