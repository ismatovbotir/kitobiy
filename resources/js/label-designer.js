/**
 * Drag-and-drop label designer (Alpine). Elements are kept in mm; the canvas is
 * drawn at real size (CSS mm) and zoomed, so screen px → mm = px / (PX_PER_MM * zoom).
 * Elements are sent to Livewire only when saving.
 */
const PX_PER_MM = 96 / 25.4;
const SNAP_MM = 0.5;

const snap = (value) => Math.round(value / SNAP_MM) * SNAP_MM;
const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

export function labelDesigner({ elements, catalog }) {
    return {
        elements: structuredClone(elements),
        catalog,
        selected: null,
        dragging: null,
        showGrid: true,

        get width() {
            return Math.max(10, Number(this.$wire.widthMm) || 10);
        },
        get height() {
            return Math.max(10, Number(this.$wire.heightMm) || 10);
        },
        get zoom() {
            const box = this.$refs.stage?.clientWidth || 560;
            return Math.min((box - 32) / (this.width * PX_PER_MM), 420 / (this.height * PX_PER_MM), 6);
        },
        get current() {
            return this.selected === null ? null : this.elements[this.selected];
        },

        sampleFor(element) {
            return element.type === 'text' ? element.text || '…' : this.catalog[element.type]?.sample ?? '';
        },

        elementStyle(element, index) {
            return {
                left: `${element.x}mm`,
                top: `${element.y}mm`,
                width: `${element.w}mm`,
                height: `${element.h}mm`,
                fontSize: `${element.font_size}pt`,
                fontWeight: element.bold ? 700 : 400,
                textAlign: element.align,
                zIndex: index === this.selected ? 20 : 10,
            };
        },

        add(type, x = null, y = null) {
            const defaults = this.catalog[type].defaults;
            const w = Math.min(defaults.w, this.width);
            const h = Math.min(defaults.h, this.height);

            this.elements.push({
                type,
                x: clamp(snap(x ?? (this.width - w) / 2), 0, this.width - w),
                y: clamp(snap(y ?? (this.height - h) / 2), 0, this.height - h),
                w,
                h,
                font_size: defaults.font_size,
                bold: type === 'title',
                align: 'left',
                text: type === 'text' ? this.catalog.text.sample : null,
            });
            this.selected = this.elements.length - 1;
        },

        /** Drop from the element menu onto the canvas. */
        drop(event) {
            const type = event.dataTransfer.getData('text/label-element');
            if (!this.catalog[type]) {
                return;
            }
            const rect = this.$refs.canvas.getBoundingClientRect();
            const scale = PX_PER_MM * this.zoom;
            const defaults = this.catalog[type].defaults;
            this.add(type, (event.clientX - rect.left) / scale - defaults.w / 2, (event.clientY - rect.top) / scale - defaults.h / 2);
        },

        /** Start moving (mode "move") or resizing (mode "resize") an element. */
        startDrag(event, index, mode) {
            this.selected = index;
            const element = this.elements[index];
            this.dragging = {
                mode,
                startX: event.clientX,
                startY: event.clientY,
                origin: { ...element },
                square: this.catalog[element.type].square,
            };
            event.target.setPointerCapture?.(event.pointerId);
        },

        onPointerMove(event) {
            if (!this.dragging) {
                return;
            }
            const scale = PX_PER_MM * this.zoom;
            const dx = (event.clientX - this.dragging.startX) / scale;
            const dy = (event.clientY - this.dragging.startY) / scale;
            const origin = this.dragging.origin;
            const element = this.elements[this.selected];

            if (this.dragging.mode === 'move') {
                element.x = clamp(snap(origin.x + dx), 0, this.width - element.w);
                element.y = clamp(snap(origin.y + dy), 0, this.height - element.h);
                return;
            }

            let w = clamp(snap(origin.w + dx), 2, this.width - origin.x);
            let h = clamp(snap(origin.h + dy), 2, this.height - origin.y);
            if (this.dragging.square) {
                w = h = Math.min(Math.max(w, h), this.width - origin.x, this.height - origin.y);
            }
            element.w = w;
            element.h = h;
        },

        stopDrag() {
            this.dragging = null;
        },

        /** Arrow keys nudge the selected element; Delete removes it. */
        onKey(event) {
            if (this.current === null || ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) {
                return;
            }
            const step = event.shiftKey ? 2 : SNAP_MM;
            const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (moves[event.key]) {
                event.preventDefault();
                const element = this.current;
                element.x = clamp(snap(element.x + moves[event.key][0]), 0, this.width - element.w);
                element.y = clamp(snap(element.y + moves[event.key][1]), 0, this.height - element.h);
            } else if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.remove();
            } else if (event.key === 'Escape') {
                this.selected = null;
            }
        },

        remove() {
            if (this.selected === null) {
                return;
            }
            this.elements.splice(this.selected, 1);
            this.selected = null;
        },

        duplicateSelected() {
            if (this.current === null) {
                return;
            }
            const copy = { ...this.current };
            copy.x = clamp(copy.x + 2, 0, this.width - copy.w);
            copy.y = clamp(copy.y + 2, 0, this.height - copy.h);
            this.elements.push(copy);
            this.selected = this.elements.length - 1;
        },

        /** Keep a numeric field from the settings panel inside the label. */
        fit() {
            for (const element of this.elements) {
                element.w = clamp(Number(element.w) || 2, 2, this.width);
                element.h = clamp(Number(element.h) || 2, 2, this.height);
                if (this.catalog[element.type].square) {
                    element.w = element.h = Math.min(element.w, element.h);
                }
                element.x = clamp(Number(element.x) || 0, 0, this.width - element.w);
                element.y = clamp(Number(element.y) || 0, 0, this.height - element.h);
            }
        },

        save() {
            this.fit();
            this.$wire.elements = structuredClone(window.Alpine.raw(this.elements));
            this.$wire.save();
        },
    };
}
