# Scoreboard — قرارداد پیشنهادی داده (Frontend → Backend)

> **وضعیت:** Frontend پیاده‌سازی شده و **منتظر تکمیل پیاده‌سازی Backend است.**
>
> این سند برای مطالعهٔ تیم Backend نوشته شده تا بداند **هر صفحه چه داده‌ای لازم دارد**
> و بر اساس همین، قرارداد نهایی تأمین شود.
>
> * هر مورد با `[Proposed Backend Contract]` هنوز در Backend پیاده‌سازی **نشده** است.
> * Frontend آن‌ها را دادهٔ قطعی فرض نمی‌کند، برایشان mock نساخته و فقط با TODO علامت زده است.
> * منبع دادهٔ فعلی: `CompetitionView::snapshot(..., $display = true)`.

---

## ۱. درخت صفحات و کامپوننت‌ها

### ۱.۱ صفحات (`Pages/Scoreboard/`)

| صفحه | فایل | هدف | وضعیت |
| ------ | ---- | --- | ----- |
| Standby | `Standby.vue` | نمایش **اجرای فعلی + Timer اجرا + state پایان** (`running` / `scoring`). بدون صف، بدون اجرای قبلی/بعدی. | متصل به snapshot |
| LiveBoard | `LiveBoard.vue` | Broadcast board وضعیت **یک زمین**: `PREVIOUS ↓ CURRENT ↓ NEXT`. Timer اینجا مسئولیت اصلی نیست. | متصل به snapshot |
| Scoring | `Scoring.vue` | نمایش زندهٔ bout در حال اجرا: ورزشکاران، فرم، جدول امتیاز قاضی‌ها، `TotalScoreArc`. | متصل به snapshot (با TODO) |
| Result | `Result.vue` | نتیجهٔ رقابت‌های تکمیل‌شده؛ تک‌مرحله‌ای/دومرحله‌ای از `rounds.length`. | متصل به snapshot |
| Ranking | `Ranking.vue` | جدول رتبه‌بندی (`standings` برای round_robin + قهرمان برای knockout). | متصل به snapshot |
| TopFour | `TopFour.vue` | چهار نفر برتر با مدال. | متصل به snapshot (فقط round_robin) |
| Draw | `Draw.vue` | قرعه‌کشی: فرم‌ها و ورزشکاران هر دسته. | متصل به snapshot (با TODO) |

### ۱.۲ کامپوننت‌ها (`Components/scoreboard/`)

| کامپوننت | هدف | مصرف‌کننده |
| ---------- | --- | ---------- |
| `ScoreboardHeader` | هدر مشترک: لوگو + نام رویداد + دور/زمین. از `slot#header` به Layout تزریق می‌شود. | همهٔ صفحات |
| `AthleteInfo` | نمایش ورزشکار: شماره + رنگ سمت + نام + ملیت، در دو جهت `horizontal`/`vertical`. | Standby, LiveBoard, Scoring, Result, TopFour, Draw |
| `NationalityBadge` | پرچم + نام کشور (در `AthleteInfo` استفاده می‌شود). | داخل AthleteInfo |
| `FormBadge` | چیپ دور + آیکون فرم + نام فرم، در سه سایز (`sm/md/lg`). | Standby, LiveBoard, Scoring, Draw |
| `IconContainer` | آیکون فرم یا مدال رتبه (`variant: form/medal`). | داخل FormBadge, TopFour, Ranking |
| `Timer` | شمارش زمان بر اساس `running` + `startedAt` + `durationSeconds`؛ stop/start. | Standby, LiveBoard |
| `TotalScoreArc` | Arc دقت (accuracy) + امتیاز کل + برچسب نوع امتیاز + پس‌زمینه. | Scoring |
| `ScoringTableBase` | اسکلت جدول scoring: هدر قاضی‌ها، ریسپانسیو در ۵/۷ قاضی. | داخل سه جدول زیر |
| `SingleScoringTable` | جدول تک‌نفره + ردیف مجموع قاضی‌ها. | Scoring |
| `FreestyleScoringTable` | جدول فری‌استایل با ستون «اجرا». | Scoring (بعد از داشتن `execution_mode`) |
| `DoubleScoringTable` | جدول دونفره با هدر دو‌سطحی به ازای هر ورزشکار. | Scoring (بعد از داشتن `execution_mode`) |
| `RankingTable` | کانتینر جدول رنکینگ. | Ranking |
| `RankingRow` | ردیف رنکینگ با ارتفاع/رنگ متغیر بسته به رتبه. | داخل RankingTable |

> جهت وابستگی: `Pages/Scoreboard → Components/scoreboard → Shared`.
> `Shared` هیچ وابستگی به Scoreboard ندارد.

---

## ۲. وضعیت اجرا (Execution States)

این سه state پایهٔ `Standby` و `LiveBoard` هستند؛ منبع آن‌ها `performance.status` و `bout.status` است:

