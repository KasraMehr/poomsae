/**
 * Scoreboard Lab — داده‌های نمونه (fixtures)
 *
 * شکل داده‌ها طبق [Proposed Backend Contract] در docs/scoreboard-contract.md (بخش ۴) است؛
 * هدف، دیدن «شکل نهایی» کامپوننت‌ها پس از تکمیل Backend است.
 * این فایل هیچ وابستگی به Inertia/Backend ندارد؛ لوگو و پرچم‌ها data-URI داخلی‌اند
 * و سمبل فرم‌ها/مدال‌ها از map دارایی‌های پروژه (`Shared/Services/symbols.js`) می‌آیند.
 */

// مسیر تصاویر از لایهٔ resolve لَب می‌آید (dev server پوشهٔ public/ را سرو نمی‌کند)
import { formSymbol, medalSymbol, formSymbolStrip } from "./assets";
export { formSymbolStrip };

const svg = (markup) =>
    `data:image/svg+xml;utf8,${encodeURIComponent(markup.trim())}`;

/* ------------------------------------------------------------------ تصاویر */

export const logoUrl = svg(`
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 168 48">
        <rect x="1" y="1" width="166" height="46" rx="10" fill="#1a1d28" stroke="#2d3748"/>
        <circle cx="26" cy="24" r="13" fill="#ffcc00"/>
        <path d="M26 14l3 6 6 1-4.5 4.5 1 6.5-5.5-3-5.5 3 1-6.5L17 21l6-1z" fill="#0c0e17"/>
        <text x="48" y="30" font-family="Arial" font-size="15" font-weight="700" fill="#e1e1ef">POOMSE CUP</text>
    </svg>
`);

const flag = (a, b, c) =>
    svg(`
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 16">
        <rect width="24" height="5.34" fill="${a}"/>
        <rect y="5.33" width="24" height="5.34" fill="${b}"/>
        <rect y="10.66" width="24" height="5.34" fill="${c}"/>
    </svg>
`);

const flags = {
    ir: flag("#239f40", "#ffffff", "#da0000"),
    kr: flag("#ffffff", "#cd2e3a", "#0047a0"),
    tr: flag("#e30a17", "#ffffff", "#e30a17"),
    jp: flag("#ffffff", "#bc002d", "#ffffff"),
    fr: flag("#0055a4", "#ffffff", "#ef4135"),
    kz: flag("#00afca", "#fec50c", "#00afca"),
    de: flag("#000000", "#dd0000", "#ffce00"),
    es: flag("#aa151b", "#f1bf00", "#aa151b"),
};

/* -------------------------------------------------------------- رویداد/هدر */

export const eventInfo = {
    stage: "مرحلهٔ نهایی",
    title: "جام جهانی پومسه",
    category: "مردان — کلاس ۱",
    roundLabel: "دور ۳ · زمین ۲",
};

export const headerCases = [
    {
        title: "با لوگو — logoUrl (پیشنهادی)",
        props: { ...eventInfo, logoUrl },
    },
    {
        title: "بدون لوگو — logoUrl=null (وضعیت snapshot فعلی)",
        props: { ...eventInfo, logoUrl: "" },
    },
    {
        title: "حداقلی — فقط عنوان رویداد",
        props: { eventTitle: eventInfo.title },
    },
];

/* ------------------------------------------------------------- AthleteInfo */

const chung = {
    number: 12,
    name: "علی رضایی",
    country: "ایران",
    countryCode: "IRI",
    flagUrl: flags.ir,
    side: "chung",
};

const hong = {
    number: 7,
    name: "کیم چان‌هی",
    country: "کرهٔ جنوبی",
    countryCode: "KOR",
    flagUrl: flags.kr,
    side: "hong",
};

// نام لاتین مثل طرح اصلی (text-transform: uppercase)
const salmani = {
    number: 1,
    name: "a.salmani",
    country: "ایران",
    countryCode: "IRI",
    flagUrl: flags.ir,
    side: "chung",
};

