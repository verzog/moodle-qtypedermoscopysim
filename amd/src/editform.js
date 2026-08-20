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
 * Authoring canvas for the question editing form.
 *
 * Lets the instructor load the uploaded clinical photograph, calibrate the
 * scale by drawing a line of known length (or typing millimetres per pixel
 * directly into the form field), trace the lesion boundary and optionally
 * draw the ideal excision margin. Results are written to hidden form
 * fields and validated server-side on save.
 *
 * @module     qtype_dermoscopysim/editform
 * @copyright  © Skin Cancer College Australasia
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
            this.status.textContent = this.labels.imagenotfound;
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
            self.status.textContent = '';
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
            var field = document.querySelector('input[name="mmperpx"]');
            if (field) {
                field.value = (mm / dist).toFixed(7);
            }
        }
        this.mode = null;
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
