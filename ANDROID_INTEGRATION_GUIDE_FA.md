# راهنمای اتصال اپلیکیشن اندروید تبلت داوران (Thin-Client Architecture)

این سند خلاصه و راهنمای رسمی توسعه‌دهنده اندروید جهت اتصال تبلت‌های قضاوت (مارکر) مسابقات پومسه به سرور لاراول و هاب Mercure SSE است.

> **مهم:** اپراتور مسابقه هیچ نیازی به تبلت یا اپلیکیشن اندروید ندارد و تمام کارهای خود را فقط و فقط از طریق کنسول تحت وب انجام می‌دهد. تبلت اندروید فقط برای مارکرها (داوران کنار) است.

نسخه کامل‌تر و با جزئیات بیشتر در آدرس زیر در دسترس است:
👉 [docs/android-realtime-integration.md](docs/android-realtime-integration.md)

---

## ۱. چرخه ۳ مرحله‌ای کل فرآیند

1. **لاگین:**
   - متد: `POST /api/v1/auth/token`
   - دریافت `token` بیرر Sanctum
2. **دریافت وضعیت فعال (Active State):**
   - متد: `GET /api/v1/judge/current-state`
   - هدر: `Authorization: Bearer {token}`
   - دریافت اطلاعات صندلی داور، زمین، `mercure_token` برای SSE، و مشخصات اجرای فعال (`active_performance`). اگر هیچ اجرایی فعال نباشد مقدار آن `null` است.
3. **ارسال نمره (Submit Score):**
   - متد: `POST /api/v1/performances/{performance}/scores`
   - بدنه ساده: فقط مقادیر نمره (`accuracy`, `presentation`, `accuracy_penalties`, `presentation_components`). نیازی به ارسال `request_id`, `expected_version`, `expected_revision` نیست!

---

## ۲. دریافت بلادرنگ با Mercure SSE

- **URL اتصال:** `{hub_url}?topic={channel}`
- **هدر:** `Authorization: Bearer {mercure_token}`
- رویدادها شامل `should_refresh: true` و شیء آماده `active_performance` هستند.
- در صورت قطع ارتباط موقت: کلاینت پس از ۳ ثانیه مجدداً متصل می‌شود و متد `GET /api/v1/judge/current-state` را صدا می‌زند.

---

## ۳. اطلاعات محیط تست محلی

- **آدرس:** `http://10.0.2.2:8000` (ایمولاتور)
- **شناسه مسابقه:** `8`
- **شناسه اجرا:** `15`
- **رمز عبور:** `password123`
- **ایمیل‌های داوران:** `judge1@poomsae.test` تا `judge5@poomsae.test`
- **اپراتور:** `operator@poomsae.test` (از طریق مرورگر وب)
