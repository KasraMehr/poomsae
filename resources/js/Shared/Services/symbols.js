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

/** مسیر سمبل فرم؛ اگر کلید ناشناخته باشد null (کامپوننت چیزی رندر نمی‌کند). */
export const formSymbol = (key) => (FORM_SYMBOLS[key] ? resolveAsset(FORM_SYMBOLS[key]) : null);

/** مسیر مدال بر اساس رتبه؛ رتبه‌های خارج از ۱..۵ مدال نمی‌گیرند. */
export const medalSymbol = (rank) => (MEDAL_SYMBOLS[rank] ? resolveAsset(MEDAL_SYMBOLS[rank]) : null);