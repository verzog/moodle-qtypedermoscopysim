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
 * Privacy provider for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace qtype_dermoscopysim\privacy;

/**
 * Privacy provider declaring that this plugin stores no personal data.
 *
 * Student responses are stored by the core question subsystem, which
 * handles its own privacy compliance for attempt data.
 *
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Explain why this plugin stores no personal data.
     *
     * @return string the language string key for the explanation
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
