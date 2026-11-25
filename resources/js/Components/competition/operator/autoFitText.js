const directiveStates = new WeakMap();
let measurementContext = null;

function getMeasurementContext() {
    if (typeof document === 'undefined') {
        return null;
    }

    measurementContext ??= document.createElement('canvas').getContext('2d');

    return measurementContext;
}

function getText(element, computedStyle = null) {
    const text = element.textContent?.trim().replace(/\s+/g, ' ') ?? '';

    if (computedStyle?.textTransform === 'uppercase') {
        return text.toLocaleUpperCase();
    }

    if (computedStyle?.textTransform === 'lowercase') {
        return text.toLocaleLowerCase();
    }

    return text;
}

function measureText(context, text, letterSpacing) {
    return context.measureText(text).width + Math.max(0, Array.from(text).length - 1) * letterSpacing;
}

function fitsWithinLines(context, text, availableWidth, letterSpacing, maximumLines) {
    if (maximumLines <= 1) {
        return measureText(context, text, letterSpacing) <= availableWidth;
    }

    let lineCount = 1;
    let currentLine = '';

    for (const word of text.split(' ')) {
        const candidateLine = currentLine ? `${currentLine} ${word}` : word;

        if (measureText(context, candidateLine, letterSpacing) <= availableWidth) {
            currentLine = candidateLine;
            continue;
        }

        if (currentLine) {
            lineCount += 1;
            currentLine = '';

            if (lineCount > maximumLines) {
                return false;
            }
        }

        if (measureText(context, word, letterSpacing) <= availableWidth) {
            currentLine = word;
            continue;
        }

        let chunk = '';

        for (const character of Array.from(word)) {
            if (chunk && measureText(context, `${chunk}${character}`, letterSpacing) > availableWidth) {
                lineCount += 1;
                chunk = character;

                if (lineCount > maximumLines) {
                    return false;
                }
            } else {
                chunk += character;
            }
        }

        currentLine = chunk;
    }

    return lineCount <= maximumLines;
}

function fitText(element, options = {}) {
    const context = getMeasurementContext();
    const sourceText = getText(element);

    if (!context || sourceText === '') {
        return;
    }

    const originalFontSize = element.style.fontSize;
    element.style.removeProperty('font-size');

    const computedStyle = getComputedStyle(element);
    const text = getText(element, computedStyle);
    const maximumFontSize = Number(options.max) || Number.parseFloat(computedStyle.fontSize);
    const minimumFontSize = Math.min(Number(options.min) || 12, maximumFontSize);
    const maximumLines = Math.max(1, Math.floor(Number(options.maxLines) || 1));
    const availableWidth = element.clientWidth
        - Number.parseFloat(computedStyle.paddingLeft)
        - Number.parseFloat(computedStyle.paddingRight);

    element.style.fontSize = originalFontSize;

    if (!Number.isFinite(maximumFontSize) || availableWidth <= 0) {
        return;
    }

    const letterSpacing = Number.parseFloat(computedStyle.letterSpacing) || 0;
    const measureAtSize = fontSize => {
        context.font = `${computedStyle.fontStyle} ${computedStyle.fontWeight} ${fontSize}px ${computedStyle.fontFamily}`;

        return fitsWithinLines(context, text, availableWidth, letterSpacing, maximumLines);
    };

    let lowerBound = minimumFontSize;
    let upperBound = maximumFontSize;

    for (let iteration = 0; iteration < 12; iteration += 1) {
        const candidate = (lowerBound + upperBound) / 2;

        if (measureAtSize(candidate)) {
            lowerBound = candidate;
        } else {
            upperBound = candidate;
        }
    }

    const nextSize = Math.floor(lowerBound * 10) / 10;
    const currentSize = Number.parseFloat(element.style.fontSize);

    if (!Number.isFinite(currentSize) || Math.abs(currentSize - nextSize) >= 0.2) {
        element.style.fontSize = `${nextSize}px`;
    }
}

function scheduleFit(element, state) {
    if (state.animationFrame !== null) {
        return;
    }

    state.animationFrame = requestAnimationFrame(() => {
        state.animationFrame = null;

        if (directiveStates.has(element)) {
            fitText(element, state.options);
        }
    });
}

export const vAutoFitText = {
    mounted(element, binding) {
        const state = {
            options: binding.value ?? {},
            optionsKey: JSON.stringify(binding.value ?? {}),
            text: getText(element),
            observer: null,
            animationFrame: null,
            observedWidth: null,
            onResize: null,
        };
        state.onResize = () => scheduleFit(element, state);

        if (typeof ResizeObserver !== 'undefined') {
            state.observer = new ResizeObserver(([entry]) => {
                const width = entry.contentRect.width;

                if (state.observedWidth === null || Math.abs(width - state.observedWidth) > 0.5) {
                    state.observedWidth = width;
                    state.onResize();
                }
            });
            state.observer.observe(element);
        }

        window.addEventListener('resize', state.onResize);
        directiveStates.set(element, state);
        element.classList.add('operator-auto-fit-text');
        fitText(element, state.options);
        document.fonts?.ready.then(state.onResize);
    },
    updated(element, binding) {
        const state = directiveStates.get(element);

        if (state) {
            const nextText = getText(element);
            const nextOptionsKey = JSON.stringify(binding.value ?? {});

            if (state.text === nextText && state.optionsKey === nextOptionsKey) {
                return;
            }

            state.text = nextText;
            state.options = binding.value ?? {};
            state.optionsKey = nextOptionsKey;
            scheduleFit(element, state);
        }
    },
    unmounted(element) {
        const state = directiveStates.get(element);

        if (state) {
            state.observer?.disconnect();
            window.removeEventListener('resize', state.onResize);
            if (state.animationFrame !== null) {
                cancelAnimationFrame(state.animationFrame);
            }
            directiveStates.delete(element);
        }

        element.classList.remove('operator-auto-fit-text');
    },
};
