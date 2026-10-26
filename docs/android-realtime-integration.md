# راهنمای اتصال اپلیکیشن Android و فرانت‌اند به بک‌اند (REST API و Mercure SSE)

> این سند راهنمای API و طرح اتصال Android است؛ به معنی build یا اتصال عملی اپ Android جداگانه در این برنچ نیست. پایلوت فعلی از [پنل وب داور و فلو RTDS](live-panels-handoff.md) استفاده می‌کند. هر بخش این سند که جرایم زمانی یا انطباق رسمی را فرض می‌کند، باید با کد و تست‌های فعلی تطبیق داده شود.

این مستند راهنمای کامل چرخهٔ نمره‌دهی مسابقات پومسه تکواندو است؛ از لحظهٔ ورود نمرات توسط داوران روی تبلت‌های اندرویدی، تا تایید نهایی نمرات توسط اپراتور، محاسبهٔ نمرات نهایی طبق قوانین رسمی پومسه (حذف کمترین و بیشترین، میانگین‌گیری، جرایم زمانی) و ارسال زنده به فرانت‌اند (نمایشگرهای سالن Scoreboard و RTDS).

---

## ۱. نقشه راه و چرخهٔ حیات مسابقه (Workflow & Architecture)

```text
+-----------------------+
|  تبلت داوران (Android) |
|   (صندلی‌های J1 تا J7)  |
+-----------+-----------+
            |
            | ۱. ارسال نمرات هر داور با REST (یا ارسال دسته‌ای)
            v
+-----------------------+     ۲. نمایش دریافت نمره‌ها در کنسول     +----------------------+
|     بک‌اند لاراول     | -------------------------------------> | تبلت یا پنل اپراتور  |
|  (اعتبارسنجی و ثبت موقت)|                                        | (مشاهده صندلی‌ها)    |
+-----------+-----------+                                        +----------+-----------+
            ^                                                               |
            | ۳. تأیید نهایی اپراتور (REST API: command: approve)            |
            +---------------------------------------------------------------+
            |
            | ۴. محاسبه نمره نهایی (حذف min/max، میانگین، اعمال جریمه‌ها)
            | ۵. قفل شدن اجرا (status: approved) و ذخیره نتیجه قطعی (Result)
            | ۶. انتشار رویداد Real-time با Mercure SSE (Server-Sent Events)
            v
+---------------------------------------------------------------------------------------+
|                      فرانت‌اند و نمایشگرهای سالن (Scoreboard / RTDS)                  |
|          دریافت لحظه‌ای رویداد، استعلام Snapshot جدید و نمایش نمره قطعی و برنده       |
+---------------------------------------------------------------------------------------+
```

---

## ۲. احراز هویت و سطوح دسترسی (Authentication)

برای ارتباط با API، کلاینت اندروید باید ابتدا توکن دریافت کند:

```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "operator@example.com",
  "password": "StrongPassword123!",
  "device_name": "Court 1 / Operator Tablet"
}
```

### پاسخ سرور (201 Created):
```json
{
  "token": "1|abcdef123456789...",
  "token_type": "Bearer",
  "expires_at": "2026-10-19T23:59:59.000000Z",
  "user": {
    "id": 5,
    "name": "علی محمدی",
    "email": "operator@example.com"
  }
}
```

- **نقش داور (`judge`):** دسترسی‌های `tournaments:read` و `scores:write` برای ثبت نمرهٔ صندلی خود.
- **نقش اپراتور (`operator` / `manager`):** دسترسی‌های `tournaments:read`، `scores:write` و `competition:operate` برای ارسال فرمان‌های مسابقه (`start`, `finish`, `approve`) و ثبت دسته‌ای یا جایگزین نمرات.

در تمام درخواست‌های بعدی، هدرهای زیر الزامی است:
```http
Authorization: Bearer {token}
Accept: application/json
```

---

## ۳. دریافت Snapshot مسابقه و پیکربندی Real-time

```http
GET /api/v1/tournaments/{tournament}/competition
```

پاسخ شامل وضعیت تمام زمین‌ها، رده‌ها، رقابت‌ها و اطلاعات اتصال به Mercure Hub است:

```json
{
  "data": {
    "id": 1,
    "name": "مسابقات قهرمانی پومسه کشور",
    "status": "running",
    "courts": [...],
    "categories": [...]
  },
  "realtime": {
    "protocol": "mercure-sse",
    "hub_url": "http://192.168.1.100:3000/.well-known/mercure",
    "auth_endpoint": "http://192.168.1.100:8000/api/v1/broadcasting/auth",
    "channel": "private-tournaments.1",
    "presence_channel": "presence-tournaments.1.judges",
    "event": "competition.updated"
  }
}
```

---

## ۴. احراز هویت کانال‌های SSE (Mercure Broadcasting)

پیش از اتصال به stream هاب Mercure، کلاینت باید کوکی اشتراک را بسازد:

```http
POST /api/v1/broadcasting/auth
Authorization: Bearer {token}
Content-Type: application/json

{
  "channel_names": [
    "private-tournaments.1",
    "presence-tournaments.1.judges"
  ]
}
```

پاسخ شامل کوکی `mercureAuthorization` است. در اندروید با اشتراک‌گذاری `CookieJar` بین کلاینت HTTP و OkHttp `EventSource`، اتصال SSE به `hub_url` با تاپیک‌های مربوطه برقرار می‌شود.

---

## ۵. ثبت نمرات توسط داوران (از تبلت‌های اندروید)

پس از اعلام پایان اجرای فرم توسط اپراتور (`finish`)، وضعیت اجرا `scoring` می‌شود. داوران نمرهٔ خود را ارسال می‌کنند:

### سناریوی ۱: ارسال تکی توسط هر تبلت داور

```http
POST /api/v1/tournaments/{tournament}/performances/{performance}/scores
Content-Type: application/json

{
  "request_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "expected_version": 2,
  "expected_revision": 0,
  "accuracy": "2.50",
  "presentation": "6.00",
  "accuracy_penalties": ["0.30", "0.30", "0.30", "0.30", "0.30"],
  "presentation_components": ["2.00", "2.00", "2.00"]
}
```

- `request_id`: یک شناسهٔ یکتای UUID v4 به عنوان Idempotency Key برای جلوگیری از ارسال تکراری.
- `expected_version`: شماره نسخهٔ اجرای جاری (از snapshot خوانده می‌شود).
- `expected_revision`: نسخهٔ نمرهٔ قبلی همین داور (برای ثبت اولیه برابر ۰ و برای اصلاح با دلیل، برابر نسخهٔ قبلی).

### سناریوی ۲: ارسال دسته‌ای کلیه داوران توسط تبلت اپراتور (`scores/batch`)

اگر در طراحی اپلیکیشن، نمرات همهٔ تبلت‌ها روی تبلت مرکزی اپراتور جمع‌آوری می‌شود، اپراتور می‌تواند همه را در یک درخواست بفرستد و حتی **بلافاصله با فلگ `approve: true` تأیید نهایی را انجام دهد**:

```http
POST /api/v1/tournaments/{tournament}/performances/{performance}/scores/batch
Content-Type: application/json

{
  "expected_version": 2,
  "approve": true,
  "reason": "ثبت دسته‌ای نمرات داوران توسط اپراتور",
  "scores": [
    {
      "request_id": "d0e14a27-a065-4f4d-a99f-2f7823a233b1",
      "judge_assignment_id": 1,
      "expected_revision": 0,
      "accuracy": "2.60",
      "presentation": "5.80",
      "accuracy_penalties": ["0.30", "0.30", "0.30", "0.30", "0.10", "0.10"],
      "presentation_components": ["2.00", "2.00", "1.80"]
    },
    {
      "request_id": "d0e14a27-a065-4f4d-a99f-2f7823a233b2",
      "judge_assignment_id": 2,
      "expected_revision": 0,
      "accuracy": "2.50",
      "presentation": "5.90",
      "accuracy_penalties": ["0.30", "0.30", "0.30", "0.30", "0.30"],
      "presentation_components": ["2.00", "2.00", "1.90"]
    }
  ]
}
```

---

## ۶. تایید نهایی نمرات توسط اپراتور (Final Operator Approval)

چنانچه نمرات به صورت جداگانه از تبلت‌های داوران دریافت شده باشند، پس از رسیدن تمام نمرات (مثلاً ۵ از ۵ داور)، اپراتور دکمهٔ «تأیید نهایی و انتشار نتیجه» را می‌زند:

```http
POST /api/v1/tournaments/{tournament}/performances/{performance}/command
Content-Type: application/json

{
  "command": "approve",
  "expected_version": 2
}
```

### عملیاتی که بک‌اند در این مرحله به صورت خودکار انجام می‌دهد:
1. **کنترل کامل بودن پنل داوری:** بررسی می‌کند نمرهٔ معتبر از تمامی صندلی‌های تخصیص‌یافته (۳، ۵ یا ۷ داور) ثبت شده باشد.
2. **محاسبهٔ امتیاز طبق قوانین فدراسیون جهانی (WT):**
   - در صورت ۵ یا ۷ داور و فعال بودن قاعده، کمترین نمره و بیشترین نمرهٔ هر بخش (دقت و ارائه) به صورت مستقل حذف می‌شود (`Trimmed Mean`).
   - میانگین نمرات باقی‌مانده داوران محاسبه می‌گردد.
   - خطاهای زمانی یا خروج از تشک (در صورت وجود) کسر می‌شود.