export const athleteCases = [
    { title: "horizontal · chung (آبی) · lg — ابعاد طرح", props: { layout: "horizontal", size: "lg", ...chung } },
    { title: "horizontal · hong (قرمز) · lg — آینه‌ای: عدد و بج سمت راست", props: { layout: "horizontal", size: "lg", ...hong } },
    { title: "horizontal · chung · md", props: { layout: "horizontal", size: "md", ...chung } },
    { title: "horizontal · hong · sm", props: { layout: "horizontal", size: "sm", ...hong } },
    { title: "horizontal · نام لاتین — uppercase مثل طرح (A.SALMANI)", props: { layout: "horizontal", size: "lg", ...salmani } },
    {
        title: "horizontal · سمت خنثی — مثل Draw/TopFour",
        props: {
            layout: "horizontal",
            size: "md",
            number: 3,
            name: "مریم احمدی",
            country: "ایران",
            countryCode: "IRI",
            flagUrl: flags.ir,
        },
    },
    {
        title: "بدون country_code/flag_url — بج مخفی می‌شود (null-safe)",
        props: { layout: "horizontal", size: "md", number: 5, name: "قرعه‌کشی" },
    },
    { title: "name-only · chung · lg — فقط نوار نام", props: { layout: "name-only", size: "lg", ...chung } },
    { title: "name-only · hong · md", props: { layout: "name-only", size: "md", ...hong } },
    { title: "vertical · chung · lg — بج بالای نوار، لنگر چپ", props: { layout: "vertical", size: "lg", ...chung } },
    { title: "vertical · hong · lg — بج بالای نوار، لنگر راست", props: { layout: "vertical", size: "lg", ...hong } },
    { title: "vertical-under · chung · lg — نوار بالا، بج پایین", props: { layout: "vertical-under", size: "lg", ...chung } },
    { title: "vertical-under · hong · lg — نوار بالا، بج پایین (راست)", props: { layout: "vertical-under", size: "lg", ...hong } },
];

export const nationalityCases = [
    { title: "sm · پرچم چپ (flagPosition=left)", props: { size: "sm", flagPosition: "left", code: "IRI", country: "ایران", flagUrl: flags.ir } },
    { title: "sm · پرچم راست (flagPosition=right)", props: { size: "sm", flagPosition: "right", code: "KOR", country: "کرهٔ جنوبی", flagUrl: flags.kr } },
    { title: "md · پرچم چپ", props: { size: "md", flagPosition: "left", code: "TUR", country: "ترکیه", flagUrl: flags.tr } },
    { title: "md · پرچم راست", props: { size: "md", flagPosition: "right", code: "JPN", country: "ژاپن", flagUrl: flags.jp } },
    { title: "lg · پرچم چپ — ابعاد طرح (ارتفاع ۱۳۵px، فونت ۹۶px)", props: { size: "lg", flagPosition: "left", code: "IRI", country: "ایران", flagUrl: flags.ir } },
    { title: "lg · پرچم راست", props: { size: "lg", flagPosition: "right", code: "FRA", country: "فرانسه", flagUrl: flags.fr } },
    { title: "بدون flag_url — placeholder: کد داخل قاب پرچم", props: { size: "md", flagPosition: "left", code: "KAZ", country: "قزاقستان", flagUrl: "" } },
    { title: "بدون flag_url · sm · پرچم راست", props: { size: "sm", flagPosition: "right", code: "IRI", country: "ایران", flagUrl: "" } },
];

/* -------------------------------------------------------------- FormBadge */

