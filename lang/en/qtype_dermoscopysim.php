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
 * English language strings for the dermoscopy simulator question type.
 *
 * @package    qtype_dermoscopysim
 * @copyright  2026 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['baseimage'] = 'Clinical photograph';
$string['baseimage_help'] = 'Upload a single high-resolution clinical photograph. After uploading, use the authoring tools below to calibrate the scale and trace the lesion boundary on the photograph.';
$string['calibratebyline'] = 'Calibrate by line';
$string['capture'] = 'Capture image';
$string['captureheader'] = 'Dermoscope and capture settings';
$string['capturetolerancemm'] = 'Full-mark centring tolerance (mm)';
$string['capturetolerancemm_help'] = 'A capture centred within this distance of the lesion centre earns full marks for the capture step. Marks then fall away linearly, reaching zero at the edge of the faceplate.';
$string['captureweight'] = 'Capture weighting (%)';
$string['captureweight_help'] = 'The percentage of the question grade awarded for the capture step. The remainder is awarded for the excision margin marking.';
$string['clearpoints'] = 'Clear all points';
$string['clearshape'] = 'Clear current shape';
$string['correctanswer'] = 'Correct margin';
$string['correctanswerheading'] = 'Correct answer';
$string['dermoscopicview'] = 'Dermoscopic view';
$string['drawideal'] = 'Draw ideal margin';
$string['drawlesion'] = 'Trace lesion boundary';
$string['editorintro'] = 'Authoring tools: load the uploaded photograph, then calibrate the scale (enter a known length in millimetres and click the two ends of that length on the photograph, or type millimetres per pixel directly into the scale field). Next, click around the lesion to trace its boundary. If using the ideal margin method, also draw the ideal excision margin.';
$string['entermm'] = 'Known length (mm)';
$string['errideal'] = 'The ideal margin method requires an ideal margin of at least three points drawn on the photograph.';
$string['errimage'] = 'A clinical photograph must be uploaded.';
$string['errlesion'] = 'The lesion boundary must be traced on the photograph with at least three points.';
$string['errmarginrange'] = 'The minimum clearance must be zero or more and less than the maximum clearance.';
$string['errscale'] = 'The scale must be calibrated: enter a value greater than zero, or use the calibrate-by-line tool.';
$string['errweight'] = 'The capture weighting must be between 0 and 100.';
$string['feedbackscores'] = 'Capture accuracy: {$a->capture}%. Margin accuracy: {$a->margin}%.';
$string['finishshape'] = 'Finish shape';
$string['ideallabel'] = 'Ideal margin';
$string['idealtolerancemm'] = 'Ideal margin tolerance (mm)';
$string['imageheader'] = 'Clinical photograph and lesion';
$string['imagenotfound'] = 'No uploaded photograph was found. Upload the photograph above first, then try again. If it still cannot be found, save the question and reopen it for editing.';
$string['lensdiametermm'] = 'Faceplate diameter (mm)';
$string['lesionlabel'] = 'Lesion';
$string['loadimage'] = 'Load photograph into editor';
$string['magnification'] = 'Magnification';
$string['marginheader'] = 'Excision margin assessment';
$string['marginmaxmm'] = 'Maximum clearance (mm)';
$string['marginmethod'] = 'Margin assessment method';
$string['marginmethod_help'] = 'Distance from lesion edge: the clearance between the student margin and the lesion edge is sampled all the way around, and the score is the proportion of samples inside the clearance band. Zones: full marks only if every sample is inside the clearance band, otherwise zero. Compare to ideal margin: the student margin is compared with the instructor-drawn ideal margin, with full marks when the mean deviation is within the tolerance.';
$string['marginmm'] = 'Minimum clearance (mm)';
$string['marginprompt'] = 'Mark the excision margin on your captured image: click to place points around the lesion, and drag points to adjust them.';
$string['methoddistance'] = 'Distance from lesion edge';
$string['methodideal'] = 'Compare to ideal margin';
$string['methodzones'] = 'Zones (pass or fail)';
$string['mmperpx'] = 'Scale (millimetres per pixel)';
$string['mmperpx_help'] = 'How many millimetres of skin one pixel of the photograph represents. Either type the value directly, or use the calibrate-by-line tool in the authoring canvas: enter a known length and click its two ends on the photograph.';
$string['notcaptured'] = 'No image has been captured.';
$string['pleasecapture'] = 'Position the dermoscope over the lesion and capture an image.';
$string['pleasemargin'] = 'Mark the excision margin with at least three points on your captured image.';
$string['pluginname'] = 'Dermoscopy simulator';
$string['pluginname_help'] = 'Students position a simulated dermoscope over a clinical photograph and capture a dermoscopic image, then mark excision margins on the captured image. Both steps are graded automatically.';
$string['pluginnameadding'] = 'Adding a dermoscopy simulator question';
$string['pluginnameediting'] = 'Editing a dermoscopy simulator question';
$string['pluginnamesummary'] = 'Students capture a dermoscopic image with a simulated dermoscope and mark excision margins, graded automatically.';
$string['positionprompt'] = 'Drag the dermoscope over the photograph. The viewfinder shows the magnified dermoscopic view. Capture when the lesion is properly centred.';
$string['privacy:metadata'] = 'The dermoscopy simulator question type plugin does not store any personal data. Student responses are stored by the core question subsystem.';
$string['responsesummary'] = 'Captured at ({$a->x}, {$a->y}) px; margin marked with {$a->count} points.';
$string['retake'] = 'Retake image';
$string['scalebar'] = '10 mm';
$string['studentanswer'] = 'Your margin';
$string['takepicture'] = 'Take picture';
$string['undopoint'] = 'Undo last point';
$string['zoomlabel'] = 'Zoom';
