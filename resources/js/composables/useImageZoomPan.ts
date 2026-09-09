import { computed, ref, watch } from 'vue';

type Options = {
    min?: number;
    max?: number;
    step?: number;
};

/**
 * Zoom + pan pour lightbox images (centrage viewport + glisser hors 100 %).
 */
export function useImageZoomPan(options: Options = {}) {
    const min = options.min ?? 0.5;
    const max = options.max ?? 3;
    const step = options.step ?? 0.25;

    const zoomLevel = ref(1);
    const panX = ref(0);
    const panY = ref(0);
    const dragging = ref(false);
    const lastPointer = ref({ x: 0, y: 0 });

    const canPan = computed(() => zoomLevel.value !== 1);
    const zoomPercent = computed(() => Math.round(zoomLevel.value * 100));
    const imageTransform = computed(
        () =>
            `translate(${panX.value}px, ${panY.value}px) scale(${zoomLevel.value})`,
    );

    function resetView() {
        zoomLevel.value = 1;
        panX.value = 0;
        panY.value = 0;
        dragging.value = false;
    }

    function zoomIn() {
        zoomLevel.value = Math.min(zoomLevel.value + step, max);
    }

    function zoomOut() {
        zoomLevel.value = Math.max(zoomLevel.value - step, min);
    }

    function handleWheel(e: WheelEvent) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        zoomLevel.value = Math.max(min, Math.min(max, zoomLevel.value + delta));
    }

    function onPointerDown(e: PointerEvent) {
        if (!canPan.value) return;
        dragging.value = true;
        lastPointer.value = { x: e.clientX, y: e.clientY };
        (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    }

    function onPointerMove(e: PointerEvent) {
        if (!dragging.value) return;
        panX.value += e.clientX - lastPointer.value.x;
        panY.value += e.clientY - lastPointer.value.y;
        lastPointer.value = { x: e.clientX, y: e.clientY };
    }

    function onPointerUp(e: PointerEvent) {
        dragging.value = false;
        try {
            (e.currentTarget as HTMLElement).releasePointerCapture(e.pointerId);
        } catch {
            // ignore
        }
    }

    watch(zoomLevel, (value) => {
        if (value === 1) {
            panX.value = 0;
            panY.value = 0;
        }
    });

    return {
        zoomLevel,
        zoomPercent,
        panX,
        panY,
        dragging,
        canPan,
        imageTransform,
        resetView,
        zoomIn,
        zoomOut,
        handleWheel,
        onPointerDown,
        onPointerMove,
        onPointerUp,
    };
}
