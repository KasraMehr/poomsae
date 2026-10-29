# راهنمای جامع اتصال اپلیکیشن Android به بک‌اند (REST API و Mercure SSE)

این مستند راهنمای فنی و گام‌به‌گام برای توسعه‌دهندهٔ اندروید جهت پیاده‌سازی اپلیکیشن تبلت داوران (مارکر) و اپراتور مسابقات پومسه تکواندو است. ارتباطات بر پایهٔ **REST API (Laravel 13 + Sanctum)** و ارتباط بلادرنگ بر پایهٔ **Mercure SSE (Server-Sent Events)** طراحی شده است.

---

## ۱. چرخهٔ حیات و نقشهٔ کلی ارتباط (Workflow)

```text
[ تبلت اندروید داور ]                    [ سرور بک‌اند لاراول ]                 [ کنسول اپراتور / اسکوربورد ]
        |                                          |                                          |
 1. لاگین و دریافت توکن (REST) ------------------> | (تولید Sanctum Bearer Token)             |
 2. دریافت لیست مسابقات (REST) ------------------> | (برگرداندن مسابقه مربوط به داور)          |
 3. دریافت اسنپ‌شات مسابقه (REST) ----------------> | (اطلاعات زمین، رده و صندلی داوری)         |
 4. اتصال پایدار به هاب Mercure (SSE) -----------> | (برقراری لولهٔ باز گوش به زنگ)            |
        |                                          |                                          |
        |   <--- رویداد شروع مسابقه: start (SSE) - | <--- ارسال فرمان Start توسط اپراتور ---- |
 (فعال شدن تایمر روی تبلت)                         |                                          |
        |   <--- رویداد پایان مسابقه: finish (SSE) | <--- ارسال فرمان Finish توسط اپراتور --- |
 (باز شدن پد نمره‌دهی داور)                        |                                          |
        |                                          |                                          |
 5. ارسال نمره داور (REST POST) -----------------> | (اعتبارسنجی و ثبت موقت نمره)             |
        |                                          | --- رویداد ثبت نمره (SSE) -------------> | (سبز شدن صندلی داور)
        |                                          |                                          |
        |                                          | <--- تأیید نهایی (Approve) توسط اپراتور - |
        |                                          | [محاسبه، حذف min/max، میانگین، برنده]    |
        |   <--- نتیجه رسمی و برنده (SSE) -------- | --- انتشار زنده نتیجه رسمی (SSE) ------> | [نمایشگر سالن RTDS]
```

---

## ۲. مشخصات هدرهای عمومی (Headers)

به جز متد لاگین، در تمام درخواست‌های ارسالی به سرور باید هدرهای زیر قرار داده شوند:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## ۳. مراحل گام‌به‌گام پیاده‌سازی در اندروید

### گام اول: احراز هویت و دریافت توکن (Login)
داور ایمیل، رمز عبور و نام دستگاه خود را وارد می‌کند:

```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "judge1@poomsae.test",
  "password": "password123",
  "device_name": "Court 1 / Tablet Judge 1"
}
```

#### پاسخ سرور (201 Created):
```json
{
  "token": "1|abcdef123456789...",
  "token_type": "Bearer",
  "expires_at": "2026-10-30T09:00:00.000000Z",
  "user": {
    "id": 27,
    "name": "داور 1",
    "email": "judge1@poomsae.test"
  }
}
```
> **اقدام اندروید:** فیلد `token` را در `EncryptedSharedPreferences` ذخیره کرده و در هدر درخواست‌های بعدی استفاده کنید.

---

### گام دوم: دریافت لیست مسابقات و استخراج `tournament_id`
داور نیازی به دانستن یا وارد کردن دستی شناسه مسابقه ندارد. این اندپوینت مسابقاتی که این داور در آن‌ها ثبت شده است را برمی‌گرداند:

```http
GET /api/v1/tournaments
Authorization: Bearer {token}
Accept: application/json
```

#### پاسخ سرور (200 OK):
```json
{
  "data": [
    {
      "id": 8,
      "name": "مسابقه آزمایشی تبلت",
      "venue": "سالن مسابقات",
      "starts_on": "2026-10-28",
      "ends_on": null,
      "status": "running"
    }
  ]
}
```
> **اقدام اندروید:** اگر کاربر فقط در یک مسابقه فعال حضور دارد، عدد `data[0].id` (در اینجا `8`) را به عنوان شناسهٔ مسابقه بردارید و وارد صفحهٔ قضاوت شوید.

