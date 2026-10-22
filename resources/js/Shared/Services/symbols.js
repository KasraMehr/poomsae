/**
 * نگاشت asset های ثابت پروژه: سمبل فرم‌ها و مدال‌ها.
 *
 * snapshot فقط «کلید دامنه» را می‌دهد (`form_number` یا `rank`) و مسیر فایل از همین map می‌آید؛
 * بنابراین افزودن asset جدید فقط یعنی فایل را در `public/images/...` بگذاری و یک خط اینجا اضافه کنی
 * — بدون تغییر در کامپوننت‌ها و بدون URL در قرارداد Backend.
 *
 * فایل‌ها بیرون از باندل و در `public/` قرار می‌گیرند.
 */

/** سمبل فرم‌ها: ۱..۸ شماره‌دار (پومسه)، ۹..۱۸ مجموعهٔ دوم (فعلاً کلید عددی). */
export const FORM_SYMBOLS = {
    1: "/images/symbols/form-1.webp",
    2: "/images/symbols/form-2.webp",
    3: "/images/symbols/form-3.webp",
    4: "/images/symbols/form-4.webp",
    5: "/images/symbols/form-5.webp",
    6: "/images/symbols/form-6.webp",
    7: "/images/symbols/form-7.webp",
    8: "/images/symbols/form-8.webp",
    9: "/images/symbols/form-9.webp",
    10: "/images/symbols/form-10.webp",
    11: "/images/symbols/form-11.webp",
    12: "/images/symbols/form-12.webp",
    13: "/images/symbols/form-13.webp",
    14: "/images/symbols/form-14.webp",
    15: "/images/symbols/form-15.webp",
    16: "/images/symbols/form-16.webp",
    17: "/images/symbols/form-17.webp",
    18: "/images/symbols/form-18.webp",
};

/** مدال‌ها: ۱ طلا، ۲ نقره، ۳ برنز، ۴ و ۵ خاکستری. مدال از روی `rank` انتخاب می‌شود. */
export const MEDAL_SYMBOLS = {
    1: "/images/medals/medal-1.webp",
    2: "/images/medals/medal-2.webp",
    3: "/images/medals/medal-3.webp",
    4: "/images/medals/medal-4.webp",
    5: "/images/medals/medal-5.webp",
};

/**
 * حل‌کنندهٔ مسیر asset.
 *
 * پیش‌فرض همان مسیر خام `public/` است، چون در برنامهٔ اصلی فایل‌ها را وب‌سرور از `public/` سرو می‌کند.
 * تنها استثنا `scoreboard-lab` است: آن صفحه مستقیم روی dev server خودِ Vite باز می‌شود و
 * Vite پوشهٔ `public/` را سرو نمی‌کند، پس لَب پیش از mount این تابع را صدا می‌زند
 * تا مسیرها را به URL باندل‌شدهٔ خودش تبدیل کند. در production این قلاب دست‌نخورده می‌ماند.
 */
let resolveAsset = (path) => path;

/** نصب حل‌کنندهٔ سفارشی (فقط برای محیط‌هایی مثل لَب که `public/` را سرو نمی‌کنند). */
export const setAssetResolver = (resolver) => {
    resolveAsset = typeof resolver === "function" ? resolver : (path) => path;
};

export const POOMSAE_NAME_TO_KEY = {
    "taegeuk 1": 1, "taeguk 1": 1, "taegeuk 1 jang": 1, "taeguk 1 jang": 1,
    "taegeuk 2": 2, "taeguk 2": 2, "taegeuk 2 jang": 2, "taeguk 2 jang": 2,
    "taegeuk 3": 3, "taeguk 3": 3, "taegeuk 3 jang": 3, "taeguk 3 jang": 3,
    "taegeuk 4": 4, "taeguk 4": 4, "taegeuk 4 jang": 4, "taeguk 4 jang": 4,
    "taegeuk 5": 5, "taeguk 5": 5, "taegeuk 5 jang": 5, "taeguk 5 jang": 5,
    "taegeuk 6": 6, "taeguk 6": 6, "taegeuk 6 jang": 6, "taeguk 6 jang": 6,
    "taegeuk 7": 7, "taeguk 7": 7, "taegeuk 7 jang": 7, "taeguk 7 jang": 7,
    "taegeuk 8": 8, "taeguk 8": 8, "taegeuk 8 jang": 8, "taeguk 8 jang": 8,
    "koryo": 9, "keumgang": 10, "taebaek": 11, "pyongwon": 12,
    "shipjin": 13, "sipjin": 13, "jitae": 14, "chonkwon": 15,
    "hansu": 16, "ilyo": 17,
    "فرم ۱": 1, "فرم 1": 1, "1": 1, "۱": 1,
    "فرم ۲": 2, "فرم 2": 2, "2": 2, "۲": 2,
    "فرم ۳": 3, "فرم 3": 3, "3": 3, "۳": 3,
    "فرم ۴": 4, "فرم 4": 4, "4": 4, "۴": 4,
    "فرم ۵": 5, "فرم 5": 5, "5": 5, "۵": 5,
    "فرم ۶": 6, "فرم 6": 6, "6": 6, "۶": 6,
    "فرم ۷": 7, "فرم 7": 7, "7": 7, "۷": 7,
    "فرم ۸": 8, "فرم 8": 8, "8": 8, "۸": 8,
    "کوریو": 9, "فرم ۹": 9, "فرم 9": 9, "9": 9, "۹": 9,
    "کومگانگ": 10, "فرم ۱۰": 10, "فرم 10": 10, "10": 10, "۱۰": 10,
    "ته بک": 11, "ته‌بک": 11, "فرم ۱۱": 11, "فرم 11": 11, "11": 11, "۱۱": 11,
    "پیونگ وان": 12, "پیونگ‌وان": 12, "فرم ۱۲": 12, "فرم 12": 12, "12": 12, "۱۲": 12,
    "شیپ جین": 13, "شیپ‌جین": 13, "فرم ۱۳": 13, "فرم 13": 13, "13": 13, "۱۳": 13,
    "جی ته": 14, "جی‌ته": 14, "فرم ۱۴": 14, "فرم 14": 14, "14": 14, "۱۴": 14,
    "چون کوان": 15, "چون‌کوان": 15, "فرم ۱۵": 15, "فرم 15": 15, "15": 15, "۱۵": 15,
    "هانسو": 16, "فرم ۱۶": 16, "فرم 16": 16, "16": 16, "۱۶": 16,
    "ایل یو": 17, "ایل‌یو": 17, "فرم ۱۷": 17, "فرم 17": 17, "17": 17, "۱۷": 17,
};

/** مسیر سمبل فرم؛ اگر کلید ناشناخته باشد null (کامپوننت چیزی رندر نمی‌کند). */
export const formSymbol = (key) => {
    if (key == null) return null;
    const str = String(key).trim();
    const resolvedKey = FORM_SYMBOLS[key] ? key : (POOMSAE_NAME_TO_KEY[str.toLowerCase()] ?? POOMSAE_NAME_TO_KEY[str] ?? null);
    return (resolvedKey && FORM_SYMBOLS[resolvedKey]) ? resolveAsset(FORM_SYMBOLS[resolvedKey]) : null;
};

/** مسیر مدال بر اساس رتبه؛ رتبه‌های خارج از ۱..۵ مدال نمی‌گیرند. */
export const medalSymbol = (rank) => (MEDAL_SYMBOLS[rank] ? resolveAsset(MEDAL_SYMBOLS[rank]) : null);