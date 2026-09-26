# قرارداد بک‌اند اجرای مسابقه پومسه

این سند مرز بین رابط روز مسابقه و منطق بک‌اند را برای پومسه استاندارد انفرادی با ۵ یا ۷ داور مشخص می‌کند. منبع اجرایی فعلی کلاس‌های `app/Actions` و تست‌های Feature هستند. تیمی و ابداعی خارج از این قراردادند.

## ماشین وضعیت

هر اجرا (`performance`) دقیقاً این مسیر را طی می‌کند:

```text
pending -> running -> scoring -> approved
                     \-> cancelled (فقط تصمیم ثبت‌شدهٔ walkover)
```

- `start`: فقط اجرای pending، وقتی زمین اجرای باز دیگری ندارد و فرم قبلی همان ورزشکار تأیید شده است.
- `finish`: فقط اجرای running؛ زمان پایان ثبت می‌شود و داورها اجازه ارسال نمره می‌گیرند.
- `approve`: فقط scoring و پس از دریافت دقیقاً یک نمره معتبر از تمام ۵/۷ صندلی. نتیجه محاسبه، منتشر و اجرا قفل می‌شود.
- اصلاح نمره فقط پیش از approve، با revision مورد انتظار و دلیل اجباری انجام می‌شود.
- هر command باید `expected_version` داشته باشد. نسخه قدیمی با خطای تعارض رد می‌شود.

## endpointهای وب فعلی

همه مسیرها session، CSRF، کاربر فعال و تعلق منبع به همان مسابقه را بررسی می‌کنند. پاسخ mutation در نسخه Inertia redirect همراه flash/error است.

| عملیات | درخواست | نقش | payload اصلی |
| --- | --- | --- | --- |
| snapshot میز اجرا | `GET /tournaments/{id}` | manager/operator | — |
| شروع/پایان/تأیید | `POST /tournaments/{id}/performances/{performance}/command` | manager/operator | `command`, `expected_version` |
| ثبت/اصلاح نمره | `POST /tournaments/{id}/performances/{performance}/scores` | judge تخصیص‌یافته | `request_id`, `expected_version`, `expected_revision`, `accuracy`, `presentation`, `reason?` |
| نمره جایگزین | `POST /tournaments/{id}/performances/{performance}/proxy-scores` | manager/operator | موارد نمره به‌همراه `judge_assignment_id` و `reason` اجباری |
| تساوی یا walkover | `POST /tournaments/{id}/bouts/{bout}/resolve` | manager/operator | `winner_entry_id`, `reason`، و برای walkover: `decision_type=walkover`, `confirmed=true` |
| خروجی نتیجه | `GET /tournaments/{id}/results.csv` | manager/operator | — |

برای API موبایل، همین Actionها باید از کنترلر JSON فراخوانی شوند؛ منطق وضعیت یا محاسبه نباید در کنترلر دوم تکرار شود. خطاهای پیشنهادی JSON: `401` بدون ورود، `403` نقش نامعتبر، `404` منبع خارج از مسابقه، `409` نسخه/revision قدیمی، `422` قاعدهٔ دامنه یا validation.

## قرارداد snapshot میز اجرا

prop ریشه `console` شامل موارد زیر است:

- `tournament`: snapshot کامل مسابقه، رده‌ها، دورها، رقابت‌ها و اجراها.
- `courts[]`: `id`, `name`, `active`, `queue`.
- `courts[].active`: اجرای running یا scoring با `entry_name`, `form_name`, `version`, `judge_count`, `submitted_seats[]`, `missing_seats[]`.
- `courts[].queue`: حداکثر پنج اجرای قابل شروع. فرم دوم تا تأیید فرم اول همان ورزشکار وارد صف نمی‌شود.
- `attention`: شمارنده‌های `running`, `waiting_for_scores`, `ready`.

صف فقط یک نمای مشتق‌شده است؛ کلاینت حق ندارد ترتیب یا امکان شروع را منبع حقیقت بداند. Action سرور همه پیش‌شرط‌ها را دوباره کنترل می‌کند.

## محاسبه نمره

مقادیر ورودی با دقت صدم به عدد صحیح تبدیل می‌شوند. برای هر مؤلفه، در صورت فعال بودن `discard_extremes` و وجود حداقل ۵ داور، یک کمینه و یک بیشینه مستقل حذف می‌شود. میانگین مؤلفه‌های باقی‌مانده محاسبه و با هم جمع می‌شود. امتیاز رقابت میانگین نتایج دو فرم است. ذخیره و محاسبه میانی با عدد صحیح انجام می‌شود و تبدیل اعشاری فقط در مرز نمایش/ذخیره نتیجه صورت می‌گیرد.

تساوی به‌صورت خودکار شکسته نمی‌شود. اپراتور باید برنده و دلیل مستند را ثبت کند. walkover نیز confirmation و دلیل می‌خواهد و اجراهای تأییدنشده همان رقابت را لغو می‌کند؛ نتیجه‌های منتشرشده حذف نمی‌شوند.

## همزمانی، تراکنش و رویداد

- mutationهای دامنه داخل transaction اجرا و رکوردهای اصلی با `lockForUpdate` قفل می‌شوند.
- `request_id` ثبت نمره idempotency key است. تکرار payload یکسان همان نتیجه را می‌دهد و استفاده همان UUID با payload متفاوت رد می‌شود.
- نمرهٔ جایگزین فقط برای صندلی بدون score sheet مجاز است و `submitted_by`, `submission_mode=operator_proxy`، revision و audit را ثبت می‌کند.
- score revision و performance version باید در شرط نوشتن باشند تا دو دستگاه نتوانند تغییر همدیگر را بی‌صدا overwrite کنند.
- هر تغییر مهم audit log دارد: actor، action، subject، before/after و زمان.
- پس از commit رویداد `competition.updated` با broadcast driver جدید Mercure روی کانال خصوصی `tournaments.{id}` منتشر می‌شود. انتقال روی SSE است و polling دو ثانیه‌ای fallback رابط است.
- presence channel با نام `tournaments.{id}.judges` اتصال زندهٔ داورها را نشان می‌دهد؛ presence به‌تنهایی مدرک خرابی دستگاه یا مجوز ثبت جایگزین نیست.

## تست و معیار تحویل

پیش از تحویل بک‌اند این فرمان‌ها باید موفق باشند:

```powershell
php artisan test
npm run build
```

سناریوهای الزامی در تست‌ها: جریان کامل ۵ و ۷ داور، retry idempotent، نسخه و revision قدیمی، داور تخصیص‌نیافته، دسترسی بین دو مسابقه، محدودیت یک اجرای باز در زمین، ممنوعیت فرم دوم پیش از فرم اول، حذف min/max، اصلاح با دلیل، تساوی، walkover، bye، صعود دور بعد، پایان مسابقه و CSV امن. تست میز اجرا در `tests/Feature/CompetitionConsoleTest.php` قرارداد active/queue و صندلی‌های باقی‌مانده را تثبیت می‌کند.

## موارد خارج از نسخه حاضر

قوانین رسمی باید پیش از استفاده واقعی توسط مسئول فنی رویداد بازبینی و نسخه‌بندی شوند. تیمی، ابداعی، double elimination، همگام‌سازی آفلاین موبایل، بازیابی خودکار پس از خرابی چند سرور و زمان‌بندی چند زمین هنوز در این قرارداد پیاده نشده‌اند.
