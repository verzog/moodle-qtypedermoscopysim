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
 * Authoring canvas for the question editing form.
 *
 * Lets the instructor load the uploaded clinical photograph, calibrate the
 * scale by drawing a line of known length (or typing millimetres per pixel
 * directly into the form field), trace the lesion boundary and optionally
 * draw the ideal excision margin. Results are written to hidden form
 * fields and validated server-side on save.
 *
 * @module     qtype_dermoscopysim/editform
 * @copyright  2026 Skin Cancer College Australasia
 */
define([], function() {
    'use strict';

    var MAX_WIDTH = 640;

    /**
     * Create an element with a class and optional text.
     *
     * @param {String} tag the tag name
     * @param {String} cls the class name
     * @param {String} [text] optional text content
     * @return {HTMLElement} the created element
     */
    var el = function(tag, cls, text) {
        var node = document.createElement(tag);
        node.className = cls;
        if (text) {
            node.textContent = text;
        }
        return node;
    };

    // Number of radial samples used to draw the acceptance band, matching the
    // server-side grader's ray count so the preview reflects real scoring.
    var RAY_SAMPLES = 72;

    /**
     * Read a numeric value from a named form field.
     *
     * @param {String} name the form field name
     * @return {Number} the parsed value, or NaN if absent/invalid
     */
    function fieldNumber(name) {
        var field = document.querySelector('[name="' + name + '"]');
        return field ? parseFloat(field.value) : NaN;
    }

    /**
     * Read the current value of a named select/text field as a string.
     *
     * @param {String} name the form field name
     * @return {String} the field value, or '' if absent
     */
    function fieldString(name) {
        var field = document.querySelector('[name="' + name + '"]');
        return field ? String(field.value) : '';
    }

    /**
     * Compute the area-weighted centroid of a polygon, falling back to the
     * vertex mean for degenerate shapes. Mirrors the server-side grader.
     *
     * @param {Array} points the polygon vertices as [x, y] pairs
     * @return {Number[]} the centroid as [x, y]
     */
    function centroid(points) {
        var n = points.length;
        var area = 0;
        var cx = 0;
        var cy = 0;
        for (var i = 0; i < n; i++) {
            var j = (i + 1) % n;
            var cross = (points[i][0] * points[j][1]) - (points[j][0] * points[i][1]);
            area += cross;
            cx += (points[i][0] + points[j][0]) * cross;
            cy += (points[i][1] + points[j][1]) * cross;
        }
        if (Math.abs(area) < 0.0001) {
            var sx = 0;
            var sy = 0;
            for (var k = 0; k < n; k++) {
                sx += points[k][0];
                sy += points[k][1];
            }
            return [sx / n, sy / n];
        }
        area *= 0.5;
        return [cx / (6 * area), cy / (6 * area)];
    }

    /**
     * Distance from a centre point to a polygon boundary along a ray, taking
     * the furthest intersection. Mirrors the server-side grader.
     *
     * @param {Array} points the polygon vertices as [x, y] pairs
     * @param {Number} cx the ray origin x
     * @param {Number} cy the ray origin y
     * @param {Number} angle the ray angle in radians
     * @return {Number|null} the distance in pixels, or null if there is no hit
     */
    function radiusAtAngle(points, cx, cy, angle) {
        var dx = Math.cos(angle);
        var dy = Math.sin(angle);
        var n = points.length;
        var best = null;
        for (var i = 0; i < n; i++) {
            var j = (i + 1) % n;
            var ex = points[j][0] - points[i][0];
            var ey = points[j][1] - points[i][1];
            var denom = (dx * ey) - (dy * ex);
            if (Math.abs(denom) < 0.0000001) {
                continue;
            }
            var ox = points[i][0] - cx;
            var oy = points[i][1] - cy;
            var t = ((ox * ey) - (oy * ex)) / denom;
            var u = ((ox * dy) - (oy * dx)) / denom;
            if (t > 0 && u >= 0 && u <= 1 && (best === null || t > best)) {
                best = t;
            }
        }
        return best;
    }

    /**
     * The authoring editor controller.
     *
     * @param {HTMLElement} container the container element
     * @param {Object} labels localised interface labels
     */
    var Editor = function(container, labels) {
        this.container = container;
        this.labels = labels;
        this.mode = null;
        this.calPoints = [];
        this.lesion = this.readField('lesiondata');
        this.ideal = this.readField('idealmargindata');
        this.image = null;
        this.build();
    };

    /**
     * Read and decode a hidden polygon field.
     *
     * @param {String} name the form field name
     * @return {Array} the decoded polygon points
     */
    Editor.prototype.readField = function(name) {
        var field = document.querySelector('input[name="' + name + '"]');
        if (!field || field.value === '') {
            return [];
        }
        try {
            var pts = JSON.parse(field.value);
            return Array.isArray(pts) ? pts : [];
        } catch (e) {
            return [];
        }
    };

    /**
     * Write a polygon into a hidden form field.
     *
     * @param {String} name the form field name
     * @param {Array} points the polygon points
     */
    Editor.prototype.writeField = function(name, points) {
        var field = document.querySelector('input[name="' + name + '"]');
        if (field) {
            field.value = points.length ? JSON.stringify(points) : '';
        }
    };

    /**
     * Build the toolbar and canvas.
     */
    Editor.prototype.build = function() {
        var self = this;
        this.container.appendChild(el('p', 'qtype_dermoscopysim-prompt', this.labels.editorintro));

        var bar = el('div', 'qtype_dermoscopysim-toolbar');
        this.loadBtn = this.button(bar, this.labels.loadimage);
        this.mmInput = document.createElement('input');
        this.mmInput.type = 'number';
        this.mmInput.step = 'any';
        this.mmInput.min = '0';
        this.mmInput.placeholder = this.labels.entermm;
        this.mmInput.className = 'qtype_dermoscopysim-mminput';
        bar.appendChild(this.mmInput);
        this.calBtn = this.button(bar, this.labels.calibratebyline);
        this.lesionBtn = this.button(bar, this.labels.drawlesion);
        this.idealBtn = this.button(bar, this.labels.drawideal);
        this.finishBtn = this.button(bar, this.labels.finishshape);
        this.clearBtn = this.button(bar, this.labels.clearshape);
        this.container.appendChild(bar);

        this.status = el('p', 'qtype_dermoscopysim-status');
        this.container.appendChild(this.status);

        this.canvas = document.createElement('canvas');
        this.canvas.className = 'qtype_dermoscopysim-editcanvas';
        this.canvas.style.display = 'none';
        this.container.appendChild(this.canvas);

        this.loadBtn.addEventListener('click', function() {
            self.loadImage();
        });
        this.calBtn.addEventListener('click', function() {
            self.setMode('calibrate');
        });
        this.lesionBtn.addEventListener('click', function() {
            self.setMode('lesion');
        });
        this.idealBtn.addEventListener('click', function() {
            self.setMode('ideal');
        });
        this.finishBtn.addEventListener('click', function() {
            self.setMode(null);
        });
        this.clearBtn.addEventListener('click', function() {
            self.clearCurrent();
        });
        this.canvas.addEventListener('click', function(e) {
            self.onClick(e);
        });

        // Redraw the acceptance band whenever a grading setting that shapes it
        // changes, so the preview always reflects the current configuration.
        ['marginmethod', 'mmperpx', 'marginmm', 'marginmaxmm'].forEach(function(name) {
            var field = document.querySelector('[name="' + name + '"]');
            if (field) {
                field.addEventListener('input', function() {
                    self.draw();
                });
                field.addEventListener('change', function() {
                    self.draw();
                });
            }
        });
    };

    /**
     * Show a status message, styled as neutral info or an error.
     *
     * @param {String} text the message to show
     * @param {Boolean} [isError] true to style the message as an error
     */
    Editor.prototype.setStatus = function(text, isError) {
        this.status.textContent = text;
        this.status.classList.toggle('is-error', !!isError);
    };

    /**
     * Create a toolbar button.
     *
     * @param {HTMLElement} parent the parent element
     * @param {String} text the button label
     * @return {HTMLButtonElement} the created button
     */
    Editor.prototype.button = function(parent, text) {
        var btn = el('button', 'btn btn-secondary', text);
        btn.type = 'button';
        parent.appendChild(btn);
        return btn;
    };

    /**
     * Find the uploaded draft image inside the filemanager and load it.
     */
    Editor.prototype.loadImage = function() {
        var self = this;
        var thumb = document.querySelector('.filemanager img[src*="draftfile.php"]');
        if (!thumb) {
            this.setStatus(this.labels.imagenotfound, true);
            return;
        }
        var url = thumb.src.split('?')[0];
        var img = new Image();
        img.addEventListener('load', function() {
            self.image = img;
            var scale = Math.min(1, MAX_WIDTH / img.naturalWidth);
            self.canvas.width = Math.round(img.naturalWidth * scale);
            self.canvas.height = Math.round(img.naturalHeight * scale);
            self.canvas.style.display = '';
            self.setStatus('', false);
            self.draw();
        });
        img.src = url;
    };

    /**
     * Switch the current drawing mode.
     *
     * @param {String|null} mode the new mode
     */
    Editor.prototype.setMode = function(mode) {
        this.mode = mode;
        if (mode === 'calibrate') {
            this.calPoints = [];
        }
        this.draw();
    };

    /**
     * Clear the polygon for the current mode, or the calibration line.
     */
    Editor.prototype.clearCurrent = function() {
        if (this.mode === 'lesion') {
            this.lesion = [];
            this.writeField('lesiondata', this.lesion);
        } else if (this.mode === 'ideal') {
            this.ideal = [];
            this.writeField('idealmargindata', this.ideal);
        } else {
            this.calPoints = [];
        }
        this.draw();
    };

    /**
     * Handle a click on the canvas according to the current mode.
     *
     * @param {MouseEvent} e the click event
     */
    Editor.prototype.onClick = function(e) {
        if (!this.image || !this.mode) {
            return;
        }
        var rect = this.canvas.getBoundingClientRect();
        var scale = this.image.naturalWidth / this.canvas.width;
        var nx = Math.round((e.clientX - rect.left) * scale * 10) / 10;
        var ny = Math.round((e.clientY - rect.top) * scale * 10) / 10;

        if (this.mode === 'calibrate') {
            this.calPoints.push([nx, ny]);
            if (this.calPoints.length === 2) {
                this.applyCalibration();
            }
        } else if (this.mode === 'lesion') {
            this.lesion.push([nx, ny]);
            this.writeField('lesiondata', this.lesion);
        } else if (this.mode === 'ideal') {
            this.ideal.push([nx, ny]);
            this.writeField('idealmargindata', this.ideal);
        }
        this.draw();
    };

    /**
     * Compute millimetres per pixel from the calibration line and length.
     */
    Editor.prototype.applyCalibration = function() {
        var mm = parseFloat(this.mmInput.value);
        var a = this.calPoints[0];
        var b = this.calPoints[1];
        var dist = Math.sqrt(
            (a[0] - b[0]) * (a[0] - b[0]) + (a[1] - b[1]) * (a[1] - b[1])
        );
        if (isFinite(mm) && mm > 0 && dist > 0) {
            var mmperpx = mm / dist;
            var field = document.querySelector('input[name="mmperpx"]');
            if (field) {
                field.value = mmperpx.toFixed(7);
            }
            this.setStatus(this.labels.scaleset + ' ' + mmperpx.toFixed(4) + ' mm/px', false);
        } else {
            this.setStatus(this.labels.scaleneedsmm, true);
        }
        this.mode = null;
        this.draw();
    };

    /**
     * Redraw the photograph and all overlays.
     */
    Editor.prototype.draw = function() {
        if (!this.image) {
            return;
        }
        var ctx = this.canvas.getContext('2d');
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);

        var scale = this.canvas.width / this.image.naturalWidth;
        this.drawAcceptanceBand(ctx, scale);
        this.drawPolygon(ctx, this.lesion, scale, '#e53935', this.labels.lesionlabel);
        this.drawPolygon(ctx, this.ideal, scale, '#00b0ff', this.labels.ideallabel);

        if (this.calPoints.length > 0) {
            ctx.strokeStyle = '#ffc400';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(this.calPoints[0][0] * scale, this.calPoints[0][1] * scale);
            if (this.calPoints.length > 1) {
                ctx.lineTo(this.calPoints[1][0] * scale, this.calPoints[1][1] * scale);
            }
            ctx.stroke();
        }
    };

    /**
     * Draw the excision-margin acceptance band implied by the current grading
     * settings, so the author can see exactly which clearance will score full
     * marks. Only shown for the distance and zones methods, which grade on a
     * fixed clearance band; the ideal method grades on deviation from the drawn
     * ideal polygon instead.
     *
     * @param {CanvasRenderingContext2D} ctx the canvas context
     * @param {Number} scale the natural-to-canvas scale factor
     */
    Editor.prototype.drawAcceptanceBand = function(ctx, scale) {
        var method = fieldString('marginmethod');
        if (method !== 'distance' && method !== 'zones') {
            return;
        }
        if (this.lesion.length < 3) {
            return;
        }
        var mmperpx = fieldNumber('mmperpx');
        var minmm = fieldNumber('marginmm');
        var maxmm = fieldNumber('marginmaxmm');
        if (!(mmperpx > 0) || !isFinite(minmm) || !isFinite(maxmm) || maxmm <= minmm) {
            return;
        }

        var minpx = minmm / mmperpx;
        var maxpx = maxmm / mmperpx;
        var c = centroid(this.lesion);
        var inner = [];
        var outer = [];
        for (var i = 0; i < RAY_SAMPLES; i++) {
            var angle = (i / RAY_SAMPLES) * 2 * Math.PI;
            var rl = radiusAtAngle(this.lesion, c[0], c[1], angle);
            if (rl === null) {
                return;
            }
            var dx = Math.cos(angle);
            var dy = Math.sin(angle);
            inner.push([(c[0] + dx * (rl + minpx)) * scale, (c[1] + dy * (rl + minpx)) * scale]);
            outer.push([(c[0] + dx * (rl + maxpx)) * scale, (c[1] + dy * (rl + maxpx)) * scale]);
        }

        // Fill the ring between the outer and inner boundaries (even-odd).
        ctx.save();
        ctx.beginPath();
        this.tracePath(ctx, outer);
        this.tracePath(ctx, inner);
        ctx.fillStyle = 'rgba(29, 158, 117, 0.22)';
        ctx.fill('evenodd');

        // Thin dashed edges for clarity.
        ctx.setLineDash([5, 4]);
        ctx.lineWidth = 1;
        ctx.strokeStyle = 'rgba(29, 158, 117, 0.9)';
        ctx.beginPath();
        this.tracePath(ctx, inner);
        ctx.stroke();
        ctx.beginPath();
        this.tracePath(ctx, outer);
        ctx.stroke();
        ctx.restore();

        // Label near the top of the band.
        ctx.fillStyle = 'rgba(15, 110, 80, 0.95)';
        ctx.font = '12px sans-serif';
        ctx.fillText(this.labels.acceptanceband, outer[0][0] + 6, outer[0][1] - 6);
    };

    /**
     * Add a closed polygon to the current path without stroking or filling it.
     *
     * @param {CanvasRenderingContext2D} ctx the canvas context
     * @param {Array} pts the polygon points in canvas coordinates
     */
    Editor.prototype.tracePath = function(ctx, pts) {
        for (var i = 0; i < pts.length; i++) {
            if (i === 0) {
                ctx.moveTo(pts[i][0], pts[i][1]);
            } else {
                ctx.lineTo(pts[i][0], pts[i][1]);
            }
        }
        ctx.closePath();
    };

    /**
     * Draw one labelled polygon overlay.
     *
     * @param {CanvasRenderingContext2D} ctx the canvas context
     * @param {Array} points the polygon points in natural coordinates
     * @param {Number} scale the natural-to-canvas scale factor
     * @param {String} colour the stroke colour
     * @param {String} label the overlay label
     */
    Editor.prototype.drawPolygon = function(ctx, points, scale, colour, label) {
        if (points.length === 0) {
            return;
        }
        ctx.strokeStyle = colour;
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (var i = 0; i < points.length; i++) {
            var x = points[i][0] * scale;
            var y = points[i][1] * scale;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        if (points.length >= 3) {
            ctx.closePath();
        }
        ctx.stroke();
        for (var k = 0; k < points.length; k++) {
            ctx.beginPath();
            ctx.arc(points[k][0] * scale, points[k][1] * scale, 4, 0, 2 * Math.PI);
            ctx.fillStyle = colour;
            ctx.fill();
        }
        ctx.fillStyle = colour;
        ctx.font = '12px sans-serif';
        ctx.fillText(label, points[0][0] * scale + 8, points[0][1] * scale - 8);
    };

    return {
        /**
         * Initialise the authoring editor inside the given container.
         *
         * @param {String} containerid the container element id
         * @param {Object} labels localised interface labels
         */
        init: function(containerid, labels) {
            var container = document.getElementById(containerid);
            if (container) {
                new Editor(container, labels);
            }
        },
    };
});