export const formBadgeCases = [
    {
        title: "sm — در Scoring (با form_number → سمبل)",
        props: { size: "sm", roundLabel: "R - 3", formNumber: 5, formName: "TAEGUK 5" },
    },
    {
        title: "md — در Standby/LiveBoard",
        props: { size: "md", roundLabel: "R - 3", formNumber: 2, formName: "TAEGUK 2" },
    },
    {
        title: "lg — در Draw",
        props: { size: "lg", roundLabel: "FINAL", formNumber: 8, formName: "TAEGUK 8" },
    },
    {
        title: "بدون form_number — فقط متن (کلید ندارد)",
        props: { size: "md", roundLabel: "R - 3", formNumber: null, formName: "فرم ۲" },
    },
    {
        title: "فرم مجموعهٔ دوم (کلید ۹..۱۸)",
        props: { size: "md", roundLabel: "R - 1", formNumber: 12, formName: "MOVE 12" },
    },
];

/* ----------------------------------------------------- SymbolContainer/Medals */

export const symbolCases = [
    { title: "form-1 · sm (۲۴px)", props: { src: formSymbol(1), label: "فرم ۱", size: "sm" } },
    { title: "form-1 · md (۴۰px)", props: { src: formSymbol(1), label: "فرم ۱", size: "md" } },
    { title: "form-1 · lg (۵۶px)", props: { src: formSymbol(1), label: "فرم ۱", size: "lg" } },
    { title: "form-5 · md — نمونهٔ تصویر FORM_BADGE", props: { src: formSymbol(5), label: "TAEGUK 5", size: "md" } },
    { title: "بدون src (کلید ناشناخته) — چیزی رندر نمی‌شود", props: { src: formSymbol(99), label: "نامعتبر", size: "md" } },
];



export const medalCases = [
    { title: "رتبهٔ ۱ — طلا · lg", props: { src: medalSymbol(1), label: "رتبهٔ ۱", size: "lg" } },
    { title: "رتبهٔ ۲ — نقره · md", props: { src: medalSymbol(2), label: "رتبهٔ ۲", size: "md" } },
    { title: "رتبهٔ ۳ — برنز · md", props: { src: medalSymbol(3), label: "رتبهٔ ۳", size: "md" } },
    { title: "رتبهٔ ۴ — خاکستری · md", props: { src: medalSymbol(4), label: "رتبهٔ ۴", size: "md" } },
    { title: "رتبهٔ ۵ — خاکستری · md", props: { src: medalSymbol(5), label: "رتبهٔ ۵", size: "md" } },
    { title: "رتبهٔ ۶ — خارج از محدوده، بدون مدال", props: { src: medalSymbol(6), label: "رتبهٔ ۶", size: "md" } },
];

/* ------------------------------------------------------------------ Timer */

/**
 * سه حالت تایتر، همه مشتق از started_at / ended_at (کنترل از پنل اپراتور):
 * idle = پیش از شروع، running = حین اجرا، stopped = اپراتور متوقف کرده.
 * زمان‌ها نسبت به لحظهٔ باز شدن لَب محاسبه می‌شوند تا شمارش زنده دیده شود.
 */
const now = Date.now();
const startedAt = now - 31_000; // ~۳۱ ثانیه گذشته از ۹۰ ثانیه

export const timerCases = [
    {
        title: "idle — پیش از شروع: فقط مدت کل (۱:۳۰) — Standby",
        props: { size: "lg", durationSeconds: 90, startedAt: null, endedAt: null },
    },
    {
        title: "running — حین اجرا، شمارش معکوس — Standby",
        props: { size: "lg", durationSeconds: 90, startedAt, endedAt: null },
    },
    {
        title: "stopped — اپراتور متوقف کرده (اجرای ۴۵ ثانیه‌ای) — Standby",
        props: {
            size: "lg",
            durationSeconds: 90,
            startedAt: now - 70_000,
            endedAt: now - 25_000,
        },
    },
    {
        title: "sm — همان حالت‌ها با اندازهٔ کوچک (LiveBoard)",
        props: { size: "sm", durationSeconds: 90, startedAt, endedAt: null },
    },
    {
        title: "stopped — اجرای تمام‌شده با زمان کامل (۰۰:۰۰)",
        props: {
            size: "lg",
            durationSeconds: 90,
            startedAt: now - 95_000,
            endedAt: now - 5_000,
        },
    },
    {
        title: "مدت غیر استاندارد — execution_duration_seconds=120",
        props: { size: "lg", durationSeconds: 120, startedAt: now - 51_000, endedAt: null },
    },
    {
        title: "ورودی ISO — started_at به شکل رشته",
        props: {
            size: "lg",
            durationSeconds: 90,
            startedAt: new Date(now - 61_000).toISOString(),
            endedAt: null,
        },
    },
    {
        title: "بدون execution_duration_seconds — پیش‌فرض ۰ (۰۰:۰۰)",
        props: { size: "lg", durationSeconds: 0, startedAt: null, endedAt: null },
    },
];

