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
 * Interactive dermoscope simulator for the student attempt page.
 *
 * The clinical photograph is displayed at a minimum of 1000 px wide.
 * A circular lens canvas is overlaid on the photo showing the magnified
 * dermoscopic view inside itself. A zoom slider adjusts how much of the
 * faceplate area is visible without changing the lens circle size. A "Take
 * picture" button captures the view; phase two lets the student mark
 * excision margins on the captured image.
 *
 * A separate initCorrectAnswer() export draws a comparison canvas for the
 * question engine's correct_response() display (shown based on quiz
 * behaviour settings).
 *
 * @module     qtype_dermoscopysim/simulator
 * @copyright  © Skin Cancer College Australasia
 */
define([], function() {
    'use strict';

    var LENS_SIZE = 320;
    var MARGIN_SIZE = 480;
    var HANDLE_RADIUS = 7;

    // Width of the dermoscope bezel ring drawn inside the lens canvas.
    // At 320 px this gives a 42 px wide ring that closely matches the
    // proportions of a real hand-held dermatoscope.
    var RING_WIDTH = 42;

    // -----------------------------------------------------------------
    // Utilities
    // -----------------------------------------------------------------

    /**
     * Create an element with a class and optional text content.
     *
     * @param {String} tag the tag name
     * @param {String} cls the CSS class
     * @param {String} [text] optional text content
     * @return {HTMLElement}
     */
    function el(tag, cls, text) {
        var node = document.createElement(tag);
        node.className = cls;
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    /**
     * Decode a JSON polygon string into an array of [x, y] pairs.
     *
     * @param {String|null} json the JSON string
     * @return {Array} list of [x, y] pairs; empty on failure
     */
    function parsePoints(json) {
        if (!json) {
            return [];
        }
        try {
            var pts = JSON.parse(json);
            return Array.isArray(pts) ? pts.filter(function(p) {
                return Array.isArray(p) && p.length >= 2
                    && isFinite(p[0]) && isFinite(p[1]);
            }) : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Choose a scale-bar length rounded to a nice millimetre value.
     *
     * @param {Number} rawMm the ideal bar length in millimetres
     * @return {Number} the rounded value
     */
    function niceBarMm(rawMm) {
        var vals = [1, 2, 5, 10, 20];
        for (var i = 0; i < vals.length; i++) {
            if (vals[i] >= rawMm * 0.6) {
                return vals[i];
            }
        }
        return 20;
    }

    // -----------------------------------------------------------------
    // Shared canvas drawing helpers
    // -----------------------------------------------------------------

    /**
     * Draw the magnified dermoscopic view into a canvas context.
     *
     * @param {CanvasRenderingContext2D} ctx the context to draw into
     * @param {HTMLImageElement} img the clinical photograph
     * @param {Number} cx the lens centre x in natural image pixels
     * @param {Number} cy the lens centre y in natural image pixels
     * @param {Number} faceplatePx the faceplate diameter in natural pixels
     * @param {Number} zoomLevel the current zoom multiplier
     * @param {Number} size the canvas size in display pixels
     * @param {Number} [clipRadius] radius of the circular clip; defaults to size / 2 - 2
     */
    function drawDermoscopicView(ctx, img, cx, cy, faceplatePx, zoomLevel, size, clipRadius) {
        var r = size / 2;
        var cr = (clipRadius !== undefined) ? clipRadius : r - 2;
        var srcD = faceplatePx / zoomLevel;
        ctx.clearRect(0, 0, size, size);
        ctx.save();
        ctx.beginPath();
        ctx.arc(r, r, cr, 0, 2 * Math.PI);
        ctx.clip();
        ctx.drawImage(img, cx - srcD / 2, cy - srcD / 2, srcD, srcD, 0, 0, size, size);
        ctx.restore();
    }

    /**
     * Draw the dermoscope ring border inside a canvas.
     *
     * @param {CanvasRenderingContext2D} ctx
     * @param {Number} size the canvas size
     * @param {Boolean} locked true when the capture has been taken
     */
    function drawRing(ctx, size, locked) {
        var r = size / 2;
        ctx.beginPath();
        ctx.arc(r, r, r - 1, 0, 2 * Math.PI);
        ctx.strokeStyle = locked ? '#888' : '#1d9e75';
        ctx.lineWidth = 4;
        ctx.stroke();
    }

    /**
     * Draw the reticle crosshair and adaptive scale bar.
     *
     * The bar length is chosen so it occupies roughly 60 display pixels,
     * rounded to a nice millimetre value.
     *
     * @param {CanvasRenderingContext2D} ctx
     * @param {Number} size the canvas size
     * @param {Number} faceplateMmVisible how many mm are visible in the lens
     * @param {Number} [innerR] inner radius the bar sits within; defaults to size / 2 - 2
     */
    function drawReticle(ctx, size, faceplateMmVisible, innerR) {
        var r = size / 2;
        var ir = (innerR !== undefined) ? innerR : r - 2;
        var pxPerMm = size / faceplateMmVisible;
        var barMm = niceBarMm(60 / pxPerMm);
        var barLen = barMm * pxPerMm;
        // Position bar near the bottom of the visible inner circle.
        var barY = r + ir - 22;
        var barX = r - barLen / 2;

        // Crosshair.
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.7)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(r - 12, r);
        ctx.lineTo(r + 12, r);
        ctx.moveTo(r, r - 12);
        ctx.lineTo(r, r + 12);
        ctx.stroke();

        // Scale bar.
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(barX, barY);
        ctx.lineTo(barX + barLen, barY);
        ctx.stroke();

        // End ticks.
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(barX, barY - 5);
        ctx.lineTo(barX, barY);
        ctx.moveTo(barX + barLen, barY - 5);
        ctx.lineTo(barX + barLen, barY);
        ctx.stroke();

        // Label.
        ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(barMm + ' mm', r, barY + 14);
    }

    /**
     * Draw a polygon (already in canvas coordinates) with optional fill.
     *
     * @param {CanvasRenderingContext2D} ctx
     * @param {Array} points list of [x, y] canvas-coordinate pairs
     * @param {String} stroke stroke colour
     * @param {String|null} fill fill colour, or null for no fill
     * @param {Number} [lineWidth=2] stroke width
     */
    function drawPolygon(ctx, points, stroke, fill, lineWidth) {
        if (points.length < 2) {
            return;
        }
        ctx.beginPath();
        for (var i = 0; i < points.length; i++) {
            if (i === 0) {
                ctx.moveTo(points[i][0], points[i][1]);
            } else {
                ctx.lineTo(points[i][0], points[i][1]);
            }
        }
        if (points.length >= 3) {
            ctx.closePath();
        }
        if (fill) {
            ctx.fillStyle = fill;
            ctx.fill();
        }
        ctx.strokeStyle = stroke;
        ctx.lineWidth = lineWidth || 2;
        ctx.stroke();
    }

    /**
     * Draw a realistic dermoscope bezel: dark gunmetal annular ring with
     * a chrome inner edge and a ring of illuminated LED dots.
     *
     * Applied only to the interactive lens canvas; the margin and
     * correct-answer canvases keep the simpler ring for legibility.
     *
     * @param {CanvasRenderingContext2D} ctx
     * @param {Number} size the canvas size (assumes square)
     * @param {Boolean} locked true after the student has captured
     */
    function drawDermoscopeBezel(ctx, size, locked) {
        var r = size / 2;
        var outerR = r - 1;
        var innerR = r - RING_WIDTH;
        // LED ring sits roughly halfway through the bezel, closer to inner edge.
        var ledR = r - RING_WIDTH * 0.52;
        var ledBodyR = Math.max(4, Math.round(size * 0.028)); // ~9 px at 320
        var ledCount = 8;

        // --- Annular bezel fill (gunmetal with subtle diagonal gradient) ---
        var grd = ctx.createLinearGradient(r - outerR, r - outerR, r + outerR, r + outerR);
        if (locked) {
            grd.addColorStop(0, '#1c1c1e');
            grd.addColorStop(0.5, '#191919');
            grd.addColorStop(1, '#141415');
        } else {
            grd.addColorStop(0, '#2e2e33');
            grd.addColorStop(0.5, '#252527');
            grd.addColorStop(1, '#1b1b1e');
        }

        // Clip to the ring shape (outer circle minus inner hole) then fill.
        ctx.save();
        ctx.beginPath();
        ctx.arc(r, r, outerR, 0, 2 * Math.PI);
        ctx.arc(r, r, innerR, 0, 2 * Math.PI, true);
        ctx.clip();
        ctx.fillStyle = grd;
        ctx.fillRect(0, 0, size, size);
        ctx.restore();

        // Outer edge — very dark stroke.
        ctx.beginPath();
        ctx.arc(r, r, outerR, 0, 2 * Math.PI);
        ctx.strokeStyle = '#080809';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Inner chrome edge — lighter stroke to simulate the lens mount.
        ctx.beginPath();
        ctx.arc(r, r, innerR, 0, 2 * Math.PI);
        ctx.strokeStyle = locked ? '#383840' : '#55555e';
        ctx.lineWidth = 2.5;
        ctx.stroke();

        // Very thin bright highlight on the inner edge top arc.
        ctx.beginPath();
        ctx.arc(r, r, innerR + 1, Math.PI * 1.1, Math.PI * 1.9);
        ctx.strokeStyle = locked ? 'rgba(100,100,110,0.4)' : 'rgba(160,160,180,0.5)';
        ctx.lineWidth = 1;
        ctx.stroke();

        // --- LED dots ---
        for (var i = 0; i < ledCount; i++) {
            var angle = (i / ledCount) * 2 * Math.PI - Math.PI / 2;
            var lx = r + ledR * Math.cos(angle);
            var ly = r + ledR * Math.sin(angle);

            if (!locked) {
                // Warm glow halo behind each LED.
                var glow = ctx.createRadialGradient(lx, ly, 0, lx, ly, ledBodyR * 2.8);
                glow.addColorStop(0, 'rgba(255, 242, 160, 0.65)');
                glow.addColorStop(0.5, 'rgba(255, 230, 100, 0.2)');
                glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
                ctx.beginPath();
                ctx.arc(lx, ly, ledBodyR * 2.8, 0, 2 * Math.PI);
                ctx.fillStyle = glow;
                ctx.fill();
            }

            // LED body.
            ctx.beginPath();
            ctx.arc(lx, ly, ledBodyR, 0, 2 * Math.PI);
            ctx.fillStyle = locked ? '#484840' : '#fffce4';
            ctx.fill();

            // LED bezel ring.
            ctx.beginPath();
            ctx.arc(lx, ly, ledBodyR, 0, 2 * Math.PI);
            ctx.strokeStyle = locked ? '#303030' : '#c8b860';
            ctx.lineWidth = 0.8;
            ctx.stroke();

            // Specular highlight (top-left of each LED).
            if (!locked) {
                ctx.beginPath();
                ctx.arc(
                    lx - ledBodyR * 0.28,
                    ly - ledBodyR * 0.28,
                    ledBodyR * 0.38,
                    0, 2 * Math.PI
                );
                ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                ctx.fill();
            }
        }
    }

    // -----------------------------------------------------------------
    // Simulator — student UI
    // -----------------------------------------------------------------

    /**
     * Controller for one question's interactive dermoscope simulator.
     *
     * @param {HTMLElement} container the question container element
     * @param {Object} config configuration from the PHP renderer
     */
    var Simulator = function(container, config) {
        this.container = container;
        this.config = config;
        this.zoomLevel = 1.0;
        this.faceplatePx = config.lensdiametermm / config.mmperpx;
        this.lensX = 0;
        this.lensY = 0;
        this.captured = false;
        this.marginPoints = [];
        this.dragPointIndex = -1;
        this.dragMoved = false;
        this.image = new Image();
        this.image.addEventListener('load', this.build.bind(this));
        this.image.src = config.imageurl;
    };

    /**
     * Build the interface once the image has loaded.
     */
    Simulator.prototype.build = function() {
        var self = this;
        var cfg = this.config;

        this.lensX = this.image.naturalWidth / 2;
        this.lensY = this.image.naturalHeight / 2;

        // Prompt.
        this.prompt = el('p', 'qtype_dermoscopysim-prompt', cfg.strings.positionprompt);
        this.container.appendChild(this.prompt);

        // Scrollable photo container.
        this.photoScroll = el('div', 'qtype_dermoscopysim-photoscroll');
        this.photoWrap = el('div', 'qtype_dermoscopysim-photo');

        this.photoImg = document.createElement('img');
        this.photoImg.src = cfg.imageurl;
        this.photoImg.alt = '';
        this.photoImg.draggable = false;
        this.photoWrap.appendChild(this.photoImg);

        // Lens canvas — overlaid on photo, invisible to pointer events.
        this.lensCanvas = document.createElement('canvas');
        this.lensCanvas.width = LENS_SIZE;
        this.lensCanvas.height = LENS_SIZE;
        this.lensCanvas.className = 'qtype_dermoscopysim-lens-canvas';
        this.photoWrap.appendChild(this.lensCanvas);

        this.photoScroll.appendChild(this.photoWrap);
        this.container.appendChild(this.photoScroll);

        // Controls row: zoom slider + take-picture button.
        var controls = el('div', 'qtype_dermoscopysim-controls');

        var zoomGroup = el('div', 'qtype_dermoscopysim-zoomgroup');
        var zLabel = el('label', 'qtype_dermoscopysim-zoomlabel', cfg.strings.zoomlabel + ':');
        this.zoomSlider = document.createElement('input');
        this.zoomSlider.type = 'range';
        this.zoomSlider.min = '0.5';
        this.zoomSlider.max = '4.0';
        this.zoomSlider.step = '0.1';
        this.zoomSlider.value = '1.0';
        this.zoomSlider.className = 'qtype_dermoscopysim-zoomslider';
        this.zoomValue = el('span', 'qtype_dermoscopysim-zoomvalue', '1.0\u00d7');
        zoomGroup.appendChild(zLabel);
        zoomGroup.appendChild(this.zoomSlider);
        zoomGroup.appendChild(this.zoomValue);
        controls.appendChild(zoomGroup);

        this.captureBtn = el('button', 'btn btn-primary qtype_dermoscopysim-capture', cfg.strings.takepicture);
        this.captureBtn.type = 'button';
        controls.appendChild(this.captureBtn);
        this.container.appendChild(controls);

        // Margin section (hidden until a capture exists).
        this.marginWrap = el('div', 'qtype_dermoscopysim-marginwrap');
        this.marginWrap.appendChild(el('p', 'qtype_dermoscopysim-prompt', cfg.strings.marginprompt));

        this.marginCanvas = document.createElement('canvas');
        this.marginCanvas.width = MARGIN_SIZE;
        this.marginCanvas.height = MARGIN_SIZE;
        this.marginCanvas.className = 'qtype_dermoscopysim-margincanvas';
        this.marginWrap.appendChild(this.marginCanvas);

        var toolbar = el('div', 'qtype_dermoscopysim-toolbar');
        this.undoBtn = el('button', 'btn btn-secondary', cfg.strings.undopoint);
        this.undoBtn.type = 'button';
        this.clearBtn = el('button', 'btn btn-secondary', cfg.strings.clearpoints);
        this.clearBtn.type = 'button';
        this.retakeBtn = el('button', 'btn btn-secondary', cfg.strings.retake);
        this.retakeBtn.type = 'button';
        toolbar.appendChild(this.undoBtn);
        toolbar.appendChild(this.clearBtn);
        toolbar.appendChild(this.retakeBtn);
        this.marginWrap.appendChild(toolbar);
        this.marginWrap.style.display = 'none';
        this.container.appendChild(this.marginWrap);

        this.restoreState();

        if (!cfg.readonly) {
            this.bindEvents();
        } else {
            this.captureBtn.style.display = 'none';
            this.undoBtn.style.display = 'none';
            this.clearBtn.style.display = 'none';
            this.retakeBtn.style.display = 'none';
        }

        // Defer first layout until the img element has rendered dimensions.
        var doLayout = function() {
            self.layoutLens();
            self.drawLens();
            self.drawMargin();
        };
        if (this.photoImg.complete && this.photoImg.clientWidth > 0) {
            doLayout();
        } else {
            this.photoImg.addEventListener('load', function() {
                requestAnimationFrame(doLayout);
            });
            requestAnimationFrame(doLayout);
        }

        // Re-layout on window resize.
        window.addEventListener('resize', function() {
            self.layoutLens();
        });
    };

    /**
     * Restore any saved response from the hidden form fields.
     */
    Simulator.prototype.restoreState = function() {
        var ids = this.config.inputids;
        var x = parseFloat(document.getElementById(ids.capturex).value);
        var y = parseFloat(document.getElementById(ids.capturey).value);
        if (isFinite(x) && isFinite(y)) {
            this.lensX = x;
            this.lensY = y;
            this.captured = true;
        }
        this.marginPoints = parsePoints(document.getElementById(ids.margindata).value);
        this.updatePhase();
    };

    /**
     * Show or hide the margin section based on capture state.
     */
    Simulator.prototype.updatePhase = function() {
        this.marginWrap.style.display = this.captured ? '' : 'none';
        this.captureBtn.disabled = this.captured;
    };

    /**
     * Attach all interactive event listeners.
     */
    Simulator.prototype.bindEvents = function() {
        var self = this;
        var dragging = false;

        this.photoWrap.addEventListener('pointerdown', function(e) {
            if (self.captured) {
                return;
            }
            dragging = true;
            self.photoWrap.setPointerCapture(e.pointerId);
            self.moveLensTo(e);
        });
        this.photoWrap.addEventListener('pointermove', function(e) {
            if (dragging && !self.captured) {
                self.moveLensTo(e);
            }
        });
        this.photoWrap.addEventListener('pointerup', function() {
            dragging = false;
        });

        this.captureBtn.addEventListener('click', function() {
            self.captured = true;
            self.saveCapture();
            self.updatePhase();
            self.drawMargin();
        });
        this.retakeBtn.addEventListener('click', function() {
            self.captured = false;
            self.marginPoints = [];
            self.saveCapture();
            self.saveMargin();
            self.updatePhase();
            self.drawLens();
        });
        this.undoBtn.addEventListener('click', function() {
            self.marginPoints.pop();
            self.saveMargin();
            self.drawMargin();
        });
        this.clearBtn.addEventListener('click', function() {
            self.marginPoints = [];
            self.saveMargin();
            self.drawMargin();
        });

        this.zoomSlider.addEventListener('input', function() {
            self.zoomLevel = parseFloat(self.zoomSlider.value);
            self.zoomValue.textContent = self.zoomLevel.toFixed(1) + '\u00d7';
            self.drawLens();
            // Margin canvas redraws but margin points remain in natural px, so they stay valid.
            self.drawMargin();
        });

        this.marginCanvas.addEventListener('pointerdown', function(e) {
            self.marginPointerDown(e);
        });
        this.marginCanvas.addEventListener('pointermove', function(e) {
            self.marginPointerMove(e);
        });
        this.marginCanvas.addEventListener('pointerup', function(e) {
            self.marginPointerUp(e);
        });
    };

    /**
     * Return the display scale factor (natural px → CSS px) for the photo.
     *
     * @return {Number}
     */
    Simulator.prototype.getDisplayScale = function() {
        if (!this.photoImg || !this.photoImg.naturalWidth) {
            return 1;
        }
        return this.photoImg.clientWidth / this.photoImg.naturalWidth;
    };

    /**
     * Move the lens centre to the pointer's photo position and redraw.
     *
     * @param {PointerEvent} e
     */
    Simulator.prototype.moveLensTo = function(e) {
        var rect = this.photoImg.getBoundingClientRect();
        var scale = this.getDisplayScale();
        var x = (e.clientX - rect.left) / scale;
        var y = (e.clientY - rect.top) / scale;
        this.lensX = Math.max(0, Math.min(this.image.naturalWidth, x));
        this.lensY = Math.max(0, Math.min(this.image.naturalHeight, y));
        this.layoutLens();
        this.drawLens();
    };

    /**
     * Position the lens canvas element over the photo.
     */
    Simulator.prototype.layoutLens = function() {
        var scale = this.getDisplayScale();
        this.lensCanvas.style.left = Math.round(this.lensX * scale - LENS_SIZE / 2) + 'px';
        this.lensCanvas.style.top = Math.round(this.lensY * scale - LENS_SIZE / 2) + 'px';
    };

    /**
     * Redraw the lens canvas with the current position and zoom.
     */
    Simulator.prototype.drawLens = function() {
        var ctx = this.lensCanvas.getContext('2d');
        var innerR = LENS_SIZE / 2 - RING_WIDTH;
        // Clip the image to the inner circle so the bezel ring can overlay cleanly.
        drawDermoscopicView(
            ctx, this.image, this.lensX, this.lensY,
            this.faceplatePx, this.zoomLevel, LENS_SIZE, innerR
        );
        // Draw the gunmetal bezel with LEDs on top.
        drawDermoscopeBezel(ctx, LENS_SIZE, this.captured);
        // Draw reticle inside the inner circle.
        drawReticle(ctx, LENS_SIZE, this.config.lensdiametermm / this.zoomLevel, innerR);
    };

    // ----- Margin canvas helpers -----

    /**
     * Compute the origin and scale for converting between natural image and
     * margin canvas coordinates at the current capture position and zoom.
     *
     * @return {{ox: Number, oy: Number, cs: Number}}
     */
    Simulator.prototype.marginOrigin = function() {
        var srcD = this.faceplatePx / this.zoomLevel;
        return {
            ox: this.lensX - srcD / 2,
            oy: this.lensY - srcD / 2,
            cs: MARGIN_SIZE / srcD
        };
    };

    /**
     * Convert a margin canvas position to natural image coordinates.
     *
     * @param {Number} cx canvas x
     * @param {Number} cy canvas y
     * @return {Number[]} [naturalX, naturalY]
     */
    Simulator.prototype.toNatural = function(cx, cy) {
        var m = this.marginOrigin();
        return [(cx / m.cs) + m.ox, (cy / m.cs) + m.oy];
    };

    /**
     * Convert a natural image coordinate to margin canvas coordinates.
     *
     * @param {Number} nx natural x
     * @param {Number} ny natural y
     * @return {Number[]} [canvasX, canvasY]
     */
    Simulator.prototype.toCanvas = function(nx, ny) {
        var m = this.marginOrigin();
        return [(nx - m.ox) * m.cs, (ny - m.oy) * m.cs];
    };

    /**
     * Get the pointer position in margin canvas coordinate space.
     *
     * @param {PointerEvent} e
     * @return {Number[]} [x, y]
     */
    Simulator.prototype.canvasPos = function(e) {
        var rect = this.marginCanvas.getBoundingClientRect();
        return [
            (e.clientX - rect.left) * (this.marginCanvas.width / rect.width),
            (e.clientY - rect.top) * (this.marginCanvas.height / rect.height)
        ];
    };

    /**
     * Find the margin handle near the given canvas coordinates, or -1.
     *
     * @param {Number} cx canvas x
     * @param {Number} cy canvas y
     * @return {Number} point index, or -1
     */
    Simulator.prototype.findHandle = function(cx, cy) {
        for (var i = 0; i < this.marginPoints.length; i++) {
            var c = this.toCanvas(this.marginPoints[i][0], this.marginPoints[i][1]);
            var dx = c[0] - cx;
            var dy = c[1] - cy;
            if (Math.sqrt(dx * dx + dy * dy) <= HANDLE_RADIUS + 4) {
                return i;
            }
        }
        return -1;
    };

    /**
     * Redraw the margin canvas with the captured view and current polygon.
     */
    Simulator.prototype.drawMargin = function() {
        if (!this.captured) {
            return;
        }
        var ctx = this.marginCanvas.getContext('2d');
        drawDermoscopicView(ctx, this.image, this.lensX, this.lensY, this.faceplatePx, this.zoomLevel, MARGIN_SIZE);
        drawRing(ctx, MARGIN_SIZE, true);
        drawReticle(ctx, MARGIN_SIZE, this.config.lensdiametermm / this.zoomLevel);

        if (this.marginPoints.length === 0) {
            return;
        }

        var self = this;
        var canvasPoints = this.marginPoints.map(function(p) {
            return self.toCanvas(p[0], p[1]);
        });

        drawPolygon(ctx, canvasPoints, '#2196f3', 'rgba(33, 150, 243, 0.15)', 2);

        canvasPoints.forEach(function(c) {
            ctx.beginPath();
            ctx.arc(c[0], c[1], 5, 0, 2 * Math.PI);
            ctx.fillStyle = '#2196f3';
            ctx.fill();
        });
    };

    Simulator.prototype.marginPointerDown = function(e) {
        var pos = this.canvasPos(e);
        this.dragPointIndex = this.findHandle(pos[0], pos[1]);
        this.dragMoved = false;
        this.marginCanvas.setPointerCapture(e.pointerId);
    };

    Simulator.prototype.marginPointerMove = function(e) {
        if (this.dragPointIndex < 0) {
            return;
        }
        this.dragMoved = true;
        var pos = this.canvasPos(e);
        this.marginPoints[this.dragPointIndex] = this.toNatural(pos[0], pos[1]);
        this.drawMargin();
    };

    Simulator.prototype.marginPointerUp = function(e) {
        var pos = this.canvasPos(e);
        if (this.dragPointIndex < 0 && !this.dragMoved) {
            var nat = this.toNatural(pos[0], pos[1]);
            this.marginPoints.push([
                Math.round(nat[0] * 10) / 10,
                Math.round(nat[1] * 10) / 10
            ]);
        }
        this.dragPointIndex = -1;
        this.dragMoved = false;
        this.saveMargin();
        this.drawMargin();
    };

    Simulator.prototype.saveCapture = function() {
        var ids = this.config.inputids;
        if (this.captured) {
            document.getElementById(ids.capturex).value = Math.round(this.lensX * 10) / 10;
            document.getElementById(ids.capturey).value = Math.round(this.lensY * 10) / 10;
        } else {
            document.getElementById(ids.capturex).value = '';
            document.getElementById(ids.capturey).value = '';
        }
    };

    Simulator.prototype.saveMargin = function() {
        var ids = this.config.inputids;
        document.getElementById(ids.margindata).value =
            this.marginPoints.length > 0 ? JSON.stringify(this.marginPoints) : '';
    };

    // -----------------------------------------------------------------
    // Correct-answer comparison display
    // -----------------------------------------------------------------

    /**
     * Draw a comparison canvas showing the student margin versus the
     * correct margin at the student's capture position.
     *
     * Called by the PHP correct_response() renderer method; triggered
     * automatically by Moodle based on question behaviour settings.
     *
     * @param {String} containerid id of the container element
     * @param {Object} config configuration from the PHP renderer
     */
    function initCorrectAnswer(containerid, config) {
        var container = document.getElementById(containerid);
        if (!container) {
            return;
        }

        var img = new Image();
        img.addEventListener('load', function() {
            var size = MARGIN_SIZE;
            var faceplatePx = config.lensdiametermm / config.mmperpx;

            // Use the student's capture position, or fall back to centre.
            var cx = isFinite(parseFloat(config.capturex))
                ? parseFloat(config.capturex)
                : img.naturalWidth / 2;
            var cy = isFinite(parseFloat(config.capturey))
                ? parseFloat(config.capturey)
                : img.naturalHeight / 2;

            // Build the canvas.
            var canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            canvas.className = 'qtype_dermoscopysim-margincanvas';
            container.appendChild(canvas);

            var ctx = canvas.getContext('2d');

            // Dermoscopic view at zoom 1.0 (full faceplate).
            drawDermoscopicView(ctx, img, cx, cy, faceplatePx, 1.0, size);
            drawRing(ctx, size, true);
            drawReticle(ctx, size, config.lensdiametermm);

            // Coordinate converter: natural image px → canvas px.
            var srcD = faceplatePx;
            var cs = size / srcD;
            var ox = cx - srcD / 2;
            var oy = cy - srcD / 2;

            /**
             * Convert natural image points to canvas coordinates.
             *
             * @param {Array} pts list of [x, y] natural-image pairs
             * @return {Array} the points in canvas coordinates
             */
            function tc(pts) {
                return pts.map(function(p) {
                    return [(p[0] - ox) * cs, (p[1] - oy) * cs];
                });
            }

            // Lesion boundary (red, semi-transparent).
            var lesion = parsePoints(config.lesiondata);
            if (lesion.length >= 3) {
                drawPolygon(ctx, tc(lesion), '#e53935', 'rgba(229, 57, 53, 0.25)', 1.5);
            }

            // Student margin (blue).
            var student = parsePoints(config.studentmargin);
            if (student.length >= 3) {
                drawPolygon(ctx, tc(student), '#2196f3', 'rgba(33, 150, 243, 0.15)', 2);
            }

            // Correct margin (teal).
            var correct = parsePoints(config.correctmargin);
            if (correct.length >= 3) {
                drawPolygon(ctx, tc(correct), '#1d9e75', 'rgba(29, 158, 117, 0.2)', 2.5);
            }

            // Colour legend.
            var legend = document.createElement('div');
            legend.className = 'qtype_dermoscopysim-legend';
            [
                ['#e53935', config.strings.lesionlabel],
                ['#2196f3', config.strings.studentanswer],
                ['#1d9e75', config.strings.correctanswer]
            ].forEach(function(item) {
                var row = document.createElement('div');
                row.className = 'qtype_dermoscopysim-legend-item';
                var sw = document.createElement('span');
                sw.className = 'qtype_dermoscopysim-legend-swatch';
                sw.style.setProperty('background', item[0]);
                row.appendChild(sw);
                row.appendChild(document.createTextNode('\u00a0' + item[1]));
                legend.appendChild(row);
            });
            container.appendChild(legend);
        });
        img.src = config.imageurl;
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------

    return {
        /**
         * Initialise the interactive simulator for a student attempt.
         *
         * @param {String} containerid the container element id
         * @param {Object} config the configuration from the PHP renderer
         */
        init: function(containerid, config) {
            var container = document.getElementById(containerid);
            if (container) {
                new Simulator(container, config);
            }
        },

        /**
         * Render the correct-answer comparison canvas.
         *
         * Called by the PHP correct_response() method; Moodle decides
         * whether to invoke this based on quiz behaviour settings.
         *
         * @param {String} containerid the container element id
         * @param {Object} config the configuration from the PHP renderer
         */
        initCorrectAnswer: function(containerid, config) {
            initCorrectAnswer(containerid, config);
        }
    };
});