---

### گام سوم: دریافت اسنپ‌شات کامل مسابقه (Snapshot)
با داشتن شناسهٔ مسابقه، اطلاعات زمین، صندلی داوری، رده‌ها و اجرای فعال دریافت می‌شود:

```http
GET /api/v1/tournaments/8/competition
Authorization: Bearer {token}
Accept: application/json
```

#### پاسخ سرور (200 OK):
```json
{
  "data": {
    "id": 8,
    "name": "مسابقه آزمایشی تبلت",
    "status": "running",
    "courts": [
      {
        "id": 13,
        "name": "زمین ۱"
      }
    ],
    "categories": [
      {
        "id": 1,
        "name": "انفرادی مردان",
        "rules": {
          "judge_count": 5,
          "input_method": "standard_wt"
        },
        "rounds": [...]
      }
    ]
  },
  "realtime": {
    "protocol": "mercure-sse",
    "hub_url": "http://127.0.0.1:8000/.well-known/mercure",
    "auth_endpoint": "http://127.0.0.1:8000/api/v1/broadcasting/auth",
    "channel": "private-tournaments.8",
    "presence_channel": "presence-tournaments.8.judges",
    "event": "competition.updated"
  }
}
```

---

### گام چهارم: برقراری اتصال زنده Mercure SSE (Real-Time Hub)

برای اینکه تبلت داور به محض زدن دکمه توسط اپراتور فرمان‌ها را دریافت کند:

#### ۱. دریافت مجوز کانال خصوصی از لاراول:
```http
POST /api/v1/broadcasting/auth
Authorization: Bearer {token}
Content-Type: application/json

{
  "channel_name": "private-tournaments.8"
}
```
پاسخ سرور شامل توکن/کوکی مجوز اشتراک هاب Mercure است.

#### ۲. باز نگه داشتن کانکشن پایدار SSE به هاب Mercure:
اندروید یک اتصال `EventSource` (با OkHttp یا کتابخانه‌های SSE) به آدرس زیر باز می‌کند:
```http
GET http://127.0.0.1:8000/.well-known/mercure?topic=private-tournaments.8
Authorization: Bearer {auth_token_یا_cookie}
Accept: text/event-stream
```

#### ۳. نمونه پیامی که سرور در حین مسابقه روی این کانال می‌فرستد:
```json
event: message
data: {
  "tournament_id": 8,
  "revision": 12,
  "action": "finish",
  "subject": {
    "type": "performance",
    "id": 15
  }
}
```
* **اگر `action == "start"` باشد:** تبلت داور تایمر اجرای فرم را روشن می‌کند.
* **اگر `action == "finish"` باشد:** تبلت داور پد ورود نمره را فعال و آنلاک می‌کند.
* **اگر `action == "approve"` باشد:** وضعیت اجرا تمام شده و فرم جدید آماده می‌شود.

---

### گام پنجم: ارسال نمره داور (Submit Score)

پس از اعلام پایان اجرا، داور نمرات دقت (`accuracy`)، ارائه (`presentation`) و ریزخطاها را وارد کرده و ارسال می‌کند:

```http
POST /api/v1/tournaments/8/performances/15/scores
Authorization: Bearer {token}
Content-Type: application/json

{
  "request_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "expected_version": 3,
  "expected_revision": 0,
  "accuracy": "3.50",
  "presentation": "4.20",
  "accuracy_penalties": ["0.10", "0.10", "0.30"],
  "presentation_components": ["1.80", "1.40", "1.00"]
}
```

* `request_id`: یک UUID v4 یکتا (برای Idempotency که در صورت چندبار زدن دکمه، نمره تکراری ثبت نشود).
* `expected_version`: نسخهٔ اجرای فعلی (از اسنپ‌شات دریافت شده).
* `expected_revision`: برای ثبت اولیه عدد `0`، و برای ویرایش نمره قبلی، شماره نسخهٔ قبلی.

#### پاسخ سرور (200 OK):
```json
{
  "message": "نمرهٔ شما با موفقیت ذخیره شد."
}
```
> **اقدام اندروید:** دکمه‌های نمره‌دهی را غیرفعال کنید و پیام «نمره ثبت شد؛ منتظر اجرای بعدی باشید» نمایش دهید.

---

### گام ششم: فرمان‌های اپراتور (ویژه تبلت اپراتور مسابقه)

تبلت اپراتور می‌تواند دستورات کنترل مسابقه را با متد زیر صادر کند:

```http
POST /api/v1/tournaments/8/performances/15/command
Authorization: Bearer {token}
Content-Type: application/json

{
  "command": "finish",
  "expected_version": 2
}
```
* مقادیر معتبر `command`:
  * `"start"`: شروع اجرای فرم و استارت تایمر
  * `"finish"`: پایان اجرای فرم و باز شدن پد داوران برای نمره‌دهی
  * `"approve"`: تأیید نهایی نمرات تمام داوران، محاسبهٔ میانگین با حذف min/max، و انتشار رسمی نتیجه

---

## ۴. نمونه کد پیاده‌سازی اتصال SSE در اندروید (OkHttp EventSource)

```kotlin
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.sse.EventSource
import okhttp3.sse.EventSourceListener
import okhttp3.sse.EventSources
import java.util.concurrent.TimeUnit

class MercureRealtimeService(private val token: String) {

    private val client = OkHttpClient.Builder()
        .readTimeout(0, TimeUnit.MILLISECONDS) // برای باز ماندن دائمی لوله SSE
        .build()

    fun connectToTournament(tournamentId: Int, onEventReceived: (String) -> Unit) {
        val hubUrl = "http://127.0.0.1:8000/.well-known/mercure?topic=private-tournaments.$tournamentId"

        val request = Request.Builder()
            .url(hubUrl)
            .addHeader("Authorization", "Bearer $token")
            .addHeader("Accept", "text/event-stream")
            .build()

        EventSources.createFactory(client).newEventSource(request, object : EventSourceListener() {
            override fun onOpen(eventSource: EventSource, response: Response) {
                println("اتصال پایدار به Mercure برقرار شد.")
            }

            override fun onEvent(eventSource: EventSource, id: String?, type: String?, data: String) {
                // دریافت بلادرنگ رویداد مسابقه
                println("پیام زنده مسابقه: $data")
                onEventReceived(data)
            }

            override fun onFailure(eventSource: EventSource, t: Throwable?, response: Response?) {
                println("خطای شبکه یا قطعی موقت: ${t?.message} - سیستم خودکار تلاش مجدد می‌کند.")
            }

            override fun onClosed(eventSource: EventSource) {
                println("اتصال بسته شد.")
            }
        })
    }
}
```

---

## ۵. اطلاعات تست در محیط محلی (Local Test Sandbox)

برای تست اپلیکیشن اندروید روی سرور لوکال می‌توانید از داده‌های زیر استفاده کنید:

* **آدرس سرور لوکال:** `http://10.0.2.2:8000` (در ایمولاتور اندروید) یا آدرس آی‌پی سیستم در شبکه LAN (مثلاً `http://192.168.1.100:8000`)
* **شناسه مسابقه آماده (Tournament ID):** `8`
* **شناسه اجرا آماده تست (Performance ID):** `15` (در وضعیت `scoring`)
* **رمز عبور تمام کاربران:** `password123`

| نقش | ایمیل تست | مسئولیت |
|---|---|---|
| **داور ۱ (مارکر)** | `judge1@poomsae.test` | ثبت نمره صندلی ۱ |
| **داور ۲ (مارکر)** | `judge2@poomsae.test` | ثبت نمره صندلی ۲ |
| **داور ۳ تا ۵** | `judge3@poomsae.test` تا `judge5@poomsae.test` | ثبت نمرات سایر صندلی‌ها |
| **اپراتور مسابقه** | `operator@poomsae.test` | صدور فرمان‌های start, finish, approve |

---

## ۶. جدول خطاهای رایج HTTP

| کد خطا | علت | اقدام اپلیکیشن اندروید |
|---|---|---|
| `200 / 201` | عملیات موفق | رندر داده‌ها یا ذخیره توکن |
| `401 Unauthorized` | توکن نامعتبر یا منقضی | هدایت داور به صفحه لاگین مجدد |
| `403 Forbidden` | کاربر داور این مسابقه نیست | نمایش پیام خطای سطح دسترسی |
| `404 Not Found` | مسابقه یا اجرای مورد نظر پیدا نشد | بررسی مجدد شناسه مسابقه یا اجرا |
| `409 Conflict` | تضاد نسخه (`expected_version`) | بارگذاری مجدد Snapshot و ارسال نسخه جدید |
| `422 Unprocessable` | ورودی نامعتبر (مثلاً نمره خارج از بازه) | بررسی محدوده مجاز نمرات (دقت بین ۰ تا ۴، ارائه بین ۰ تا ۶) |