3. **ثبت نتیجه در پایگاه داده:** رکورد نهایی در جدول `results` ساخته شده و وضعیت اجرا به `approved` تغییر می‌کند.
4. **تعیین برنده یا صعود جدول:** اگر رقابت تک‌حذفی دوطرفه باشد و هر دو فرم تمام شده باشد، برنده مشخص و جدول مرحلهٔ بعد به‌روزرسانی می‌شود.
5. **انتشار Real-time برای فرانت‌اند:** رویداد `performance.approve` ایجاد شده و از طریق Mercure SSE به فرانت‌اند ارسال می‌گردد.

### پاسخ سرور (200 OK):
```json
{
  "message": "وضعیت اجرا به‌روزرسانی شد.",
  "data": {
    "performance_id": 14,
    "status": "approved",
    "version": 3,
    "approved_at": "2026-10-19T11:00:00.000000Z",
    "result": {
      "id": 8,
      "score": "8.400000",
      "calculation": {
        "score": "8.400000",
        "components": {
          "accuracy": {
            "sorted_hundredths": [250, 260, 260, 270, 280],
            "kept_hundredths": [260, 260, 270],
            "mean": "2.633333"
          },
          "presentation": {
            "sorted_hundredths": [570, 580, 580, 590, 600],
            "kept_hundredths": [580, 580, 590],
            "mean": "5.833333"
          }
        },
        "rules": {
          "algorithm": "component_trimmed_mean_v1",
          "discard_each_end": 1
        },
        "judge_count": 5
      },
      "published_at": "2026-10-19T11:00:00.000000Z"
    }
  }
}
```

---

## ۷. دریافت و نمایش نمرات توسط فرانت‌اند (Scoreboard & Display)

فرانت‌اند به دو روش هماهنگ، نمرات را دریافت و نمایش می‌دهد:

### الف) اتصال زنده Mercure SSE (روش پیش‌فرض و آنی)
فرانت‌اند (کامپوننت‌های `Scoreboard.vue` و `Rtds.vue` و `CourtConsole.vue`) به تاپیک `tournaments.{id}` گوش می‌دهند:
- به محض تایید نهایی اپراتور، رویداد `competition.updated` و `scoreboard.updated` دریافت می‌شود.
- فرانت‌اند به طور خودکار داده‌های جدید را واکشی و بدون نیاز به رفرش صفحه، نمره قطعی ورزشکار، جزئیات صندلی داوران، و برنده مسابقه را در لحظه نمایش می‌دهد.

### ب) اندپوینت‌های اختصاصی REST برای نمایشگرها و وب
نمایشگرهای مستقل (مانند تلویزیون سالن، اپلیکیشن تماشاچیان یا کلاینت‌های مجزا) می‌توانند نمرات را مستقیماً از طریق REST دریافت کنند:

#### ۱. دریافت آخرین داده‌های نمایشگر سالن:
```http
GET /api/v1/tournaments/{tournament}/display-data?revision={last_known_revision}
```
اگر تغییری نکرده باشد پاسخ `{"changed": false}` می‌دهد (بسیار کم‌مصرف برای Polling)، و در صورت تغییر نمرات، داده‌های کامل نمایشگر بازگردانده می‌شود.

#### ۲. دریافت جزئیات نمرات یک اجرای خاص:
```http
GET /api/v1/tournaments/{tournament}/performances/{performance}/result
```
این اندپوینت وضعیت اجرا، نمرهٔ نهایی، فرمول محاسبه و نمرات تفکیک‌شدهٔ تک‌تک داوران (صندلی، دقت، ارائه، کسورات) را برمی‌گرداند.

---

## ۸. جدول کدهای وضعیت HTTP و رفع خطاها

| کد HTTP | مفهوم | علت | اقدام مورد نیاز کلاینت |
|---|---|---|---|
| `200 OK` | موفق | نمره یا فرمان با موفقیت ثبت شد | به‌روزرسانی UI با داده‌های دریافتی |
| `201 Created` | ایجاد شد | توکن ورود با موفقیت صادر شد | ذخیره توکن در Keystore |
| `401 Unauthorized` | عدم احراز هویت | توکن منقضی شده یا ارسال نشده | لاگین مجدد و دریافت توکن جدید |
| `403 Forbidden` | عدم دسترسی | داور صندلی دیگری است یا کاربر نقش اپراتور ندارد | بررسی نقش کاربر در مسابقه |
| `404 Not Found` | یافت نشد | اجرا یا مسابقه مربوطه وجود ندارد | بررسی شناسه tournament و performance |
| `409 Conflict` | تضاد نسخه یا تکرار نامعتبر | تغییر نسخهٔ اجرا (`expected_version`) یا تکرار `request_id` با داده متفاوت | خواندن مجدد Snapshot و ارسال نسخه جدید |
| `422 Unprocessable` | خطای اعتبارسنجی دامنه | کسورات نمره با سقف مجاز همخوانی ندارد یا تعداد داوران کامل نیست | اصلاح فیلدهای نمره طبق قواعد پومسه |
