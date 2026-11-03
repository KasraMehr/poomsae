# Poomsae Scoring System

سامانهٔ مدیریت مسابقهٔ پومسه با Laravel 13، Inertia 3 و Vue 3. پنل‌های وب مدیر، اپراتور، داور و نمایشگر RTDS از یک وضعیت مسابقه روی سرور استفاده می‌کنند. API نسخه‌دار برای مصرف‌کنندهٔ موبایل نیز وجود دارد؛ اپ Android جداگانه هنوز جایگزین پنل وب داور در پایلوت فعلی نیست.

## از کجا شروع کنیم؟

| سند | موضوع |
| --- | --- |
| [راهنمای پنل‌ها و جریان زنده](docs/live-panels-handoff.md) | نقش‌ها، URLها، فلو یک بازی، همگام‌سازی، ثبت دستی، تست و محدودیت‌های استقرار |
| [قرارداد فنی بک‌اند](docs/competition-backend-contract.md) | وضعیت‌ها، قواعد دامنه، API و همزمانی |
| [معماری نمایشگر](docs/scoreboard-architecture.md) | کامپوننت‌ها و ساختار Scoreboard |
| [قرارداد نمایشگر](docs/scoreboard-contract.md) | داده‌های مورد نیاز UI نمایشگر |
| [اتصال Android و realtime](docs/android-realtime-integration.md) | API موبایل و Mercure/SSE |

## اجرای محلی

پیش‌نیاز: PHP 8.4، Composer، Node 22 و یک دیتابیس قابل دسترس. دستورهای زیر را از پوشهٔ `poomsae-system` اجرا کنید:

```powershell
composer install
npm ci
Copy-Item .env.example .env  # فقط نصب جدید؛ فایل موجود را بازنویسی نکنید
php artisan key:generate     # فقط نصب جدید
php artisan migrate
npm run build
php artisan app:create-admin admin@example.com --name="Competition Admin"
php artisan serve --host=127.0.0.1 --port=8000
```

برای SQLite در نصب جدید، پیش از `migrate` فایل `database/database.sqlite` را بسازید و `DB_CONNECTION=sqlite` را در `.env` تنظیم کنید. رمز مدیر هنگام اجرای فرمان دریافت می‌شود؛ حساب پیش‌فرض وجود ندارد. برای توسعهٔ فرانت از `npm run dev` استفاده کنید. اگر صف دیتابیسی یا Redis فعال است، worker را با `php artisan queue:work --tries=3` اجرا کنید.

پایلوت آزمایشی فقط در محیط `local` یا `testing`:

```powershell
php artisan db:seed --class=PilotSeeder
```

این seeder یک مسابقه و حساب‌های آزمایشی با رمز تصادفی می‌سازد و رمزها را فقط در همان خروجی فرمان نشان می‌دهد. آن را روی دیتابیس مسابقهٔ واقعی اجرا نکنید.

### اجرای زنده با Mercure در Docker (Windows)

این روش برای تست هم‌زمان پنل داور، اپراتور و RTDS است. Docker Desktop باید روشن باشد. در `.env` این مقادیر را تنظیم کنید؛ `APP_URL` باید دقیقاً با نشانی مرورگر یکی باشد، چون Mercure صادرکنندهٔ توکن را با آن تطبیق می‌دهد:

```dotenv
APP_URL=http://localhost:8000
BROADCAST_CONNECTION=mercure
QUEUE_CONNECTION=database
MERCURE_URL=http://127.0.0.1:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://127.0.0.1:3000/.well-known/mercure
VITE_MERCURE_HUB_URL="${MERCURE_PUBLIC_URL}"
VITE_MERCURE_ENABLED=true
```

`MERCURE_JWT_SECRET` را نیز در `.env` با یک مقدار تصادفی طولانی تنظیم کنید. فایل `compose.mercure.yaml` همین مقدار را برای کانتینر می‌خواند. پس از تغییر `.env`، سرور و worker را از نو راه‌اندازی و فرانت را دوباره اجرا کنید. از ریشهٔ همین پروژه چهار ترمینال باز کنید:

```powershell
# ترمینال ۱: هاب Mercure
docker compose -f compose.mercure.yaml up -d
```

```powershell
# ترمینال ۲: Laravel
php artisan serve --host=localhost --port=8000
```

```powershell
# ترمینال ۳: Vite
npm run dev
```

```powershell
# ترمینال ۴: ارسال رویدادهای صف
php artisan queue:work --tries=3
```

سپس `http://localhost:8000` را باز کنید. برای دیدن وضعیت هاب از `docker compose -f compose.mercure.yaml ps` استفاده کنید. اگر رویدادها در worker با `FAIL` ثبت شدند، `storage/logs/laravel.log` و `docker compose -f compose.mercure.yaml logs --tail=50 mercure` را بررسی کنید. خطای `401` با پیام `untrusted issuer` معمولاً یعنی `APP_URL` با صادرکنندهٔ مورد اعتماد در کانتینر هماهنگ نیست؛ بعد از اصلاح `.env`، `docker compose -f compose.mercure.yaml up -d` را دوباره اجرا کنید. `DONE` یعنی ارسال کار به هاب موفق بوده است؛ دریافت آن در مرورگر را با تغییر نمره و به‌روزرسانی بدون رفرش پنل اپراتور و RTDS بررسی کنید.

