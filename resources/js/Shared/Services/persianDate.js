const partsFormatter = new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    timeZone: 'UTC',
});

const currentDateFormatter = new Intl.DateTimeFormat('en-US-u-ca-persian-nu-latn', {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    timeZone: 'Asia/Tehran',
});

const monthCache = new Map();
const numberFormatter = new Intl.NumberFormat('fa-IR', {
    minimumIntegerDigits: 2,
    useGrouping: false,
});

function parseParts(parts) {
    const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return {
        year: Number(values.year),
        month: Number(values.month),
        day: Number(values.day),
    };
}

function partsForDate(date) {
    return parseParts(partsFormatter.formatToParts(date));
}

export function gregorianIsoToPersianParts(isoDate) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate ?? '');

    if (!match) {
        return null;
    }

    const [, year, month, day] = match;
    const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day), 12));

    if (date.toISOString().slice(0, 10) !== isoDate) {
        return null;
    }

    return partsForDate(date);
}

export function currentPersianDateParts() {
    return parseParts(currentDateFormatter.formatToParts(new Date()));
}

export function persianMonthDays(year, month) {
    const key = `${year}-${month}`;

    if (monthCache.has(key)) {
        return monthCache.get(key);
    }

    const days = [];
    const start = Date.UTC(year + 621, 2, 15, 12);

    for (let offset = 0; offset <= 380; offset += 1) {
        const date = new Date(start + offset * 24 * 60 * 60 * 1000);
        const parts = partsForDate(date);

        if (parts.year === year && parts.month === month) {
            days.push({
                iso: date.toISOString().slice(0, 10),
                day: parts.day,
            });
        }
    }

    monthCache.set(key, days);

    return days;
}

export function persianDateToGregorianIso(year, month, day) {
    const date = persianMonthDays(year, month).find((candidate) => candidate.day === day);

    return date?.iso ?? null;
}

export function formatGregorianIsoAsPersian(isoDate) {
    const parts = gregorianIsoToPersianParts(isoDate);

    if (!parts) {
        return '';
    }

    return [parts.year, parts.month, parts.day].map((part) => numberFormatter.format(part)).join('/');
}
