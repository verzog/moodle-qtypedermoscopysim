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
 * Renderer for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for dermoscopy simulator questions.
 *
 * The renderer emits the question text, the hidden response fields and a
 * container element which the simulator AMD module turns into the
 * interactive dermoscope and margin-marking canvases.
 *
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class qtype_dermoscopysim_renderer extends qtype_renderer {

    /**
     * Generate the area that contains the question text and controls.
     *
     * @param question_attempt $qa the question attempt to display
     * @param question_display_options $options controls what is displayed
     * @return string HTML fragment
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();

        $imageurl = $this->base_image_url($qa, $question);
        if ($imageurl === null) {
            return $this->output->notification(
                get_string('errimage', 'qtype_dermoscopysim'),
                'error'
            );
        }

        $result = html_writer::div(
            $question->format_questiontext($qa),
            'qtext'
        );

        $fields = [
            'capturex' => $qa->get_last_qt_var('capturex', ''),
            'capturey' => $qa->get_last_qt_var('capturey', ''),
            'margindata' => $qa->get_last_qt_var('margindata', ''),
        ];
        $inputids = [];
        foreach ($fields as $name => $value) {
            $inputids[$name] = $qa->get_qt_field_name($name) . '_input';
            $result .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $qa->get_qt_field_name($name),
                'id' => $inputids[$name],
                'value' => $value,
            ]);
        }

        $containerid = 'qtype_dermoscopysim_' . $qa->get_slot() . '_' . uniqid();
        $result .= html_writer::div('', 'qtype_dermoscopysim-sim', ['id' => $containerid]);

        $config = [
            'imageurl' => $imageurl,
            'mmperpx' => (float) $question->mmperpx,
            'lensdiametermm' => (float) $question->lensdiametermm,
            'magnification' => (float) $question->magnification,
            'readonly' => (bool) $options->readonly,
            'inputids' => $inputids,
            'strings' => [
                'clearpoints' => get_string('clearpoints', 'qtype_dermoscopysim'),
                'marginprompt' => get_string('marginprompt', 'qtype_dermoscopysim'),
                'positionprompt' => get_string('positionprompt', 'qtype_dermoscopysim'),
                'retake' => get_string('retake', 'qtype_dermoscopysim'),
                'takepicture' => get_string('takepicture', 'qtype_dermoscopysim'),
                'undopoint' => get_string('undopoint', 'qtype_dermoscopysim'),
                'zoomlabel' => get_string('zoomlabel', 'qtype_dermoscopysim'),
            ],
        ];
        $this->page->requires->js_call_amd(
            'qtype_dermoscopysim/simulator',
            'init',
            [$containerid, $config]
        );

        return $result;
    }

    /**
     * Generate the specific feedback shown after grading.
     *
     * @param question_attempt $qa the question attempt to display
     * @return string HTML fragment
     */
    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        if (empty($response)) {
            return '';
        }

        $capture = round($question->grade_capture($response) * 100);
        $margin = round($question->grade_margin($response) * 100);

        $a = new stdClass();
        $a->capture = $capture;
        $a->margin = $margin;
        return html_writer::div(
            get_string('feedbackscores', 'qtype_dermoscopysim', $a),
            'qtype_dermoscopysim-feedback'
        );
    }

    /**
     * Render the correct-answer comparison canvas.
     *
     * Moodle calls this method automatically when the question behaviour
     * settings say the correct answer should be shown (e.g. deferred
     * feedback after submission, interactive with multiple tries, etc.).
     * The canvas overlays the student margin, the correct margin and the
     * lesion boundary so the student can see exactly where they differed.
     *
     * @param question_attempt $qa the question attempt to display
     * @return string HTML fragment, or empty string when no image is stored
     */
    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        // Use the student's capture position if present.
        if (isset($response['capturex']) && is_numeric($response['capturex'])
                && isset($response['capturey']) && is_numeric($response['capturey'])) {
            $capturex = (float) $response['capturex'];
            $capturey = (float) $response['capturey'];
        } else {
            // Fall back to the lesion centroid so the canvas is still meaningful.
            $lesion = $question->decode_points($question->lesiondata);
            if (count($lesion) < 3) {
                return '';
            }
            [$capturex, $capturey] = qtype_dermoscopysim_question::centroid($lesion);
        }

        $imageurl = $this->base_image_url($qa, $question);
        if ($imageurl === null) {
            return '';
        }

        $correctresponse = $question->get_correct_response();
        $correctmargin = $correctresponse ? ($correctresponse['margindata'] ?? '') : '';
        $studentmargin = $response['margindata'] ?? '';

        $containerid = 'qtype_dermoscopysim_correct_' . $qa->get_slot() . '_' . uniqid();
        $heading = html_writer::tag(
            'h5',
            get_string('correctanswerheading', 'qtype_dermoscopysim'),
            ['class' => 'qtype_dermoscopysim-correct-heading']
        );
        $output = $heading . html_writer::div(
            '',
            'qtype_dermoscopysim-correctanswer',
            ['id' => $containerid]
        );

        $config = [
            'imageurl' => $imageurl,
            'mmperpx' => (float) $question->mmperpx,
            'lensdiametermm' => (float) $question->lensdiametermm,
            'capturex' => $capturex,
            'capturey' => $capturey,
            'studentmargin' => $studentmargin,
            'correctmargin' => $correctmargin,
            'lesiondata' => $question->lesiondata,
            'strings' => [
                'correctanswer' => get_string('correctanswer', 'qtype_dermoscopysim'),
                'lesionlabel' => get_string('lesionlabel', 'qtype_dermoscopysim'),
                'studentanswer' => get_string('studentanswer', 'qtype_dermoscopysim'),
            ],
        ];

        $this->page->requires->js_call_amd(
            'qtype_dermoscopysim/simulator',
            'initCorrectAnswer',
            [$containerid, $config]
        );

        return $output;
    }

    /**
     * Resolve the URL of the question's clinical photograph.
     *
     * @param question_attempt $qa the question attempt being rendered
     * @param question_definition $question the question definition
     * @return string|null the image URL, or null if no image is stored
     */
    protected function base_image_url(question_attempt $qa, question_definition $question) {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $question->contextid,
            'qtype_dermoscopysim',
            'baseimage',
            $question->id,
            'itemid, filepath, filename',
            false
        );
        if (empty($files)) {
            return null;
        }
        $file = reset($files);
        $placeholder = '@@PLUGINFILE@@/' . rawurlencode($file->get_filename());
        return $qa->rewrite_pluginfile_urls(
            $placeholder,
            'qtype_dermoscopysim',
            'baseimage',
            $question->id
        );
    }
}