| state | معنی | رفتار |
| ----- | --- | ----- |
| `running` | اجرای فیزیکی فعلی | Timer در حال شمارش از `started_at` |
| `scoring` | اجرا تمام شده، منتظر نتیجه | Timer متوقف روی `ended_at - started_at`، نمایش «در انتظار نتیجه» |
| `approved` | نتیجه تأیید/منتشر شده | نمایش «نتیجه تأیید شده» + نمره |

```text
PREVIOUS (bout تکمیل‌شده) → CURRENT (running/scoring) → NEXT (bout pending)
```

---

## ۳. قرارداد هر صفحه با Backend

> «دادهٔ موجود» = همین الان در `CompetitionView::snapshot(display=true)` هست.
> «`[Proposed Backend Contract]`» = هنوز نیست و **Backend باید تأمین کند**.

### ۳.۱ Standby

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `tournament.status/name`, `courts[]`, `bout.status=running`, `performance.status/started_at/ended_at`, `performance.form_name`, `round.name`, `entries[].name/side` |
| **`[Proposed]`** | `logoUrl` (لوگوی رویداد)، `execution_duration_seconds` (مدت استاندارد اجرا برای شمارش معکوس)، `entry.photo_url`، `entry.country`، `entry.country_code`، `entry.flag_url`، `entry.number` |
| **نکته** | صرفاً اجرای جاری؛ هیچ داده‌ای برای صف/بعدی لازم ندارد. |

### ۳.۲ LiveBoard

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `bout.court_id` (فیلتر زمین)، `round.sequence/bout.sequence` (ترتیب)، `performance.status` (تشخیص current)، `bout.status=completed` + `winner_entry_id` + `totals` (previous)، `bout.status=pending` (next)، `entries[].name/side`، `form_name` |
| **`[Proposed]`** | `courtId` به‌صورت **پارامتر رسمی صفحه** (query param) — الان fallback به اولین زمین، `timeline` سراسری `previous/current/next` (الان از ترتیب snapshot و **یک زمین** استخراج می‌شود؛ بین زمین‌ها ترتیب سراسری نداریم)، `entry.photo_url/country/country_code/flag_url`، `logoUrl`، `execution_duration_seconds` |
| **نکته** | در آینده می‌تواند به «همهٔ زمین‌ها» یا «یک Category» توسعه یابد؛ فعلاً فقط یک زمین. |

### ۳.۳ Scoring

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.judge_count` (۵/۷)، `bout.entries[].name/side`، `performance.form_name/form_number`، `bout.totals` (میانگین دو فرم)، `performance.result` |
| **`[Proposed]`** | **`execution_mode`** (`SINGLE \| DOUBLE \| SINGLE-FREESTYLE`) — برای انتخاب جدول درست (الان همیشه `SingleScoringTable`)، **`judge_scores[]` per seat** — نمرهٔ هر قاضی (در display snapshot آرایهٔ `scores[]` خالی می‌آید)، **`accuracy_score`** (عدد داخل `TotalScoreArc`)، `score_type_label`، `entry.photo_url/country/country_code/flag`، `logoUrl` |
| **نکته** | `mode` متعلق به Domain است؛ Frontend حدس نمی‌زند. |

### ۳.۴ Result

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `bout.status=completed`، `winner_entry_id`، `entries[].name/side`، `bout.totals`، `rounds.length` (تشخیص `ONE-ROUND`/`TWO-ROUNDS`) |
| **`[Proposed]`** | `entry.photo_url/country/country_code/flag_url`، `form_icon` (آیکون فرم هر رقابت)، `logoUrl` |

### ۳.۵ Ranking

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.standings[]` = `{rank, name, wins, played}` (فقط `format=round_robin`)، `champion_id` (فقط `format=knockout`) |
| **`[Proposed]`** | **`placements[]`** یکدست برای **هر دو قالب** (`round_robin` و `knockout`) با شکل پیشنهادی `{rank, entry_id, name, score, photo_url, country, country_code, flag_url, medal}` — الان برای knockout رتبه‌بندی نداریم، و `score` عددی نهایی هم نداریم (فعلاً «X برد از Y» متنی نمایش داده می‌شود)، `logoUrl` |

### ۳.۶ TopFour

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | ۴ ردیف اول `standings` (فقط `round_robin`) |
| **`[Proposed]`** | **`placements[]`** (همان فیلد پیشنهادی Ranking — شامل knockout)، `entry.photo_url/country/country_code/flag_url`، `medal`، `logoUrl` |
| **نکته** | برای knockout در حال حاضر داده‌ای ندارد و صفحه پیام placeholder نشان می‌دهد. |

