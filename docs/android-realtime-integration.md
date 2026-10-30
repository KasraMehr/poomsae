# راهنمای جامع اتصال اپلیکیشن اندروید داوران (Thin-Client / Active-State)

این مستند راهنمای فنی، گام‌به‌گام و ساده‌شده برای توسعه‌دهندهٔ اندروید جهت پیاده‌سازی اپلیکیشن تبلت داوران (مارکر) مسابقات پومسه تکواندو است.

> **نکتهٔ بسیار مهم:** اپراتور مسابقه هیچ تبلتی ندارد و کلیه وظایف و فرامین خود را (مانند Start، Finish، تغییر ورزشکار و Approve) منحصراً از طریق پنل وب انجام می‌دهد. اپلیکیشن اندروید منحصراً برای داوران (مارکر) جهت مشاهده وضعیت اجرای زنده و ثبت نمرات طراحی شده است.

---

## ۱. معماری و چرخه حیات سبک (Thin-Client Architecture)

در این معماری، اپلیکیشن تبلت داور دیگر نیازی به دریافت کل درخت مسابقات، گروه‌ها، دورها و محاسبات پیچیده کلاینت ندارد. کلاینت کاملاً State-Driven (مبتنی بر وضعیت فعال) عمل می‌کند:

```text
[ تبلت اندروید داور ]                    [ سرور لاراول ]                        [ کنسول اپراتور (وب) ]
        |                                       |                                       |
 1. لاگین و دریافت توکن ----------------------> | (تولید Sanctum Bearer Token)          |
        |                                       |                                       |
 2. دریافت وضعیت فعال فعلی -------------------> | (بازگرداندن اجرای جاری + Mercure Token)|
        |                                       |                                       |
 3. برقراری اتصال زنده SSE به Mercure ---------> | (شنود رویدادهای زنده مسابقه)          |
        |                                       |                                       |
        |                                       | <--- کلیک روی Start / Finish -------- |
        | <--- دریافت رویداد زنده (SSE) -------- |                                       |
        |      (شامل active_performance جدید)   |                                       |
        |                                       |                                       |
 4. ارسال مستقیم نمرات (دقت + ارائه) ---------> | (ثبت نمره داور)                       |
        |                                       | --- ارسال رویداد ثبت نمره -----------> | (سبز شدن صندلی در وب)
        |                                       |                                       |
        |                                       | <--- تأیید نهایی نمرات (Approve) ----- |
        | <--- رویداد نتیجه و اجرای بعدی (SSE) - |                                       |
```

---

## ۲. هدرهای عمومی درخواست‌ها (Headers)

به جز متد لاگین، در تمام درخواست‌های ارسالی به سرور باید هدرهای زیر قرار داده شوند:

```http
Authorization: Bearer {sanctum_token}
Accept: application/json
Content-Type: application/json
```

---

## ۳. چرخه ۳ مرحله‌ای ارتباط در اندروید

### گام اول: احراز هویت داور (Login)

داور با ایمیل و کلمه عبور وارد می‌شود:

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
> **اقدام اندروید:** مقدار `token` را ذخیره کنید تا در هدر تمام درخواست‌های بعدی ارسال شود.

---

### گام دوم: دریافت وضعیت فعال (Current State)

داور بلافاصله پس از ورود، اندپوینت وضعیت جاری را فراخوانی می‌کند:

```http
GET /api/v1/judge/current-state
Authorization: Bearer {sanctum_token}
Accept: application/json
```

#### پاسخ سرور زمانی که مسابقه و اجرایی در جریان است (200 OK):
```json
{
  "data": {
    "judge": {
      "id": 27,
      "name": "داور 1",
      "email": "judge1@poomsae.test",
      "seat_number": 1
    },
    "tournament": {
      "id": 8,
      "name": "مسابقه قهرمانی پومسه",
      "court_id": 13,
      "court_name": "زمین ۱"
    },
    "realtime": {
      "protocol": "mercure-sse",
      "hub_url": "http://127.0.0.1:8000/.well-known/mercure",
      "channel": "private-tournaments.8",
      "mercure_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
    },
    "active_performance": {
      "id": 15,
      "tournament_id": 8,
      "status": "scoring",
      "started_at": "2026-10-30T10:15:00.000000Z",
      "category_name": "تیمی دونفره (جفتی)",
      "entry_type": "pair",
      "entry_display_name": "علی حسینی / رضا اکبری",
      "side": "chung",
      "round_name": "فینال",
      "poomsae_name": "کوریو (Koryo)",
      "athlete": {
        "id": 101,
        "name": "علی حسینی",
        "first_name": "علی",
        "last_name": "حسینی",
        "team_or_club": "باشگاه عقاب",
        "position": 1
      },
      "athletes": [
        {
          "id": 101,
          "name": "علی حسینی",
          "first_name": "علی",
          "last_name": "حسینی",
          "team_or_club": "باشگاه عقاب",
          "position": 1
        },
        {
          "id": 102,
          "name": "رضا اکبری",
          "first_name": "رضا",
          "last_name": "اکبری",
          "team_or_club": "باشگاه عقاب",
          "position": 2
        }
      ],
      "paired_performance": null,
      "rules": {
        "judge_count": 5,
        "input_method": "standard_wt",
        "accuracy_max": 4.0,
        "presentation_max": 6.0
      },
      "my_submission": {
        "is_submitted": false,
        "accuracy": null,
        "presentation": null
      }
    }
  }
}
```

