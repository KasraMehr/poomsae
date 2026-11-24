const directiveStates = new WeakMap();
let measurementContext = null;

function getMeasurementContext() {
    if (typeof document === 'undefined') {
        return null;
    }

    measurementContext ??= document.createElement('canvas').getContext('2d');

    return measurementContext;
}

function fitText(element, options = {}) {
    const context = getMeasurementContext();
    const text = element.textContent?.trim().replace(/\s+/g, ' ') ?? '';

    if (!context || text === '') {
        return;
    }

    const originalFontSize = element.style.fontSize;
    element.style.removeProperty('font-size');

    const computedStyle = getComputedStyle(element);
    const maximumFontSize = Number(options.max) || Number.parseFloat(computedStyle.fontSize);
    const minimumFontSize = Math.min(Number(options.min) || 12, maximumFontSize);
    const availableWidth = element.clientWidth
        - Number.parseFloat(computedStyle.paddingLeft)
        - Number.parseFloat(computedStyle.paddingRight);

    element.style.fontSize = originalFontSize;

    if (!Number.isFinite(maximumFontSize) || availableWidth <= 0) {
        return;
    }

    const letterSpacing = Number.parseFloat(computedStyle.letterSpacing) || 0;
    const measure = fontSize => {
        context.font = `${computedStyle.fontStyle} ${computedStyle.fontWeight} ${fontSize}px ${computedStyle.fontFamily}`;

        return context.measureText(text).width + (text.length - 1) * letterSpacing;
    };

    let lowerBound = minimumFontSize;
    let upperBound = maximumFontSize;

    for (let iteration = 0; iteration < 12; iteration += 1) {
        const candidate = (lowerBound + upperBound) / 2;

        if (measure(candidate) <= availableWidth) {
            lowerBound = candidate;
        } else {
            upperBound = candidate;
        }
    }

    const nextFontSize = `${Math.floor(lowerBound * 100) / 100}px`;

    if (element.style.fontSize !== nextFontSize) {
        element.style.fontSize = nextFontSize;
    }
}

function scheduleFit(element, state) {
    requestAnimationFrame(() => {
        if (directiveStates.has(element)) {
            fitText(element, state.options);
        }
    });
}

export const vAutoFitText = {
    mounted(element, binding) {
        const state = { options: binding.value ?? {}, observer: null, onResize: null };
        state.onResize = () => scheduleFit(element, state);

        if (typeof ResizeObserver !== 'undefined') {
            state.observer = new ResizeObserver(state.onResize);
            state.observer.observe(element);
        }

        window.addEventListener('resize', state.onResize);
        directiveStates.set(element, state);
        fitText(element, state.options);
        document.fonts?.ready.then(state.onResize);
    },
    updated(element, binding) {
        const state = directiveStates.get(element);

        if (state) {
            state.options = binding.value ?? {};
            fitText(element, state.options);
        }
    },
    unmounted(element) {
        const state = directiveStates.get(element);

        if (state) {
            state.observer?.disconnect();
            window.removeEventListener('resize', state.onResize);
            directiveStates.delete(element);
        }
    },
};