/* ------------------------------------------------- ScoreCard / ScoreItem */

/**
 * سقف‌های پومسه ثابت‌اند (تصمیم کاربر): Accuracy=4، Presentation=6، Total=10.
 * این‌ها در همین‌جا تعریف می‌شوند و به ScoreItem پاس داده می‌شوند؛
 * یعنی ScoreCard خودش سقف‌ها را نمی‌داند و فقط چیدمان و آینه‌سازی را می‌دهد.
 */
export const SCORE_MAX = {
    accuracy: 4,
    presentation: 6,
    total: 10,
};

/** برچسب‌ها دقیقاً همان‌طور که در طرح آمده‌اند (لاتین، برای نمایش انگلیسی). */
export const SCORE_LABELS = {
    accuracy: "Accuracy",
    presentation: "Presentation",
    total: "Total Score",
};

/**
 * امتیازهای نمونهٔ صفحهٔ Result (از تصویر RESULT-ONE-ROUND):
 * ورزشکار آبی ۳.۴۶ / ۵.۰۰ / ۸.۴۶ و ورزشکار قرمز ۳.۵۲ / ۵.۲۰ / ۸.۷۲ (برنده).
 */
export const scoreCardCases = [
    {
        title: "chung (آبی) · lg · برنده نیست",
        card: { side: "chung", winner: false, size: "lg" },
        items: [
            { variant: "sub", label: SCORE_LABELS.accuracy, value: 3.46, max: SCORE_MAX.accuracy },
            { variant: "sub", label: SCORE_LABELS.presentation, value: 5.0, max: SCORE_MAX.presentation },
            { variant: "total", label: SCORE_LABELS.total, value: 8.46, max: SCORE_MAX.total },
        ],
    },
    {
        title: "hong (قرمز) · lg · **برنده** — کادر زرد فقط روی total",
        card: { side: "hong", winner: true, size: "lg" },
        items: [
            { variant: "sub", label: SCORE_LABELS.accuracy, value: 3.52, max: SCORE_MAX.accuracy },
            { variant: "sub", label: SCORE_LABELS.presentation, value: 5.2, max: SCORE_MAX.presentation },
            { variant: "total", label: SCORE_LABELS.total, value: 8.72, max: SCORE_MAX.total },
        ],
    },
    {
        title: "روبه‌رو — دقیقاً مثل صفحهٔ Result (آبی چپ، قرمز راست)",
        card: null, // رندر ویژه: جفت رو‌به‌رو
        items: null,
    },
    {
        title: "sm — نسخهٔ فشرده (جای فشرده‌تر)",
        card: { side: "chung", winner: true, size: "sm" },
        items: [
            { variant: "sub", label: SCORE_LABELS.accuracy, value: 3.46, max: SCORE_MAX.accuracy },
            { variant: "sub", label: SCORE_LABELS.presentation, value: 5.0, max: SCORE_MAX.presentation },
            { variant: "total", label: SCORE_LABELS.total, value: 8.46, max: SCORE_MAX.total },
        ],
    },
    {
        title: "بدون امتیاز — هر سه «--» (هنوز قاضی نمره نداده)",
        card: { side: "chung", winner: false, size: "lg" },
        items: [
            { variant: "sub", label: SCORE_LABELS.accuracy, value: null, max: SCORE_MAX.accuracy },
            { variant: "sub", label: SCORE_LABELS.presentation, value: null, max: SCORE_MAX.presentation },
            { variant: "total", label: SCORE_LABELS.total, value: null, max: SCORE_MAX.total },
        ],
    },
    {
        title: "سفیدی کامل — بدون کادر، بدون رنگ تیم (default)",
        card: { side: "chung", winner: false, size: "lg" },
        items: [
            { variant: "sub", label: SCORE_LABELS.accuracy, value: 0, max: SCORE_MAX.accuracy },
            { variant: "sub", label: SCORE_LABELS.presentation, value: 0, max: SCORE_MAX.presentation },
            { variant: "total", label: SCORE_LABELS.total, value: 0, max: SCORE_MAX.total },
        ],
    },
];

