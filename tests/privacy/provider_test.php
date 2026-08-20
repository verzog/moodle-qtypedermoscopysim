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
 * PHPUnit tests for the qtype_dermoscopysim privacy provider.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_dermoscopysim\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the dermoscopy simulator privacy provider.
 *
 * The plugin stores no personal data of its own, so it is a null provider.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
