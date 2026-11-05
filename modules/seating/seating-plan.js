(function () {
    var LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÜ-+/&*<>@€,.;!?abcdefghijklmnopqrstuvwxyzäöü1234567890[]';
    var K = '#000000';
    var B = '#0c00ff';
    var L = '#8d89f1';
    var Y = '#cac861';

    function thick(x1, y1, x2, y2, color) {
        return [x1, y1, x2, y2, color || K, 2.4];
    }

    function thin(x1, y1, x2, y2, color) {
        return [x1, y1, x2, y2, color || K, 1];
    }

    function buildLines() {
        var lines = {
            10: [thick(7, 0, 7, 14)],
            11: [thick(0, 7, 14, 7)],
            12: [thick(0, 7, 14, 7), thick(7, 0, 7, 7)],
            13: [thick(7, 0, 7, 14), thick(0, 7, 7, 7)],
            14: [thick(0, 7, 14, 7), thick(7, 7, 7, 14)],
            15: [thick(7, 0, 7, 14), thick(7, 7, 14, 7)],
            16: [thick(7, 0, 7, 14), thick(0, 7, 14, 7)],
            17: [thick(0, 7, 9, 7), thick(7, 0, 7, 9)],
            18: [thick(0, 7, 9, 7), thick(7, 9, 7, 14)],
            19: [thick(5, 7, 14, 7), thick(7, 5, 7, 14)],
            20: [thick(5, 7, 14, 7), thick(7, 0, 7, 9)],
            21: [thick(-1, 7, 7, 15)],
            22: [thick(15, 7, 7, 15)],
            23: [thick(15, 7, 7, -1)],
            24: [thick(-1, 7, 7, -1)],
            101: [thin(0, 7, 14, 7)],
            102: [thin(7, 0, 7, 14)],
            103: [thin(6, 7, 14, 7), thin(7, 6, 7, 14)],
            104: [thin(0, 7, 8, 7), thin(7, 8, 7, 14)],
            105: [thin(6, 7, 14, 7), thin(7, 0, 7, 8)],
            106: [thin(0, 7, 8, 7), thin(7, 0, 7, 8)],
            107: [thin(0, 7, 14, 7), thin(7, 7, 7, 14)],
            108: [thin(7, 0, 7, 14), thin(7, 7, 14, 7)],
            109: [thin(7, 0, 7, 14), thin(0, 7, 7, 7)],
            110: [thin(0, 7, 14, 7), thin(7, 0, 7, 7)],
            111: [thin(7, 0, 7, 14), thin(0, 7, 14, 7)],
            112: [thin(14, 7, 7, 14)],
            113: [thin(0, 7, 7, 14)],
            114: [thin(14, 7, 7, 0)],
            115: [thin(0, 7, 7, 0)],
            132: [thin(7, 14, 5, 11), thin(7, 14, 9, 11), thin(5, 3, 5, 11), thin(9, 3, 9, 11), thin(7, 0, 5, 3), thin(7, 0, 9, 3)],
            133: [thin(5, 14, 5, 8), thin(9, 14, 9, 8), thin(5, 8, 7, 0), thin(9, 8, 7, 0)],
            134: [thin(5, 0, 5, 14), thin(9, 0, 9, 14)],
            135: [thin(5, 0, 5, 8), thin(9, 0, 9, 8), thin(5, 8, 7, 14), thin(9, 8, 7, 14)],
            136: [thin(14, 7, 7, 7), thin(7, 7, 5, 11), thin(7, 7, 9, 11), thin(5, 11, 5, 14), thin(9, 11, 9, 14)],
            137: [thin(0, 7, 7, 7), thin(7, 7, 5, 11), thin(7, 7, 9, 11), thin(5, 11, 5, 14), thin(9, 11, 9, 14)],
            138: [thin(14, 7, 7, 7), thin(7, 7, 5, 3), thin(7, 7, 9, 3), thin(5, 3, 5, 0), thin(9, 3, 9, 0)],
            139: [thin(0, 7, 7, 7), thin(7, 7, 5, 3), thin(7, 7, 9, 3), thin(5, 3, 5, 0), thin(9, 3, 9, 0)],
            140: [thin(7, 14, 7, 7), thin(7, 7, 11, 5), thin(7, 7, 11, 9), thin(11, 5, 14, 5), thin(11, 9, 14, 9)],
            141: [thin(7, 14, 7, 7), thin(7, 7, 3, 5), thin(7, 7, 3, 9), thin(3, 5, 0, 5), thin(3, 9, 0, 9)],
            142: [thin(7, 0, 7, 7), thin(7, 7, 11, 5), thin(7, 7, 11, 9), thin(11, 5, 14, 5), thin(11, 9, 14, 9)],
            143: [thin(7, 0, 7, 7), thin(7, 7, 3, 5), thin(7, 7, 3, 9), thin(3, 5, 0, 5), thin(3, 9, 0, 9)],
            144: [thin(7, 7, 11, 5), thin(7, 7, 11, 9), thin(11, 5, 14, 5), thin(11, 9, 14, 9), thin(7, 7, 5, 11), thin(7, 7, 9, 11), thin(5, 11, 5, 14), thin(9, 11, 9, 14)],
            145: [thin(7, 7, 3, 5), thin(7, 7, 3, 9), thin(3, 5, 0, 5), thin(3, 9, 0, 9), thin(7, 7, 5, 11), thin(7, 7, 9, 11), thin(5, 11, 5, 14), thin(9, 11, 9, 14)],
            146: [thin(7, 7, 11, 5), thin(7, 7, 11, 9), thin(11, 5, 14, 5), thin(11, 9, 14, 9), thin(7, 7, 5, 3), thin(7, 7, 9, 3), thin(5, 3, 5, 0), thin(9, 3, 9, 0)],
            147: [thin(7, 7, 3, 5), thin(7, 7, 3, 9), thin(3, 5, 0, 5), thin(3, 9, 0, 9), thin(7, 7, 5, 3), thin(7, 7, 9, 3), thin(5, 3, 5, 0), thin(9, 3, 9, 0)],
            148: [thin(5, 14, 5, 5), thin(9, 14, 9, 9), thin(5, 5, 14, 5), thin(9, 9, 14, 9)],
            149: [thin(0, 5, 9, 5), thin(0, 9, 5, 9), thin(9, 5, 9, 14), thin(5, 9, 5, 14)],
            150: [thin(14, 5, 9, 5), thin(14, 9, 5, 9), thin(9, 5, 9, 0), thin(5, 9, 5, 0)],
            151: [thin(5, 0, 5, 5), thin(9, 0, 9, 9), thin(5, 5, 0, 5), thin(9, 9, 0, 9)],
            152: [thin(14, 7, 11, 5), thin(14, 7, 11, 9), thin(11, 5, 3, 5), thin(11, 9, 3, 9), thin(3, 5, 0, 7), thin(3, 9, 0, 7)],
            153: [thin(14, 5, 8, 5), thin(14, 9, 8, 9), thin(8, 5, 0, 7), thin(8, 9, 0, 7)],
            154: [thin(0, 5, 14, 5), thin(0, 9, 14, 9)],
            156: [thin(0, 5, 8, 5), thin(0, 9, 8, 9), thin(8, 5, 14, 7), thin(8, 9, 14, 7)],
            201: [thick(5, 7, 9, 7, B)],
            206: [thin(3, 14, 3, 3, B), thin(3, 3, 14, 3, B), thick(6, 14, 6, 6, L), thick(4, 6, 14, 6, L)],
            207: [thin(3, 0, 3, 14, B), thick(6, 0, 6, 14, L)],
            208: [thin(3, 0, 3, 11, B), thin(3, 11, 14, 11, B), thick(6, 0, 6, 8, L), thick(4, 8, 14, 8, L)],
            209: [thin(11, 0, 11, 11, B), thin(11, 11, 0, 11, B), thick(8, 0, 8, 8, L), thick(10, 8, 0, 8, L)],
            212: [thin(0, 3, 14, 3, B), thick(0, 6, 14, 6, L)],
            213: [thin(11, 14, 11, 3, B), thin(11, 3, 0, 3, B), thick(8, 14, 8, 6, L), thick(10, 6, 0, 6, L)],
            216: [thin(11, 0, 11, 14, B), thick(8, 0, 8, 14, L)],
            220: [thin(0, 11, 14, 11, B), thick(0, 8, 14, 8, L)],
            222: [thick(5, 7, 9, 7, Y)],
            223: [thick(5, 7, 14, 7, Y)],
            224: [thick(0, 7, 14, 7, Y)],
            225: [thick(0, 7, 9, 7, Y)],
            226: [thick(7, 5, 7, 14, Y)],
            227: [thick(7, 0, 7, 14, Y)],
            228: [thick(7, 0, 7, 9, Y)]
        };
        return lines;
    }

    var LINES = buildLines();
    var ALIAS = {
        116: 115, 117: 114, 118: 113, 119: 112, 120: 114, 121: 115, 122: 114, 123: 113,
        124: 112, 125: 114, 126: 112, 127: 112, 128: 115, 129: 115, 130: 113, 131: 113,
        202: 201, 203: 201, 204: 201, 205: 201,
        210: 208, 211: 206, 214: 206, 215: 213, 217: 209, 218: 213, 219: 209, 221: 208
    };
    var FACILITY = {80: 'WC', 81: 'WC', 82: 'Not', 83: 'Cat'};

    function drawSymbol(el, id) {
        if (!el) {
            return;
        }
        id = ALIAS[id] || id;
        el.style.background = '';
        el.classList.remove('is-text');
        if (id >= 1 && id <= 8) {
            el.replaceChildren();
            return;
        }
        if (id >= 300 && id <= 383 && LETTERS.charAt(id - 300)) {
            el.style.background = 'transparent';
            el.classList.add('is-text');
            el.textContent = LETTERS.charAt(id - 300);
            return;
        }
        if (FACILITY[id]) {
            el.style.background = 'transparent';
            el.classList.add('is-text');
            el.textContent = FACILITY[id];
            return;
        }
        var segs = LINES[id];
        if (!segs) {
            el.replaceChildren();
            return;
        }
        el.style.background = 'transparent';
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 14 14');
        svg.setAttribute('aria-hidden', 'true');
        segs.forEach(function (seg) {
            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', seg[0]);
            line.setAttribute('y1', seg[1]);
            line.setAttribute('x2', seg[2]);
            line.setAttribute('y2', seg[3]);
            line.setAttribute('stroke', seg[4]);
            line.setAttribute('stroke-width', seg[5]);
            line.setAttribute('stroke-linecap', 'square');
            svg.appendChild(line);
        });
        el.replaceChildren(svg);
    }

    function drawAll(root) {
        root.querySelectorAll('.seatplan__glyph').forEach(function (glyph) {
            drawSymbol(glyph, parseInt(glyph.dataset.symbol || '0', 10));
        });
    }

    function readAlign(root) {
        var xInput = root.querySelector('[data-align="x"]');
        var yInput = root.querySelector('[data-align="y"]');
        var scaleInput = root.querySelector('[data-align="scale"]');
        var x = xInput ? Number(xInput.value) : Number(root.dataset.floorX || 0);
        var y = yInput ? Number(yInput.value) : Number(root.dataset.floorY || 0);
        var scale = scaleInput ? Number(scaleInput.value) : Number(root.dataset.floorScale || 100);
        if (!isFinite(x)) {
            x = 0;
        }
        if (!isFinite(y)) {
            y = 0;
        }
        if (!isFinite(scale) || scale <= 0) {
            scale = 100;
        }
        return {x: x, y: y, scale: scale / 100};
    }

    function placeFloor(root) {
        var stage = root.querySelector('.seatplan__stage');
        var frame = root.querySelector('.seatplan__frame');
        var img = root.querySelector('.seatplan__floor');
        if (!stage || !frame) {
            return;
        }
        var align = readAlign(root);
        var shiftX = Math.min(0, align.x);
        var shiftY = Math.min(0, align.y);
        frame.style.transform = 'translate(' + (-shiftX) + 'px, ' + (-shiftY) + 'px)';
        var width = frame.offsetWidth - shiftX;
        var height = frame.offsetHeight - shiftY;
        if (img) {
            img.style.left = (align.x - shiftX) + 'px';
            img.style.top = (align.y - shiftY) + 'px';
            img.style.transform = 'scale(' + align.scale + ')';
            if (img.naturalWidth) {
                width = Math.max(width, align.x - shiftX + img.naturalWidth * align.scale);
                height = Math.max(height, align.y - shiftY + img.naturalHeight * align.scale);
            }
        }
        stage.style.minWidth = Math.ceil(width) + 'px';
        stage.style.minHeight = Math.ceil(height) + 'px';
    }

    function setTool(root, id, name) {
        root.dataset.tool = String(id);
        var current = root.querySelector('.seatplan__current-name');
        if (current) {
            current.textContent = name;
        }
        var note = root.querySelector('.seatplan__note');
        if (note) {
            note.hidden = true;
        }
        root.querySelectorAll('.seatplan__tool').forEach(function (button) {
            button.classList.toggle('is-selected', button.dataset.tool === String(id));
        });
    }

    function paintCell(root, cell) {
        if (!cell || cell.dataset.locked === '1') {
            return false;
        }
        var tool = root.dataset.tool;
        if (tool === undefined || tool === '') {
            return false;
        }
        cell.dataset.symbol = tool;
        var glyph = cell.querySelector('.seatplan__glyph');
        if (glyph) {
            glyph.dataset.symbol = tool;
            drawSymbol(glyph, parseInt(tool, 10));
        }
        var input = document.getElementById(cell.dataset.field);
        if (input) {
            input.value = tool;
        }
        return true;
    }

    function initEditor(root) {
        var painting = false;
        var pointerId = null;
        var note = root.querySelector('.seatplan__note');

        root.addEventListener('click', function (event) {
            var toolButton = event.target.closest('.seatplan__tool');
            if (toolButton) {
                setTool(root, toolButton.dataset.tool, toolButton.dataset.name || '');
                return;
            }
            var fill = event.target.closest('[data-fill-col], [data-fill-row]');
            if (!fill || !root.contains(fill)) {
                return;
            }
            var selector = fill.hasAttribute('data-fill-col')
                ? '.seatplan__cell[data-col="' + fill.dataset.fillCol + '"]'
                : '.seatplan__cell[data-row="' + fill.dataset.fillRow + '"]';
            root.querySelectorAll(selector).forEach(function (cell) {
                paintCell(root, cell);
            });
        });

        var letter = root.querySelector('.seatplan__letter-input');
        if (letter) {
            letter.addEventListener('input', function () {
                var chars = Array.from(letter.value);
                var ch = chars.length ? chars[chars.length - 1] : '';
                letter.value = ch;
                var index = LETTERS.indexOf(ch);
                if (ch !== '' && index === -1) {
                    setTool(root, root.dataset.tool || '1', letter.dataset.unknown || '');
                    return;
                }
                if (index === -1) {
                    return;
                }
                setTool(root, String(300 + index), (letter.dataset.letterLabel || '') + ': ' + ch);
            });
            letter.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
        }

        root.addEventListener('pointerdown', function (event) {
            var cell = event.target.closest('.seatplan__cell');
            if (!cell || !root.contains(cell)) {
                return;
            }
            if (cell.dataset.locked === '1') {
                if (note) {
                    note.hidden = false;
                }
                return;
            }
            painting = true;
            pointerId = event.pointerId;
            if (note) {
                note.hidden = true;
            }
            event.preventDefault();
            paintCell(root, cell);
        });

        window.addEventListener('pointermove', function (event) {
            if (!painting || event.pointerId !== pointerId) {
                return;
            }
            event.preventDefault();
            var under = document.elementFromPoint(event.clientX, event.clientY);
            var cell = under && under.closest ? under.closest('.seatplan__cell') : null;
            if (cell && root.contains(cell)) {
                paintCell(root, cell);
            }
        }, {passive: false});

        window.addEventListener('pointerup', function (event) {
            if (event.pointerId === pointerId) {
                painting = false;
                pointerId = null;
            }
        });
        window.addEventListener('pointercancel', function () {
            painting = false;
            pointerId = null;
        });
    }

    function showDetail(root, cell) {
        var body = root.querySelector('.seatplan__detail-body');
        var empty = root.querySelector('.seatplan__detail-empty');
        if (!body) {
            return;
        }
        var info = (root._seatplanDetails || {})[cell.dataset.key] || null;
        root.querySelectorAll('.seatplan__cell.is-selected').forEach(function (selected) {
            selected.classList.remove('is-selected');
        });
        cell.classList.add('is-selected');
        if (empty) {
            empty.hidden = true;
        }
        body.hidden = false;
        body.replaceChildren();

        var title = document.createElement('h3');
        title.className = 'seatplan__detail-title';
        title.textContent = info && info.title ? info.title : (cell.getAttribute('aria-label') || '');
        body.appendChild(title);

        if (info && info.image) {
            var image = document.createElement('img');
            image.className = 'seatplan__avatar';
            image.alt = '';
            image.src = info.image;
            body.appendChild(image);
        }
        if (info && info.lines) {
            var list = document.createElement('dl');
            info.lines.forEach(function (line) {
                var term = document.createElement('dt');
                term.textContent = line[0];
                var value = document.createElement('dd');
                value.textContent = line[1];
                list.appendChild(term);
                list.appendChild(value);
            });
            body.appendChild(list);
        }
        if (info && info.action) {
            var link = document.createElement('a');
            link.className = 'seatplan__action';
            link.href = info.action;
            link.textContent = info.actionLabel || '';
            body.appendChild(link);
        }
    }

    function initVisitor(root) {
        var viewport = root.querySelector('.seatplan__scroll');
        var stage = root.querySelector('.seatplan__stage');
        if (!viewport || !stage) {
            return;
        }
        var script = root.querySelector('.seatplan__data');
        try {
            root._seatplanDetails = script ? JSON.parse(script.textContent || '{}') : {};
        } catch (error) {
            root._seatplanDetails = {};
        }

        var scale = 1;
        var x = 0;
        var y = 0;
        var userAdjusted = false;
        var pointers = new Map();
        var pan = null;

        function apply() {
            stage.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + scale + ')';
            stage.style.transformOrigin = '0 0';
            var height = stage.offsetHeight * scale;
            viewport.style.height = Math.max(160, Math.min(window.innerHeight * 0.7, height)) + 'px';
        }

        function fittedScale() {
            var available = viewport.clientWidth || 0;
            var contentWidth = stage.offsetWidth || 0;
            if (available > 0 && contentWidth > 0) {
                return Math.min(1, available / contentWidth);
            }
            return 1;
        }

        function fit() {
            scale = fittedScale();
            x = 0;
            y = 0;
            apply();
        }

        function zoomAt(clientX, clientY, factor) {
            var rect = viewport.getBoundingClientRect();
            var px = clientX - rect.left;
            var py = clientY - rect.top;
            var floor = fittedScale();
            var next = Math.min(4, Math.max(floor, scale * factor));
            if (next <= floor + 0.001) {
                scale = floor;
                x = 0;
                y = 0;
                userAdjusted = false;
                apply();
                return;
            }
            var ratio = next / scale;
            x = px - ratio * (px - x);
            y = py - ratio * (py - y);
            scale = next;
            userAdjusted = true;
            apply();
        }

        root.querySelectorAll('[data-zoom]').forEach(function (button) {
            button.addEventListener('click', function () {
                var rect = viewport.getBoundingClientRect();
                zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, button.dataset.zoom === 'in' ? 1.2 : 0.8);
            });
        });

        viewport.addEventListener('wheel', function (event) {
            event.preventDefault();
            zoomAt(event.clientX, event.clientY, event.deltaY < 0 ? 1.1 : 0.9);
        }, {passive: false});

        viewport.addEventListener('pointerdown', function (event) {
            if (event.target.closest('[data-zoom]')) {
                return;
            }
            pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
            if (pointers.size === 1) {
                pan = {
                    id: event.pointerId,
                    x: event.clientX,
                    y: event.clientY,
                    ox: x,
                    oy: y,
                    moved: false,
                    cell: event.target.closest('.seatplan__cell')
                };
            }
        });

        viewport.addEventListener('pointermove', function (event) {
            if (!pointers.has(event.pointerId)) {
                return;
            }
            pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
            if (pointers.size === 2) {
                var pts = Array.from(pointers.values());
                var dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                if (!viewport._pinchDist) {
                    viewport._pinchDist = dist;
                    return;
                }
                var midX = (pts[0].x + pts[1].x) / 2;
                var midY = (pts[0].y + pts[1].y) / 2;
                var factor = dist / viewport._pinchDist;
                viewport._pinchDist = dist;
                if (factor > 0) {
                    zoomAt(midX, midY, factor);
                }
                if (pan) {
                    pan.moved = true;
                }
                return;
            }
            if (!pan || pan.id !== event.pointerId) {
                return;
            }
            var dx = event.clientX - pan.x;
            var dy = event.clientY - pan.y;
            if (Math.abs(dx) + Math.abs(dy) > 8) {
                pan.moved = true;
                userAdjusted = true;
            }
            if (pan.moved && scale > fittedScale() + 0.001) {
                x = pan.ox + dx;
                y = pan.oy + dy;
                apply();
            }
        });

        function endPointer(event) {
            pointers.delete(event.pointerId);
            viewport._pinchDist = null;
            if (!pan || pan.id !== event.pointerId) {
                return;
            }
            if (!pan.moved && pan.cell && root.contains(pan.cell)) {
                showDetail(root, pan.cell);
                root._seatplanSuppressClick = true;
            }
            pan = null;
        }

        viewport.addEventListener('pointerup', endPointer);
        viewport.addEventListener('pointercancel', endPointer);
        root.addEventListener('click', function (event) {
            var cell = event.target.closest('.seatplan__cell');
            if (!cell || !root.contains(cell)) {
                return;
            }
            if (root._seatplanSuppressClick) {
                root._seatplanSuppressClick = false;
                return;
            }
            showDetail(root, cell);
        });

        placeFloor(root);
        fit();
        var image = root.querySelector('.seatplan__floor');
        if (image && !image.complete) {
            image.addEventListener('load', function () {
                placeFloor(root);
                if (!userAdjusted) {
                    fit();
                }
            });
        }
    }

    function initAlign(root) {
        root.querySelectorAll('[data-align]').forEach(function (input) {
            input.addEventListener('input', function () {
                placeFloor(root);
            });
        });
        root.querySelectorAll('[data-aisle-width]').forEach(function (input) {
            input.addEventListener('input', function () {
                var width = parseInt(input.value, 10);
                if (!isFinite(width)) {
                    width = 14;
                }
                width = Math.max(4, Math.min(400, width));
                var selector = '[data-aisle-orient="' + input.dataset.aisleOrient + '"][data-aisle-value="' + input.dataset.aisleValue + '"]';
                root.querySelectorAll(selector).forEach(function (cell) {
                    if (cell === input) {
                        return;
                    }
                    cell.style.setProperty('--aisle-size', width + 'px');
                });
                placeFloor(root);
            });
        });
        root.addEventListener('click', function (event) {
            var link = event.target.closest('.seatplan__aisle-btn');
            if (!link || !root.contains(link)) {
                return;
            }
            var align = readAlign(root);
            var url = new URL(link.href, window.location.href);
            url.searchParams.set('floorplan_x', String(Math.round(align.x)));
            url.searchParams.set('floorplan_y', String(Math.round(align.y)));
            url.searchParams.set('floorplan_scale', String(Math.round(align.scale * 100)));
            link.href = url.pathname + url.search;
        });
    }

    function init(root) {
        drawAll(root);
        placeFloor(root);
        if (root.classList.contains('seatplan--mode-2')) {
            initEditor(root);
        }
        if (root.classList.contains('seatplan--mode-0')) {
            initVisitor(root);
        } else if (root.querySelector('[data-align]')) {
            initAlign(root);
        }
        var image = root.querySelector('.seatplan__floor');
        if (image && !root.classList.contains('seatplan--mode-0')) {
            image.addEventListener('load', function () {
                placeFloor(root);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.seatplan').forEach(init);
    });
}());