/* ------------------------------------------------------ جدول‌های Scoring */

/**
 * دادهٔ خام پیشنهادی Scoring — همان شکل بخش ۳.۳ سند:
 * execution_mode + judge_scores[] per seat + نتیجهٔ هر اجرا.
 */
export const proposedScoring = {
    execution_mode: "SINGLE",
    judge_count: 5,
    performances: [
        { id: "perf-1", label: "فرم ۱ — تای گوک ۵ جو" },
        { id: "perf-2", label: "فرم ۲ — کیبورد پومسه" },
    ],
    judge_scores: [
        { seat: 1, values: [4.562, 4.71] },
        { seat: 2, values: [4.61, 4.68] },
        { seat: 3, values: [4.485, 4.655] },
        { seat: 4, values: [4.575, 4.725] },
        { seat: 5, values: [4.53, 4.69] },
    ],
    results: [4.552, 4.692],
};

export const proposedScoringSeven = {
    ...proposedScoring,
    judge_count: 7,
    judge_scores: [
        { seat: 1, values: [4.56, 4.71] },
        { seat: 2, values: [4.61, 4.68] },
        { seat: 3, values: [4.485, 4.655] },
        { seat: 4, values: [4.575, 4.725] },
        { seat: 5, values: [4.53, 4.69] },
        { seat: 6, values: [4.595, 4.7] },
        { seat: 7, values: [4.54, 4.67] },
    ],
    results: [4.556, 4.69],
};

/**
 * شکل‌دادهٔ جدول‌های Scoring: هر ردیف = یک داور با نمره‌هایش (ترتیب صندلی J1..Jn).
 * آینه‌سازی Double داخل کامپوننت انجام می‌شود، پس داده همیشه در ترتیب صندلی می‌آید.
 */
const withSeatLabels = (seats) =>
    seats.map((seat, index) => ({ label: `J${index + 1}`, ...seat }));

/** نمره‌های پنج داور — دقیقاً مقادیر طرح SINGLE/DOUBLE */
const designSeats = [
    { accuracy: 4.0, eoe: 1.7, sp: 1.9, rp: 1.8, presentation: 5.4 },
    { accuracy: 3.8, eoe: 1.5, sp: 1.6, rp: 1.6, presentation: 4.9 },
    { accuracy: 3.3, eoe: 1.5, sp: 1.7, rp: 1.6, presentation: 4.8 },
    { accuracy: 3.0, eoe: 1.1, sp: 1.5, rp: 1.5, presentation: 4.1 },
    { accuracy: 2.4, eoe: 0.9, sp: 1.3, rp: 1.2, presentation: 3.4 },
];

/** هفت داور — برای حالت ریسپانسیو ۷ صندلی */
const designSeatsSeven = [
    ...designSeats,
    { accuracy: 2.9, eoe: 1.0, sp: 1.4, rp: 1.4, presentation: 4.0 },
    { accuracy: 3.6, eoe: 1.6, sp: 1.6, rp: 1.7, presentation: 4.6 },
];

