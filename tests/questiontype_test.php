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
 * PHPUnit tests for the qtype_dermoscopysim question type class.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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
        foreach ([
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
        ] as $column) {
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
