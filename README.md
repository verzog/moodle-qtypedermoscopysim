# Dermoscopic Simulator Question Type (`qtype_dermoscopysim`)

An interactive Moodle question type that simulates dermoscopic examination. Students
position a virtual dermoscope over a clinical photograph, capture a dermoscopic view,
then mark excision margins on the captured image — all auto-graded server-side.

---

## Features

- Draggable dermoscope ring overlaid on a clinical photograph
- Circular viewfinder showing a magnified view with reticle crosshair and 10 mm scale bar
- Capture graded on centring accuracy (configurable tolerance)
- Three margin-grading methods: radial clearance band, zone coverage, or deviation from
  an instructor-defined ideal polygon
- Instructor configures scale via a drawn calibration line or direct mm-per-pixel entry
- Works within standard Moodle quizzes and the question bank

---

## Requirements

- Moodle 5.0 or later
- PHP 8.2 or later
- A modern browser with Pointer Events support (Chrome 55+, Firefox 59+, Safari 13+)

---

## Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to
   *Site administration > Plugins > Install plugins*.
2. Upload the ZIP file, confirm the installation, and follow the on-screen prompts.
3. If you have problems, see
   [Installing plugins](https://docs.moodle.org/en/Installing_plugins).

---

## Installing manually

1. Extract the plugin folder so the path reads:
   `<moodleroot>/question/type/dermoscopysim/` (Moodle 5.0)
   or `<moodleroot>/public/question/type/dermoscopysim/` (Moodle 5.1+ `public/` layout)
2. Log in as an admin and go to *Site administration > Notifications* to trigger the
   database upgrade.

---

## Authoring a question

1. Add a new *Dermoscopic Simulator* question in the question bank.
2. Upload the clinical photograph.
3. Open the *Load image* panel; the photo loads into the editor canvas.
4. Calibrate scale: either draw a line over a known-length feature and enter its real
   length in mm, or type the mm-per-pixel value directly.
5. Switch to *Draw lesion* mode and click to trace the lesion boundary (close the
   polygon with a final click near the first point).
6. If using the *Ideal margin* grading method, switch to *Draw ideal margin* and trace
   the target polygon.
7. Set grading parameters (lens diameter, capture tolerance, margin method and
   tolerances, capture weight) and save.

> **Tip:** If the photo does not appear in the editor after upload, save the question
> and reopen it — the filemanager thumbnail needs a round-trip to produce a URL the
> editor can load.

---

## Third-party libraries

None bundled. All JavaScript is written as Moodle AMD modules using only browser-native
APIs.

---

## Licence

Copyright © Skin Cancer College Australasia. All rights reserved.

This plugin is **proprietary** and is **not** released under the GNU General Public
Licence or any other open-source licence. It is developed for in-house use by Skin
Cancer College Australasia only. Unauthorised copying, distribution, modification, or
use of this software, in whole or in part, is strictly prohibited without the prior
written permission of Skin Cancer College Australasia.