export const singleFive = { judges: withSeatLabels(designSeats) };
export const singleFiveCompact = {
    judges: withSeatLabels(designSeats),
    showSubScores: false,
};
export const singleSeven = { judges: withSeatLabels(designSeatsSeven) };

/** حالت خالی: display=true و هنوز judge_scores نداریم */
export const singleEmpty = { judgeCount: 5 };

/**
 * زیرستون‌های Freestyle طبق طرح: ۴ مقدار زیر ACCURACY و ۶ مقدار زیر PRESENTATION.
 * `accuracy`/`presentation` (تک‌ستونی) برای حالت سه‌ستونه‌اند — مقدار demo؛
 * منبع نهایی از بک‌اند می‌آید و در قرارداد ثبت می‌شود.
 */
const freestyleSeat = () => ({
    accuracy: 3.2,
    accuracyParts: [0.8, 0.9, 0.8, 0.7],
    presentation: 4.9,
    presentationParts: [0.9, 0.8, 0.8, 0.9, 0.7, 0.8],
});

export const freestyleFive = {
    judges: withSeatLabels(Array.from({ length: 5 }, freestyleSeat)),
};
export const freestyleFiveCompact = {
    judges: withSeatLabels(Array.from({ length: 5 }, freestyleSeat)),
    showSubScores: false,
};
export const freestyleSeven = {
    judges: withSeatLabels(Array.from({ length: 7 }, freestyleSeat)),
};

/**
 * Double: دو جدول جدا (یکی برای هر سمت) کنار هم رندر می‌شوند.
 * داده در ترتیب صندلی است؛ `side="hong"` داخل کامپوننت آینه می‌شود،
 * پس دادهٔ سمت راست عمداً برعکس است تا خروجی دقیقاً مثل تصویر طرح باشد
 * (سمت راست: ستون بیرونی J1 است و عدد بیرونی‌ترین سمتِ چپِ همان ستون است).
 */
const designSeatsHong = [...designSeats].reverse();
const designSeatsSevenHong = [...designSeatsSeven].reverse();

export const doubleFiveChung = {
    side: "chung",
    judges: withSeatLabels(designSeats),
};
export const doubleFiveHong = {
    side: "hong",
    judges: withSeatLabels(designSeatsHong),
};
export const doubleFiveCompact = {
    side: "chung",
    judges: withSeatLabels(designSeats),
    showSubScores: false,
};
export const doubleSevenChung = {
    side: "chung",
    judges: withSeatLabels(designSeatsSeven),
};
export const doubleSevenHong = {
    side: "hong",
    judges: withSeatLabels(designSeatsSevenHong),
};

/* ------------------------------------------------- Ranking / TopFour */

/**
 * شکل پیشنهادی placements[] (بخش ۴ سند) — برای هر دو قالب round_robin و knockout.
 * `round_scores` مصوب جدید است: نمرهٔ کل هر راند [R-1, R-2] برای ستون‌های RankingTable.
 */
export const placementsRoundRobin = [
    { rank: 1, entry_id: 101, name: "علی رضایی", score: 27, round_scores: [9.25, 9.36], country: "ایران", country_code: "IRI", flag_url: flags.ir },
    { rank: 2, entry_id: 102, name: "کیم چان‌هی", score: 25, round_scores: [9.05, 8.91], country: "کرهٔ جنوبی", country_code: "KOR", flag_url: flags.kr },
    { rank: 3, entry_id: 103, name: "امره ییلدیز", score: 22, round_scores: [8.93, 8.82], country: "ترکیه", country_code: "TUR", flag_url: flags.tr },
    { rank: 4, entry_id: 104, name: "کنیچیرو تاناکا", score: 19, round_scores: [8.61, 8.9], country: "ژاپن", country_code: "JPN", flag_url: flags.jp },
    { rank: 5, entry_id: 105, name: "پییر دوبوا", score: 15, round_scores: [8.24, 8.65], country: "فرانسه", country_code: "FRA", flag_url: flags.fr },
    { rank: 6, entry_id: 106, name: "النور حسین‌اف", score: 11, round_scores: [8.1, 8.3], country: "قزاقستان", country_code: "KAZ", flag_url: flags.kz },
];

