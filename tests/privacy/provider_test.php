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
 * PHPUnit tests for the qtype_dermoscopysim privacy provider.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace qtype_dermoscopysim\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the dermoscopy simulator privacy provider.
 *
 * The plugin stores no personal data of its own, so it is a null provider.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
#[CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {

    /**
     * The provider declares itself a null provider (stores no personal data).
     */
    public function test_provider_is_a_null_provider(): void {
        $this->assertContains(
            \core_privacy\local\metadata\null_provider::class,
            class_implements(provider::class)
        );
    }

    /**
     * get_reason() returns the language key explaining why no data is stored,
     * and that key resolves to a real string.
     */
    public function test_get_reason_resolves_to_a_string(): void {
        $reason = provider::get_reason();
        $this->assertEquals('privacy:metadata', $reason);
        $this->assertNotEmpty(get_string($reason, 'qtype_dermoscopysim'));
    }
}
