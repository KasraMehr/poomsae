/**
 * لایهٔ دارایی‌ها برای scoreboard-lab.
 *
 * چرا این لایه جدا لازم است:
 * `laravel-vite-plugin` مقدار `publicDir` را `false` می‌کند، پس dev server خود Vite
 * هیچ فایلی از `public/` را سرو نمی‌کند (حتی `/favicon.ico` هم ۴۰۴ می‌دهد).
 * در اپ واقعی این مشکلی نیست چون وب‌سرور/‏`php artisan serve` فایل‌ها را از `public/` می‌دهد،
 * اما لَب مستقیم روی پورت Vite باز می‌شود و مسیرهای `/images/...` در آن می‌شکنند.
 *
 * اینجا همان مسیرهای `Shared/Services/symbols.js` را به URL قابل سرو توسط Vite نگاشت می‌کنیم.
 * منبع حقیقتِ نام/کلید همچنان `symbols.js` است — این فقط لایهٔ resolve برای محیط لَب.
 *
 * از `import.meta.glob` استفاده شده (نه `new URL(...)`) چون Vite باید مسیرها را
 * ایستا ببیند تا بتواند فایل را باندل/سرو کند.
 */

import {
    FORM_SYMBOLS,
    MEDAL_SYMBOLS,
    setAssetResolver,
} from "../Shared/Services/symbols";

const files = import.meta.glob("../../../public/images/**/*.{webp,png,jpg,jpeg,avif,svg}", {
    eager: true,
    query: "?url",
    import: "default",
});

/** کلید glob → مسیر وب (`/images/...`) تا با `symbols.js` تطبیق داده شود. */
const byWebPath = Object.fromEntries(
    Object.entries(files).map(([file, url]) => [file.replace(/^(\.\.\/)+public/, ""), url])
);

/** مسیر `public/images/...` را به URL قابل سرو توسط Vite تبدیل می‌کند. */
const assetUrl = (path) => byWebPath[path] ?? null;

/** نوار همهٔ کلیدهای موجود در map برای کنترل assetها. */
export const formSymbolStrip = Object.entries(FORM_SYMBOLS).map(([key, path]) => ({
    key,
    src: assetUrl(path),
}));

/**
 * نصب حل‌کننده در سطح ماژول (side effect).
 *
 * عمداً هنگام evaluate شدن همین فایل انجام می‌شود، نه داخل mount: fixture های `data.js`
 * در سطح ماژول `formSymbol()` را صدا می‌زنند، پس resolver باید قبل از آن فعال باشد.
 * کافی است `main.js` این فایل را قبل از `App.vue` ایمپورت کند.
 */
setAssetResolver(assetUrl);

/** مسیر سمبل فرم؛ کلید ناشناخته یا asset گم‌شده → null. */
export const formSymbol = (key) => (FORM_SYMBOLS[key] ? assetUrl(FORM_SYMBOLS[key]) : null);

/** مسیر مدال بر اساس رتبه؛ رتبه‌های خارج از ۱..۵ مدال نمی‌گیرند. */
export const medalSymbol = (rank) => (MEDAL_SYMBOLS[rank] ? assetUrl(MEDAL_SYMBOLS[rank]) : null);