export const placementsKnockout = [
    { rank: 1, entry_id: 201, name: "کنیچیرو تاناکا", score: 5, round_scores: [9.41, 9.38], country: "ژاپن", country_code: "JPN", flag_url: flags.jp },
    { rank: 2, entry_id: 202, name: "علی رضایی", score: 4, round_scores: [9.3, 9.25], country: "ایران", country_code: "IRI", flag_url: flags.ir },
    { rank: 3, entry_id: 203, name: "کیم چان‌هی", score: 3, round_scores: [9.15, 9.2], country: "کرهٔ جنوبی", country_code: "KOR", flag_url: flags.kr },
    { rank: 4, entry_id: 204, name: "امره ییلدیز", score: 2, round_scores: [8.99, 9.05], country: "ترکیه", country_code: "TUR", flag_url: flags.tr },
];

/** حالت‌های تک‌ردیف RankingRow — با props جدید (دو ستون راند، بدون highlight) */
export const extraRankingRows = [
    {
        title: "بدون پرچم — placeholder داخل قاب پرچم (کد کشور)",
        props: { rank: 4, name: "WANG LI", country: "چین", countryCode: "CHN", flagUrl: "", roundScores: [8.61, 8.9] },
    },
    {
        title: "راند دوم خالی — «—» نمایش داده می‌شود",
        props: { rank: 5, name: "J.WEIDE VAN DER", countryCode: "NL", roundScores: [8.24] },
    },
    {
        title: "بدون country/country_code — بلوک کشور حذف می‌شود",
        props: { rank: 3, name: "بدون کشور", roundScores: [] },
    },
];

/** ردیف‌های طرح TOP-4 (مقادیر از تصویر طرح خوانده شده‌اند) */
export const topFourRows = [
    { rank: 1, name: "a.salmani", country: "ایران", countryCode: "IRI", flagUrl: flags.ir, score: 9.36 },
    { rank: 2, name: "m.yilmaz", country: "ترکیه", countryCode: "TUR", flagUrl: flags.tr, score: 8.91 },
    { rank: 3, name: "c.senft", country: "آلمان", countryCode: "DEU", flagUrl: flags.de, score: 8.82 },
    { rank: 4, name: "a.m.borras", country: "اسپانیا", countryCode: "ESP", flagUrl: flags.es, score: 8.9 },
];

/** حالت‌های تک‌ردیف TopFourRow */
export const extraTopFourRows = [
    {
        title: "بدون پرچم — placeholder داخل قاب پرچم",
        props: { rank: 2, name: "m.yilmaz", countryCode: "TUR", flagUrl: "", score: 8.91 },
    },
    {
        title: "بدون country/country_code — بلوک کشور حذف می‌شود · بدون نمره «—»",
        props: { rank: 3, name: "بدون کشور", score: null },
    },
];


/* ------------------------------------------------------------------ Draw page */

export const drawSingle = {
    mode: "single",
    stage: "SEMI-FINAL",
    title: "Title",
    category: "MALE - UNDER 30",
    entries: [
        {
            id: 1,
            number: 1,
            name: "A.SALMANI",
            country: "ایران",
            country_code: "IRI",
            flag_url: flags.ir,
            side: "chung",
        },
    ],
    forms: [
        { round_label: "R - 1", form_number: 1, symbol_key: 5, form_name: "TAEGUK 5" },
        { round_label: "R - 2", form_number: 2, symbol_key: 9, form_name: "KORYO" },
    ],
};

