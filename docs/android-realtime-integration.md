# اتصال اپ Android به API و Mercure

Realtime این پروژه از broadcast driver جدید Mercure در Laravel 13.32 استفاده می‌کند. Mercure روی Server-Sent Events است و سرور WebSocket یا Reverb در معماری وجود ندارد. اپ موبایل برای اعلان تغییرات و presence از SSE استفاده می‌کند؛ فرمان‌ها و ثبت نمره همچنان روی HTTPS/REST می‌مانند تا validation، idempotency و پاسخ قطعی حفظ شوند.

## ۱. دریافت توکن

```http
POST /api/v1/auth/token
Content-Type: application/json

{"email":"judge@example.com","password":"...","device_name":"Court 1 / Seat 3"}
```

توکن ۱۲ ساعت اعتبار و abilityهای `tournaments:read` و `scores:write` دارد. آن را در Android Keystore نگه دارید و برای API هدرهای زیر را بفرستید:

```http
Authorization: Bearer {token}
Accept: application/json
```

## ۲. snapshot و قرارداد realtime

```http
GET /api/v1/tournaments/{tournament}/competition
```

پاسخ شامل `data` و `realtime` است. مقادیر realtime:

- `protocol`: مقدار ثابت `mercure-sse`.
- `hub_url`: آدرس عمومی hub.
- `auth_endpoint`: endpoint ساخت cookie مجوز اشتراک.
- `channel`: کانال خصوصی تغییرات مسابقه.
- `presence_channel`: کانال حضور داورها.
- `event`: رویداد `competition.updated`.

## ۳. احراز کانال‌ها

Mercure driver کانال‌ها را به‌صورت batch احراز می‌کند:

```http
POST /api/v1/broadcasting/auth
Authorization: Bearer {token}
Content-Type: application/json

{
  "channel_names": [
    "private-tournaments.15",
    "presence-tournaments.15.judges"
  ]
}
```

پاسخ یک cookie از نوع HttpOnly می‌سازد. کلاینت HTTP و کلاینت SSE باید CookieJar مشترک داشته باشند. سپس `hub_url` را با topicهایی که Echo/Mercure از پاسخ auth می‌سازد باز کنید. در Android می‌توان از OkHttp `EventSource` همراه CookieJar استفاده کرد. cookie و stream باید پس از reconnect دوباره ساخته شوند.

رویداد `competition.updated` شامل `schema_version`, `tournament_id` و `revision` است. با دریافت آن snapshot مرحله ۲ را دوباره بخوانید. نمره و اطلاعات محرمانه داخل event منتشر نمی‌شود. `revision` برای حذف refresh تکراری است و event منبع حقیقت نیست.

presence channel وضعیت اتصال را گزارش می‌کند. قطع presence فقط یک نشانهٔ ارتباطی است؛ به‌تنهایی مجوز ثبت نمره جایگزین نیست.

## ۴. ثبت نمره

```http
POST /api/v1/tournaments/{tournament}/performances/{performance}/scores
Content-Type: application/json

{
  "request_id": "UUID-V4",
  "expected_version": 3,
  "expected_revision": 0,
  "accuracy": "2.50",
  "presentation": "6.00"
}
```

همان `request_id` فقط برای retry همان payload استفاده شود. تلاش جدید UUID جدید می‌خواهد. `409` یعنی snapshot یا revision قدیمی، `422` خطای قواعد نمره، `403` نبود نقش/تخصیص صندلی و `401` توکن نامعتبر است. فقط پاسخ `200` ثبت قطعی را ثابت می‌کند.

## ۵. hub محلی و LAN

برای hub مستقل توسعه:

```powershell
docker compose -f compose.mercure.yaml up -d
php artisan queue:work --tries=3
```

Laravel با `BROADCAST_CONNECTION=mercure` به `MERCURE_URL` منتشر می‌کند و مرورگر/موبایل از `MERCURE_PUBLIC_URL` می‌خوانند. روی LAN مقدار public URL باید IP یا hostname قابل دسترسی لپ‌تاپ باشد و `cors_origins` فایل Compose نیز همان originهای واقعی را داشته باشد. پس از تغییر `VITE_MERCURE_HUB_URL` دوباره `npm run build` اجرا شود.

در مسابقه واقعی HTTPS برای API و hub لازم است. JWT secret حداقل ۳۲ بایت، تصادفی و فقط روی سرور نگهداری شود. Wi-Fi داوری از شبکه عمومی سالن جدا باشد و polling دوره‌ای REST به‌عنوان fallback باقی بماند. با FrankenPHP می‌توان از hub داخلی استفاده کرد و `MERCURE_URL`/secret را طبق تنظیمات آن حذف کرد.