> **انواع مسابقات (`entry_type`):**
> - `individual`: انفرادی (آرایه `athletes` دارای ۱ عضو است).
> - `pair`: جفتی / دونفره (آرایه `athletes` دارای ۲ عضو است).
> - `team`: تیمی ۳ نفره (آرایه `athletes` دارای ۳ عضو است).
> - فیلد `athlete` همواره عضو اول را جهت سازگاری کدهای قبلی برمی‌گرداند.
>
> **مسابقات تک‌حذفی همزمان (Knockout Simultaneous):**
> در این نوع مسابقات دو بازیکن یا دو تیم (طرف آبی `chung` و طرف قرمز `hong`) همزمان داخل زمین فرم اجرا می‌کنند. در این حالت فیلد `paired_performance` حاوی اطلاعات اجرای طرف مقابل است:
> ```json
> "paired_performance": {
>   "id": 16,
>   "entry_id": 22,
>   "status": "scoring",
>   "side": "hong",
>   "entry_display_name": "محمد رضایی / امیر طاهری",
>   "athlete": { ... },
>   "athletes": [ ... ],
>   "my_submission": {
>     "is_submitted": false,
>     "accuracy": null,
>     "presentation": null
>   }
> }
> ```
> داور تبلت برای هر اجرا نمره را جداگانه ثبت می‌کند:
> ۱. نمره اجرای اول: `POST /api/v1/performances/15/scores`
> ۲. نمره اجرای همزمان: `POST /api/v1/performances/16/scores`
> تبلت می‌تواند با داشتن `paired_performance` دو پد نمره‌دهی (یا دکمه سوئیچ بین آبی و قرمز) نمایش دهد تا داور هر دو را همزمان نمره دهد.
>
> **نکته:** اگر در زمین داور هیچ اجرایی در جریان نباشد، مقدار `"active_performance": null` خواهد بود و تبلت وضعیت «در انتظار شروع مسابقه توسط اپراتور» را نمایش می‌دهد.
>
> **توجه مهم درباره SSE:** فیلد `realtime.mercure_token` مستقیماً در همین پاسخ تولید و تحویل داده می‌شود. بنابراین نیازی به فراخوانی جداگانه اندپوینت broadcasting/auth نیست.

---

### گام سوم: ارسال نمره داور (Submit Score)

هنگامی که اجرای بازیکن پایان یافته (`status == "scoring"`) یا حین اجرا (`status == "running"`):

```http
POST /api/v1/performances/15/scores
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
  "accuracy": "3.50",
  "presentation": "4.20",
  "accuracy_penalties": ["0.10", "0.10", "0.30"],
  "presentation_components": ["1.80", "1.40", "1.00"]
}
```

> **ساده‌سازی اساسی:** 
> - فیلدهای همزمانی مانند `request_id`، `expected_version` و `expected_revision` کاملاً اختیاری (Nullable) شده‌اند. تبلت اندروید نیازی به محاسبه یا نگهداری آن‌ها ندارد.
> - خطای `409 Conflict` در ارسال نمره حذف شده و سرور خودکار نسخه‌ها را مدیریت می‌کند.
> - مسیر بلند قبلی (`POST /api/v1/tournaments/{tournament}/performances/{performance}/scores`) نیز همچنان به عنوان نام مستعار معتبر است.

#### پاسخ سرور (200 OK):
```json
{
  "data": {
    "score_sheet_id": 42,
    "score": "7.70",
    "accuracy": "3.50",
    "presentation": "4.20"
  }
}
```

---

## ۴. ارتباط بلادرنگ با Mercure SSE

تبلت پس از دریافت پاسخ `current-state`، یک اتصال رویدادخوان (EventSource) برقرار می‌کند:

* **آدرس اتصال:** `hub_url + "?topic=" + channel`  
  (مثال: `http://127.0.0.1:8000/.well-known/mercure?topic=private-tournaments.8`)
* **هدر احراز هویت Mercure:** `Authorization: Bearer {mercure_token}`

### نمونه پی‌آمدهای ارسال‌شده از سرور (SSE Payload):

هنگامی که اپراتور وب وضعیتی را تغییر می‌دهد (مانند Start، Finish یا تغییر ورزشکار)، رویدادی شبیه به زیر دریافت می‌شود:

```json
event: message
data: {
  "tournament_id": 8,
  "action": "finish",
  "should_refresh": true,
  "active_performance": {
    "id": 15,
    "tournament_id": 8,
    "status": "scoring",
    "started_at": "2026-10-30T10:15:00.000000Z",
    "category_name": "انفرادی استاندارد مردان",
    "round_name": "فینال",
    "poomsae_name": "کوریو (Koryo)",
    "athlete": {
      "id": 101,
      "name": "علی حسینی",
      "team_or_club": "باشگاه عقاب"
    },
    "rules": {
      "judge_count": 5,
      "input_method": "standard_wt",
      "accuracy_max": 4.0,
      "presentation_max": 6.0
    },
    "my_submission": {
      "is_submitted": false,
      "accuracy": null,
      "presentation": null
    }
  }
}
```