### ۳.۷ Draw

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.form_names[]` (ترتیب فرم‌ها)، `category.entries[]` (`name/club`) |
| **`[Proposed]`** | **`Draw.output_snapshot`** (ترتیب واقعی قرعه: شمارهٔ اجرا/جفت‌ها/slot)، `entry_members[]` (`{name, club, position}` + `photo_url`)، `draw_timing` (زمان قرعه)، `form_icon`، `logoUrl` |

---

## ۴. خلاصهٔ فیلدهای `[Proposed Backend Contract]`

| # | فیلد | شکل پیشنهادی | مصرف‌کننده |
| - | ---- | ------------ | ---------- |
| 1 | `logoUrl` | `string \| null` روی snapshot | همهٔ صفحات (header) |
| 2 | `entry.photo_url` | `string \| null` روی entry/athlete | Standby, LiveBoard, Scoring, Result, Ranking, TopFour, Draw |
| 3 | `entry.country` / `entry.country_code` / `entry.flag_url` | `string` روی entry — `country_code` از نوع ISO 3166-1 alpha-3 انگلیسی (مثل `IRI`) | هر جا `NationalityBadge` (`AthleteInfo`, `RankingRow`) |
| 4 | `entry.number` | `int` روی entry | Standby, Scoring |
| 5 | `execution_duration_seconds` | `int` روی تنظیمات مسابقه | Standby, LiveBoard (Timer) |
| 6 | `execution_mode` | `SINGLE \| DOUBLE \| SINGLE-FREESTYLE` روی category | Scoring (انتخاب جدول) |
| 7 | `judge_scores[]` | `[{seat, values[]}]` روی performance (در display هم) | Scoring (ستون‌های قاضی) |
| 8 | `accuracy_score` | `number` روی نتیجهٔ اجرا | Scoring (`TotalScoreArc`) |
| 9 | `score_type_label` | `string` | Scoring (`TotalScoreArc`) |
| 10 | `placements[]` | `[{rank, entry_id, name, score, photo_url, country, country_code, flag_url, medal}]` روی category | Ranking, TopFour |
| 11 | `Draw.output_snapshot` | خروجی قرعه (ترتیب اجرا/جفت‌ها) | Draw |
| 12 | `entry_members[]` | `[{name, club, position}]` روی entry | Draw, Standby, Scoring |
| 13 | `draw_timing` | `timestamp` | Draw |
| 14 | `form_icon` | `string \| null` روی فرم | Scoring, Result, Draw, Standby, LiveBoard |
| 15 | `courtId` (LiveBoard) | query param رسمی | LiveBoard |
| 16 | `timeline` (LiveBoard) | `previous/current/next` صریح با ترتیب سراسری بین زمین‌ها | LiveBoard |

**نکتهٔ `NationalityBadge` (کد کشور و پرچم):**

- نمایش بج **فقط کد سه‌حرفی انگلیسی** (`entry.country_code`) است؛ نام فارسی (`entry.country`) صرفاً برای a11y/جست‌جو می‌ماند و جایگزین کد نمی‌شود.
- اگر `flag_url` نبود، Frontend حدس نمی‌زند: همان کد داخل قاب پرچم به‌عنوان placeholder نمایش داده می‌شود (frontend-only، نیاز به دادهٔ جدید ندارد).
- دو جهتِ پرچم (`flagPosition=left|right`) هم صرفاً prop کامپوننت است و قرارداد داده را تغییر نمی‌دهد.

**پیشنهاد مدیریت کدها و پرچم‌ها:**

- `country_code` یکتا و اجباری روی هر entry باشد: کد ISO 3166-1 alpha-3 (هم‌ارز کدهای المپیک: `IRI`, `KOR`, `TUR`, …)، همیشه uppercase، در Backend اعتبارسنجی شود.
- `flag_url` از یک مخزن واحد با نام‌گذاری قابل پیش‌بینی تأمین شود: `/flags/{country_code}.svg`؛ تغییر/افزودن پرچم فقط با افزودن فایل در همان مخزن.
- کد ناشناخته/نامعتبر: entry با همان کد نمایش و پرچمش placeholder می‌گیرد؛ Backend خطا ندهد اما در لاگ ثبت کند.
- سمت Frontend هیچ حدس/تبدیل زبانی وجود ندارد؛ هر دو فیلد (`country_code`, `flag_url`) از snapshot می‌آیند.

مواردی که **Contract جدید لازم ندارند** (از snapshot فعلی قابل استخراج):

```text
rounds.length          → ONE-ROUND / TWO-ROUNDS
standings[]            → Ranking/TopFour فقط برای round_robin
judge_count            → تعداد ستون جدول (۵ یا ۷)
performance.status     → state های running / scoring / approved
court_id               → فیلتر زمین در LiveBoard
```

---

## ۵. وضعیت انتظار

```text
Frontend  →  پیاده‌سازی شده (۷ صفحه + ۱۳ کامپوننت)، بدون mock، با TODO/Proposed Contract
Backend   →  در انتظار پیاده‌سازی بر اساس بخش ۳ و ۴ همین سند
بعد از تأیید Contract نهایی:
            ۱. فیلدهای Proposed به snapshot اضافه می‌شوند.
            ۲. صفحات از TODOها پاک و داده‌محور می‌شوند.
            ۳. Route/Inertia render به `Pages/Scoreboard/*` repoint می‌شود.
            
```

> تا تکمیل Backend: هیچ Route، Controller، Migration یا Business Logic تغییر نکرده،
> هیچ mock/fixture ساخته نشده و همهٔ دسترسی‌ها `null-safe` است.