برای ساخت دادهٔ تکرارپذیر تست، فقط در محیط `local` یا `testing` اجرا کنید:

```powershell
php artisan db:seed --class=CompetitionPlaygroundSeeder
```

این سیدر ۱۳ سناریوی انفرادی، زوجی، تیمی و ابداعی با دو زمین و پنل‌های داوری مستقل می‌سازد. حساب‌های `playground.admin@example.test`، `playground.operator@example.test`، `playground.display@example.test` و `playground.judge01@example.test` تا `playground.judge14@example.test` همگی رمز `demoJudge123` دارند. اجرای دوبارهٔ سیدر مسابقهٔ موجود را دست‌نخورده می‌گذارد و دادهٔ تکراری نمی‌سازد. این حساب‌ها فقط برای تست محلی‌اند.

فرمان `php artisan poomsae:realtime --hub-binary="C:\مسیر\واقعی\mercure.exe"` روش **جایگزین** Docker است، نه ترمینال پنجم. این فرمان خودش Laravel، Mercure و worker را اجرا می‌کند؛ فایل اجرایی واقعی Mercure و تنظیمات same-origin پورت ۸۰۰۰ مطابق `.env.example` لازم دارد. مسیر `C:\path\to\mercure.exe` نمونه است و بدون نصب فایل کار نمی‌کند.

## نقش‌ها و مسیر یک مسابقه

| نقش | صفحهٔ ورود پس از انتخاب مسابقه | مسئولیت |
| --- | --- | --- |
| مدیر | `/tournaments/{id}/setup` | زمین، اعضا، رده، ثبت‌نام، قرعه، گزارش و وضعیت سیستم |
| اپراتور | `/tournaments/{id}` | شروع/پایان اجرا، بررسی نمره‌ها، تأیید و انتشار نتیجه، ثبت دستی اضطراری |
| داور | `/tournaments/{id}/judge` | مشاهدهٔ اجرای تخصیص‌داده‌شده و ارسال نمره |
| نمایشگر RTDS | `/tournaments/{id}/rtds?court={courtId}` | نمایش خودکار صف، فراخوان، اجرا، نتیجه، برنده و رتبه‌بندی همان زمین |

هر حساب عضو عادی فقط یک نوع پنل دارد. مسیرهای صفحه و عملیات روی سرور با نقش بررسی می‌شوند. مدیر و اپراتور نیز پنل جدا دارند؛ مدیر از صفحهٔ آماده‌سازی وارد عملیات اپراتور نمی‌شود. حساب نمایشگر فقط دادهٔ مجاز نمایش عمومی را دریافت می‌کند.

اپراتور اجرا را شروع و تمام می‌کند. داوران نمره را روی تبلت یا گوشی افقی در پنل وب می‌فرستند. اپراتور پس از تکمیل نمره‌ها نتیجه را تأیید می‌کند. RTDS فراخوان را به صورت **نمایشی و خودکار** انجام می‌دهد؛ فرمانی برای «فراخوان» در وضعیت مسابقه ثبت نمی‌شود. RTDS از کامپوننت‌های موجود `resources/js/Pages/Scoreboard` استفاده می‌کند و هر زمین URL مستقل دارد. جزئیات زمان‌بندی و ترتیب دقیق در [راهنمای پنل‌ها](docs/live-panels-handoff.md) آمده است.

اگر تبلت داور از کار بیفتد، اپراتور می‌تواند از پنل خودش نمرهٔ جایگزین را با دلیل ثبت کند؛ هویت ثبت‌کننده، نسخه و سابقهٔ تغییرات نگهداری می‌شوند. نمرهٔ منتشرنشده در RTDS به عنوان نتیجهٔ رسمی نمایش داده نمی‌شود.

## بررسی قبل از پوش یا استقرار

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

تست‌های خودکار مسیرهای نقش‌ها، جریان مسابقه، انتشار نتیجه، نمایشگر و API را پوشش می‌دهند. **پاس شدن تست‌ها جای آزمون هم‌زمان روی سه دستگاه در شبکهٔ واقعی سالن را نمی‌گیرد.** قبل از استفاده در مسابقه، اتصال داور، اپراتور و RTDS، قطع و وصل شبکه، تنظیمات Mercure/queue، پشتیبان‌گیری و بازیابی دیتابیس، و اندازهٔ واقعی مانیتور و تبلت را بررسی کنید. وقتی Mercure فعال نباشد، داده‌ها با polling تازه می‌شوند؛ حضور آنلاین دستگاه داور در این حالت قابل تأیید نیست.

برای پیکربندی MySQL، Redis و Mercure و محدودیت‌های عملیاتی به [راهنمای پنل‌ها](docs/live-panels-handoff.md) و [راهنمای Android/realtime](docs/android-realtime-integration.md) مراجعه کنید.
