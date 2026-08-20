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
 * Editing form for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The editing form for dermoscopy simulator questions.
 *
 * Alongside the usual settings, the form embeds a JavaScript authoring
 * canvas used to calibrate the photograph scale, trace the lesion boundary
 * and optionally draw the ideal excision margin. The canvas writes its
 * results into hidden form fields which are validated server-side.
 *
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class qtype_dermoscopysim_edit_form extends question_edit_form {

    /**
     * Add the question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built
     * @return void
     */
    protected function definition_inner($mform) {
        $mform->addElement('header', 'imageheader', get_string('imageheader', 'qtype_dermoscopysim'));
        $mform->setExpanded('imageheader', true);

        $mform->addElement(
            'filemanager',
            'baseimage',
            get_string('baseimage', 'qtype_dermoscopysim'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']]
        );
        $mform->addHelpButton('baseimage', 'baseimage', 'qtype_dermoscopysim');

        $editorhtml = html_writer::div(
            '',
            'qtype_dermoscopysim-editor',
            ['id' => 'qtype_dermoscopysim_editor']
        );
        $mform->addElement('html', $editorhtml);

        $mform->addElement(
            'text',
            'mmperpx',
            get_string('mmperpx', 'qtype_dermoscopysim'),
            ['size' => 12]
        );
        $mform->setType('mmperpx', PARAM_FLOAT);
        $mform->addHelpButton('mmperpx', 'mmperpx', 'qtype_dermoscopysim');

        $mform->addElement('hidden', 'lesiondata', '');
        $mform->setType('lesiondata', PARAM_RAW_TRIMMED);

        $mform->addElement('hidden', 'idealmargindata', '');
        $mform->setType('idealmargindata', PARAM_RAW_TRIMMED);

        $mform->addElement('header', 'captureheader', get_string('captureheader', 'qtype_dermoscopysim'));
        $mform->setExpanded('captureheader', true);

        $mform->addElement(
            'text',
            'lensdiametermm',
            get_string('lensdiametermm', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('lensdiametermm', PARAM_FLOAT);
        $mform->setDefault('lensdiametermm', 20);

        $mform->addElement(
            'text',
            'magnification',
            get_string('magnification', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('magnification', PARAM_FLOAT);
        $mform->setDefault('magnification', 10);

        $mform->addElement(
            'text',
            'capturetolerancemm',
            get_string('capturetolerancemm', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('capturetolerancemm', PARAM_FLOAT);
        $mform->setDefault('capturetolerancemm', 2);
        $mform->addHelpButton('capturetolerancemm', 'capturetolerancemm', 'qtype_dermoscopysim');

        $mform->addElement(
            'text',
            'captureweight',
            get_string('captureweight', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('captureweight', PARAM_INT);
        $mform->setDefault('captureweight', 30);
        $mform->addHelpButton('captureweight', 'captureweight', 'qtype_dermoscopysim');

        $mform->addElement('header', 'marginheader', get_string('marginheader', 'qtype_dermoscopysim'));
        $mform->setExpanded('marginheader', true);

        $methods = [
            'distance' => get_string('methoddistance', 'qtype_dermoscopysim'),
            'zones' => get_string('methodzones', 'qtype_dermoscopysim'),
            'ideal' => get_string('methodideal', 'qtype_dermoscopysim'),
        ];
        $mform->addElement(
            'select',
            'marginmethod',
            get_string('marginmethod', 'qtype_dermoscopysim'),
            $methods
        );
        $mform->setDefault('marginmethod', 'distance');
        $mform->addHelpButton('marginmethod', 'marginmethod', 'qtype_dermoscopysim');

        $mform->addElement(
            'text',
            'marginmm',
            get_string('marginmm', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('marginmm', PARAM_FLOAT);
        $mform->setDefault('marginmm', 2);

        $mform->addElement(
            'text',
            'marginmaxmm',
            get_string('marginmaxmm', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('marginmaxmm', PARAM_FLOAT);
        $mform->setDefault('marginmaxmm', 4);

        $mform->addElement(
            'text',
            'idealtolerancemm',
            get_string('idealtolerancemm', 'qtype_dermoscopysim'),
            ['size' => 6]
        );
        $mform->setType('idealtolerancemm', PARAM_FLOAT);
        $mform->setDefault('idealtolerancemm', 1);
        $mform->hideIf('idealtolerancemm', 'marginmethod', 'neq', 'ideal');
        $mform->hideIf('marginmm', 'marginmethod', 'eq', 'ideal');
        $mform->hideIf('marginmaxmm', 'marginmethod', 'eq', 'ideal');

        $labels = [
            'loadimage' => get_string('loadimage', 'qtype_dermoscopysim'),
            'calibratebyline' => get_string('calibratebyline', 'qtype_dermoscopysim'),
            'drawlesion' => get_string('drawlesion', 'qtype_dermoscopysim'),
            'drawideal' => get_string('drawideal', 'qtype_dermoscopysim'),
            'finishshape' => get_string('finishshape', 'qtype_dermoscopysim'),
            'clearshape' => get_string('clearshape', 'qtype_dermoscopysim'),
            'editorintro' => get_string('editorintro', 'qtype_dermoscopysim'),
            'entermm' => get_string('entermm', 'qtype_dermoscopysim'),
            'imagenotfound' => get_string('imagenotfound', 'qtype_dermoscopysim'),
            'lesionlabel' => get_string('lesionlabel', 'qtype_dermoscopysim'),
            'ideallabel' => get_string('ideallabel', 'qtype_dermoscopysim'),
        ];
        global $PAGE;
        $PAGE->requires->js_call_amd(
            'qtype_dermoscopysim/editform',
            'init',
            ['qtype_dermoscopysim_editor', $labels]
        );
    }

    /**
     * Prepare existing question data for display in the form.
     *
     * @param object $question the question data being edited
     * @return object the prepared question data
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        if (!empty($question->options)) {
            $fields = [
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
            ];
            foreach ($fields as $field) {
                if (isset($question->options->$field)) {
                    $question->$field = $question->options->$field;
                }
            }
        }

        $draftitemid = file_get_submitted_draft_itemid('baseimage');
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'qtype_dermoscopysim',
            'baseimage',
            !empty($question->id) ? (int) $question->id : null,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
        $question->baseimage = $draftitemid;

        return $question;
    }

    /**
     * Validate the submitted form data.
     *
     * @param array $fromform the submitted data
     * @param array $files the submitted files
     * @return array field name to error message map
     */
    public function validation($fromform, $files) {
        $errors = parent::validation($fromform, $files);

        $draftfiles = file_get_drafarea_files($fromform['baseimage']);
        if (empty($draftfiles->list)) {
            $errors['baseimage'] = get_string('errimage', 'qtype_dermoscopysim');
        }

        if (empty($fromform['mmperpx']) || $fromform['mmperpx'] <= 0) {
            $errors['mmperpx'] = get_string('errscale', 'qtype_dermoscopysim');
        }

        $lesion = json_decode($fromform['lesiondata'] ?? '', true);
        if (!is_array($lesion) || count($lesion) < 3) {
            $errors['baseimage'] = get_string('errlesion', 'qtype_dermoscopysim');
        }

        if (($fromform['marginmethod'] ?? '') === 'ideal') {
            $ideal = json_decode($fromform['idealmargindata'] ?? '', true);
            if (!is_array($ideal) || count($ideal) < 3) {
                $errors['marginmethod'] = get_string('errideal', 'qtype_dermoscopysim');
            }
        } else {
            $min = (float) ($fromform['marginmm'] ?? 0);
            $max = (float) ($fromform['marginmaxmm'] ?? 0);
            if ($min < 0 || $max <= $min) {
                $errors['marginmm'] = get_string('errmarginrange', 'qtype_dermoscopysim');
            }
        }

        $weight = (int) ($fromform['captureweight'] ?? -1);
        if ($weight < 0 || $weight > 100) {
            $errors['captureweight'] = get_string('errweight', 'qtype_dermoscopysim');
        }

        return $errors;
    }

    /**
     * Name the question type this form edits.
     *
     * @return string the question type name
     */
    public function qtype() {
        return 'dermoscopysim';
    }
}