- اگر `should_refresh == true` باشد، تبلت مستقیماً از شیء `active_performance` در پی‌لود استفاده می‌کند یا متد `GET /api/v1/judge/current-state` را بازخوانی می‌کند.
- اگر `active_performance.status == "running"` باشد: تایمر و صفحه اجرای فرم فعال می‌شود.
- اگر `active_performance.status == "scoring"` باشد: پد نمره‌دهی داور باز می‌شود.

---

## ۵. نمونه کد کاتلین (OkHttp EventSource با قابلیت اتصال مجدد خودکار)

```kotlin
package com.poomsae.marker.network

import android.os.Handler
import android.os.Looper
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.sse.EventSource
import okhttp3.sse.EventSourceListener
import okhttp3.sse.EventSources
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class RealtimeJudgeManager(
    private val hubUrl: String,
    private val channel: String,
    private val mercureToken: String,
    private val onStateUpdateRequired: (JSONObject?) -> Unit
) {
    private val client = OkHttpClient.Builder()
        .readTimeout(0, TimeUnit.MILLISECONDS) // باز نگه داشتن لوله برای SSE
        .retryOnConnectionFailure(true)
        .build()

    private var eventSource: EventSource? = null
    private val mainHandler = Handler(Looper.getMainLooper())
    private var isConnected = false

    fun startListening() {
        val streamUrl = "$hubUrl?topic=$channel"
        val request = Request.Builder()
            .url(streamUrl)
            .addHeader("Authorization", "Bearer $mercureToken")
            .addHeader("Accept", "text/event-stream")
            .build()

        eventSource = EventSources.createFactory(client).newEventSource(request, object : EventSourceListener() {
            override fun onOpen(eventSource: EventSource, response: Response) {
                isConnected = true
                println("Mercure SSE: اتصال زنده برقرار شد.")
            }

            override fun onEvent(eventSource: EventSource, id: String?, type: String?, data: String) {
                try {
                    val json = JSONObject(data)
                    mainHandler.post {
                        if (json.optBoolean("should_refresh", false)) {
                            val activePerf = json.optJSONObject("active_performance")
                            onStateUpdateRequired(activePerf)
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }

            override fun onFailure(eventSource: EventSource, t: Throwable?, response: Response?) {
                isConnected = false
                println("Mercure SSE قطع شد: ${t?.message}. تلاش مجدد پس از ۳ ثانیه...")
                // بازخوانی وضعیت پس از برقراری مجدد برای جلوگیری از جا ماندن تغییرات
                mainHandler.postDelayed({
                    onStateUpdateRequired(null) // فراخوانی مجدد GET current-state
                    startListening()
                }, 3000)
            }

            override fun onClosed(eventSource: EventSource) {
                isConnected = false
                println("Mercure SSE بسته شد.")
            }
        })
    }

    fun stopListening() {
        eventSource?.cancel()
    }
}
```

---

## ۶. محیط آزمایشی لوکال (Local Test Sandbox)

جهت تست اپلیکیشن بر روی سرور محلی:

* **آدرس سرور:** `http://10.0.2.2:8000` (ایمولاتور) یا آی‌پی شبکه محلی سیستم (مانند `http://192.168.1.100:8000`)
* **شناسه مسابقه:** `8`
* **شناسه اجرای آماده قضاوت:** `15` (وضعیت اولیه: `scoring`)
* **رمز عبور تمام کاربران:** `password123`

| نقش | ایمیل تست | مسئولیت |
|---|---|---|
| **داور ۱ (مارکر)** | `judge1@poomsae.test` | صندلی شماره ۱ (ورود از تبلت اندروید) |
| **داور ۲ (مارکر)** | `judge2@poomsae.test` | صندلی شماره ۲ (ورود از تبلت اندروید) |
| **داوران ۳ تا ۵** | `judge3@poomsae.test` تا `judge5@poomsae.test` | سایر صندلی‌ها |
| **اپراتور مسابقه** | `operator@poomsae.test` | کنترل مسابقه **فقط از طریق کنسول تحت وب** |

---

## ۷. جدول کدهای وضعیت HTTP

| کد وضعیت | شرح | واکنش اپلیکیشن اندروید |
|---|---|---|
| `200 / 201` | موفقیت‌آمیز | به‌روزرسانی UI یا ذخیره توکن |
| `401 Unauthorized` | توکن نامعتبر یا منقضی شده | هدایت به صفحه لاگین مجدد |
| `403 Forbidden` | کاربر به عنوان داور این مسابقه منتسب نشده | نمایش پیام خطای دسترسی |
| `404 Not Found` | اجرا یا مسابقه وجود ندارد | بررسی مجدد شناسه‌ها |
| `422 Unprocessable Content` | مقادیر ارسالی نمره نامعتبر است | بررسی فرمت و بازه نمرات (مثلاً نمره دقت بین ۰ تا ۴) |