export const drawDouble = {
    ...drawSingle,
    mode: "double",
    entries: [
        drawSingle.entries[0],
        {
            id: 2,
            number: 2,
            name: "A.NAJAFABADI",
            country: "ایران",
            country_code: "IRI",
            flag_url: flags.ir,
            side: "hong",
        },
    ],
};

/* ------------------------------------- خلاصهٔ فیلدهای Proposed Contract */

export const contractFields = [
    { n: 1, field: "logoUrl", shape: "string | null روی snapshot", consumers: "همهٔ صفحات (header)" },
    { n: 2, field: "entry.photo_url", shape: "حذف موقت — تا اطلاع بعدی تأمین نشود", consumers: "— (عکس ورزشکار فعلاً در هیچ صفحه‌ای نمایش داده نمی‌شود)" },
    { n: 3, field: "entry.country / entry.country_code / entry.flag_url", shape: "string روی entry — country_code از نوع ISO alpha-3 انگلیسی", consumers: "هر جا NationalityBadge (AthleteInfo, RankingRow)" },
    { n: 4, field: "entry.number", shape: "int روی entry", consumers: "Standby, Scoring" },
    { n: 5, field: "execution_duration_seconds", shape: "int روی تنظیمات مسابقه", consumers: "Standby, LiveBoard (Timer)" },
    { n: 6, field: "execution_mode", shape: "SINGLE | DOUBLE | SINGLE-FREESTYLE", consumers: "Scoring (انتخاب جدول)" },
    { n: 7, field: "judge_scores[]", shape: "[{seat, values[]}] روی performance", consumers: "Scoring (ستون‌های قاضی)" },
    { n: 8, field: "accuracy_score", shape: "number ۰..۴ — سقف ثابت، ScoreItem", consumers: "Result, Scoring (ScoreCard/ScoreItem)" },
    { n: 9, field: "presentation_score", shape: "number ۰..۶ — سقف ثابت، ScoreItem", consumers: "Result, Scoring (ScoreCard/ScoreItem)" },
    { n: 9, field: "total_score", shape: "number ۰..۱۰ = مجموع دو مورد قبل؛ از Backend می‌آید نه جمع فرانت", consumers: "Result, Scoring (ScoreCard/ScoreItem)" },
    { n: 9, field: "is_winner", shape: "bool — کادر زرد فقط روی total برنده", consumers: "Result" },
    { n: 10, field: "placements[]", shape: "[{rank, entry_id, name, score, country, country_code, flag_url}] — مدال از روی rank نمایش داده می‌شود", consumers: "Ranking, TopFour" },
    { n: 11, field: "Draw.output_snapshot", shape: "خروجی قرعه (ترتیب اجرا/جفت‌ها)", consumers: "Draw" },
    { n: 12, field: "entry_members[]", shape: "[{name, club, position}] روی entry", consumers: "Draw, Standby, Scoring" },
    { n: 13, field: "draw_timing", shape: "timestamp", consumers: "Draw" },
    { n: 14, field: "form_number", shape: "int ۱..۱۸ — کلید سمبل فرم (مسیر تصویر در فرانت)", consumers: "FormBadge در Standby, LiveBoard, Scoring, Result, Draw" },
    { n: 15, field: "courtId (LiveBoard)", shape: "query param رسمی", consumers: "LiveBoard" },
    { n: 16, field: "timeline (LiveBoard)", shape: "previous / current / next", consumers: "LiveBoard" },
    { n: 17, field: "placements[].round_scores", shape: "[number, number] — نمرهٔ کل هر راند [R-1, R-2] روی هر ردیف", consumers: "Ranking (ستون‌های نمرهٔ راند)" },
    { n: 18, field: "stage / category", shape: "string — برچسب هدر صفحه (مثل SEMI-FINAL · MALE-UNDER 30)؛ جدول‌ها هدر ندارند", consumers: "هدر صفحات Ranking, TopFour" },
];
