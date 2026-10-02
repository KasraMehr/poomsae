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

const now = Date.now();

export const timerCases = [
    {
        title: "running — شمارش رو به جلو (بدون durationSeconds)",
        props: { running: true, startedAt: now - 74_000, durationSeconds: 0 },
    },
    {
        title: "running — شمارش معکوس با execution_duration_seconds=120",
        props: { running: true, startedAt: now - 45_000, durationSeconds: 120 },
    },
    {
        title: "scoring — ایستا روی مدت اجرا (بدون شمارش)",
        props: { running: false, startedAt: null, durationSeconds: 74 },
    },
    {
        title: "شروع‌نشده — 00:00",
        props: { running: false, startedAt: null, durationSeconds: 0 },
    },
];

/* --------------------------------------------------------- TotalScoreArc */

export const arcCases = [
    {
        title: "بدون دقت — accuracy_score=0 (snapshot فعلی)",
        props: { accuracyScore: 0, totalScore: 0, scoreTypeLabel: "" },
    },
    {
        title: "با دقت و برچسب — accuracy_score + score_type_label (پیشنهادی)",
        props: { accuracyScore: 7.35, totalScore: 45.612, scoreTypeLabel: "امتیاز دقت" },
    },
    {
        title: "سقف — accuracy=10 از 10",
        props: { accuracyScore: 10, totalScore: 62.48, scoreTypeLabel: "امتیاز دقت" },
    },
    {
        title: "بدون برچسب نوع امتیاز",
        props: { accuracyScore: 6.2, totalScore: 41.05, scoreTypeLabel: "" },
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

/** تبدیل judge_scores[] پیشنهادی به ردیف‌های SingleScoringTable */
export function singleRowsFrom(snapshot) {
    return snapshot.performances.map((performance, index) => ({
        key: performance.id,
        label: performance.label,
        scores: snapshot.judge_scores.map((seat) => seat.values[index] ?? null),
        total: snapshot.results?.[index] ?? null,
    }));
}

export const singleFive = { judgeCount: 5, rows: singleRowsFrom(proposedScoring) };
export const singleSeven = { judgeCount: 7, rows: singleRowsFrom(proposedScoringSeven) };

/** وضعیت snapshot فعلی: display=true و scores[] خالی */
export const singleEmpty = {
    judgeCount: 5,
    rows: proposedScoring.performances.map((performance) => ({
        key: performance.id,
        label: performance.label,
        scores: [],
        total: null,
    })),
};

export const freestyleFive = {
    judgeCount: 5,
    rows: [
        {
            key: "fs-1",
            label: "فرم آزاد — اجرای ۱",
            scores: [4.4, 4.5, 4.35, 4.55, 4.45],
            execution: 1.15,
            total: 5.64,
        },
        {
            key: "fs-2",
            label: "فرم آزاد — اجرای ۲",
            scores: [],
            execution: null,
            total: null,
        },
    ],
};

export const freestyleSeven = {
    judgeCount: 7,
    rows: [
        {
            key: "fs-1",
            label: "فرم آزاد — اجرای ۱",
            scores: [4.4, 4.5, 4.35, 4.55, 4.45, 4.48, 4.42],
            execution: 1.15,
            total: 5.665,
        },
        {
            key: "fs-2",
            label: "فرم آزاد — اجرای ۲",
            scores: [4.6, 4.58, 4.62, 4.55, 4.6, 4.57, 4.61],
            execution: 1.3,
            total: 5.9,
        },
    ],
};

export const doubleFive = {
    judgeCount: 5,
    athletes: [
        { key: "chung", name: "علی رضایی (چونگ)" },
        { key: "hong", name: "کیم چان‌هی (هونگ)" },
    ],
    rows: [
        {
            key: "perf-1",
            label: "فرم ۱ — تای گوک ۵ جو",
            scores: {
                chung: [4.5, 4.6, 4.4, 4.55, 4.52],
                hong: [4.6, 4.55, 4.7, 4.62, 4.58],
            },
            totals: { chung: 4.514, hong: 4.61 },
        },
        {
            key: "perf-2",
            label: "فرم ۲ — کیبورد پومسه",
            scores: {
                chung: [4.65, 4.7, 4.62, 4.68, 4.66],
                hong: [4.5, 4.55, 4.48, 4.52, 4.51],
            },
            totals: { chung: 4.662, hong: 4.512 },
        },
    ],
};

export const doubleSeven = {
    ...doubleFive,
    judgeCount: 7,
    rows: [
        {
            key: "perf-1",
            label: "فرم ۱ — تای گوک ۵ جو",
            scores: {
                chung: [4.5, 4.6, 4.4, 4.55, 4.52, 4.57, 4.49],
                hong: [4.6, 4.55, 4.7, 4.62, 4.58, 4.56, 4.61],
            },
            totals: { chung: 4.533, hong: 4.603 },
        },
    ],
};

export const baseTable = {
    judgeCount: 7,
    highlightColumn: "total",
    columns: [
        { key: "accuracy", label: "دقت" },
        { key: "power", label: "قدرت" },
        { key: "total", label: "مجموع" },
    ],
    rows: [
        { key: "r1", label: "اجرای ۱", cells: { accuracy: 4.55, power: 4.2, total: 8.75 } },
        { key: "r2", label: "اجرای ۲", cells: { accuracy: 4.7, power: 4.35, total: 9.05 } },
    ],
};

/* ------------------------------------------------- Ranking / TopFour */

/** شکل پیشنهادی placements[] (بخش ۴ سند) — برای هر دو قالب round_robin و knockout */
export const placementsRoundRobin = [
    { rank: 1, entry_id: 101, name: "علی رضایی", score: 27, country: "ایران", country_code: "IRI", flag_url: flags.ir },
    { rank: 2, entry_id: 102, name: "کیم چان‌هی", score: 25, country: "کرهٔ جنوبی", country_code: "KOR", flag_url: flags.kr },
    { rank: 3, entry_id: 103, name: "امره ییلدیز", score: 22, country: "ترکیه", country_code: "TUR", flag_url: flags.tr },
    { rank: 4, entry_id: 104, name: "کنیچیرو تاناکا", score: 19, country: "ژاپن", country_code: "JPN", flag_url: flags.jp },
    { rank: 5, entry_id: 105, name: "پییر دوبوا", score: 15, country: "فرانسه", country_code: "FRA", flag_url: flags.fr },
    { rank: 6, entry_id: 106, name: "النور حسین‌اف", score: 11, country: "قزاقستان", country_code: "KAZ", flag_url: flags.kz },
];

export const placementsKnockout = [
    { rank: 1, entry_id: 201, name: "کنیچیرو تاناکا", score: 5, country: "ژاپن", country_code: "JPN", flag_url: flags.jp },
    { rank: 2, entry_id: 202, name: "علی رضایی", score: 4, country: "ایران", country_code: "IRI", flag_url: flags.ir },
    { rank: 3, entry_id: 203, name: "کیم چان‌هی", score: 3, country: "کرهٔ جنوبی", country_code: "KOR", flag_url: flags.kr },
    { rank: 4, entry_id: 204, name: "امره ییلدیز", score: 2, country: "ترکیه", country_code: "TUR", flag_url: flags.tr },
];

export const extraRankingRows = [
    {
        title: "highlight=true — حالت منتخب",
        props: { rank: 1, name: "علی رضایی", country: "ایران", countryCode: "IRI", flagUrl: flags.ir, score: "۲۷", highlight: true },
    },
    {
        title: "بدون پرچم — رتبهٔ ۴ (placeholder داخل قاب پرچم)",
        props: { rank: 4, name: "وانگ لی", country: "چین", countryCode: "CHN", flagUrl: "", score: "۱۳", highlight: false },
    },
];

/* ------------------------------------- خلاصهٔ فیلدهای Proposed Contract */

export const contractFields = [
    { n: 1, field: "logoUrl", shape: "string | null روی snapshot", consumers: "همهٔ صفحات (header)" },
    { n: 2, field: "entry.photo_url", shape: "حذف موقت — تا اطلاع بعدی تأمین نشود", consumers: "— (عکس ورزشکار فعلاً در هیچ صفحه‌ای نمایش داده نمی‌شود)" },
    { n: 3, field: "entry.country / entry.country_code / entry.flag_url", shape: "string روی entry — country_code از نوع ISO alpha-3 انگلیسی", consumers: "هر جا NationalityBadge (AthleteInfo, RankingRow)" },
    { n: 4, field: "entry.number", shape: "int روی entry", consumers: "Standby, Scoring" },
    { n: 5, field: "execution_duration_seconds", shape: "int روی تنظیمات مسابقه", consumers: "Standby, LiveBoard (Timer)" },
    { n: 6, field: "execution_mode", shape: "SINGLE | DOUBLE | SINGLE-FREESTYLE", consumers: "Scoring (انتخاب جدول)" },
    { n: 7, field: "judge_scores[]", shape: "[{seat, values[]}] روی performance", consumers: "Scoring (ستون‌های قاضی)" },
    { n: 8, field: "accuracy_score", shape: "number روی نتیجهٔ اجرا", consumers: "Scoring (TotalScoreArc)" },
    { n: 9, field: "score_type_label", shape: "string", consumers: "Scoring (TotalScoreArc)" },
    { n: 10, field: "placements[]", shape: "[{rank, entry_id, name, score, country, country_code, flag_url}] — مدال از روی rank نمایش داده می‌شود", consumers: "Ranking, TopFour" },
    { n: 11, field: "Draw.output_snapshot", shape: "خروجی قرعه (ترتیب اجرا/جفت‌ها)", consumers: "Draw" },
    { n: 12, field: "entry_members[]", shape: "[{name, club, position}] روی entry", consumers: "Draw, Standby, Scoring" },
    { n: 13, field: "draw_timing", shape: "timestamp", consumers: "Draw" },
    { n: 14, field: "form_number", shape: "int ۱..۱۸ — کلید سمبل فرم (مسیر تصویر در فرانت)", consumers: "FormBadge در Standby, LiveBoard, Scoring, Result, Draw" },
    { n: 15, field: "courtId (LiveBoard)", shape: "query param رسمی", consumers: "LiveBoard" },
    { n: 16, field: "timeline (LiveBoard)", shape: "previous / current / next", consumers: "LiveBoard" },
];
