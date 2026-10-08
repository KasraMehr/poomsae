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
| Scoring | `Scoring.vue` | نمایش زندهٔ bout در حال اجرا: ورزشکاران، فرم، جدول امتیاز قاضی‌ها، `ScoreCard` (سه امتیاز). | متصل به snapshot (با TODO) |
| Result | `Result.vue` | نتیجهٔ رقابت‌های تکمیل‌شده؛ هر ورزشکار یک `ScoreCard` روبه‌روی دیگری (آبی چپ، قرمز راست، کادر زرد روی برنده)؛ تک‌مرحله‌ای/دومرحله‌ای از `rounds.length`. | متصل به snapshot |
| Ranking | `Ranking.vue` | جدول رتبه‌بندی (`standings` برای round_robin + قهرمان برای knockout). | متصل به snapshot |
| TopFour | `TopFour.vue` | چهار نفر برتر با مدال. | متصل به snapshot (فقط round_robin) |
| Draw | `Draw.vue` | قرعه‌کشی: فرم‌ها و ورزشکاران هر دسته. | متصل به snapshot (با TODO) |

### ۱.۲ کامپوننت‌ها (`Components/scoreboard/`)

| کامپوننت | هدف | مصرف‌کننده |
| ---------- | --- | ---------- |
| `ScoreboardHeader` | هدر مشترک: لوگو + نام رویداد + دور/زمین. از `slot#header` به Layout تزریق می‌شود. | همهٔ صفحات |
| `AthleteInfo` | نوار نام رنگی (شماره + نام) + بج کشور؛ چهار چیدمان `name-only`/`horizontal`/`vertical`/`vertical-under` و دو سمت آینه‌ای (`chung` چپ، `hong` راست). بدون عکس. | Standby, LiveBoard, Scoring, Result, Draw |
| `NationalityBadge` | پرچم + کد سه‌حرفی انگلیسی کشور؛ دو جهت پرچم و placeholder وقتی پرچم نباشد. | داخل AthleteInfo (ردیف‌های Ranking/TopFour بلوک کشور خودشان را دارند — ارتفاع relative به ردیف) |
| `FormBadge` | ردیف `[roundLabel] [symبل فرم] [formName]` (بدون کادر)، در سه سایز (`sm/md/lg`). | Standby, LiveBoard, Scoring, Draw |
| `SymbolContainer` | نگه‌دارندهٔ یک تصویر سمبل (فرم یا مدال) در سه سایز؛ مسیر از `Shared/Services/symbols.js`. | داخل FormBadge |
| `Timer` | **فقط نمایش** — شمارش معکوس از `execution_duration_seconds`. سه حالت کاملاً مشتق از props: `idle` (پیش از شروع، `startedAt` خالی) / `running` (`startedAt` هست، `endedAt` خالی) / `stopped` (هر دو هست). هیچ state تایمری در فرانت وجود ندارد؛ کنترل کامل از پنل اپراتور است. در صفر روی `00:00` می‌ایستد (نه قرمز، نه اعلام خودکار). دو سایز: `lg` در Standby، `sm` در LiveBoard. | Standby, LiveBoard |
| `ScoreCard` | بلوک امتیاز **یک ورزشکار**: سه آیتم عمودی + آینه‌سازی برای `hong` (قرمز) + کادر زرد `winner` فقط روی جعبهٔ total. آیتم‌ها از slot می‌آیند. | Result, Scoring |
| `ScoreItem` | یک آیتم امتیاز: ردیف `label … /max` و عدد در جعبهٔ تیره زیرش. `variant="sub"` (عدد سفید، ۱۰۰px) یا `variant="total"` (عدد رنگ تیم، ۱۳۵px). قالب ۲ رقم اعشار با **ممیز لاتین** (`8.46`، نه `۸٫۴۶`) و مقدار غایب `--`. | داخل ScoreCard |
| `SingleScoringTable` | جدول تک‌نفره — **سطر = داور**، هدر ثابت انگلیسی `JUDGE / ACCURACY / EOE / S&P / R&P / PRESENTATION`. با `showSubScores=false` سه ستون ریزنمرات حذف می‌شوند و **عرض ستون‌های باقی تغییر نمی‌کند**. ستون/ردیف «مجموع» ندارد. | Scoring |
| `FreestyleScoringTable` | جدول فری‌استایل — سطر = داور، سه ستون `JUDGE / ACCURACY / PRESENTATION`. با `showSubScores=true` ستون ACCURACY به **۴** و PRESENTATION به **۶ زیرستون (بدون هدر)** تقسیم می‌شود؛ با `false` همان سه ستون می‌ماند و عرض ثابت است. | Scoring (بعد از داشتن `execution_mode`) |
| `DoubleScoringTable` | جدول **یک سمت** (یک ورزشکار) — ستون = داوران، سطر = دسته‌های نمره. صفحه دو بار رندر می‌کند: `side="chung"` (چپ، برچسب‌ها چپ، آبی) و `side="hong"` (راست، **آینه**: ترتیب Jn..J1 و برچسب‌ها راست، قرمز). با `showSubScores=false` سطرهای ریزنمرات مخفی می‌شوند. | Scoring (بعد از داشتن `execution_mode`) |
| `RankingTable` | جدول رنکینگ (طرح `RANKING-TABLE`) — **بدون هدر** (هدر متعلق به صفحهٔ مصرف‌کننده است)؛ ردیف‌هایی با ارتفاع کاهشی (۱۳۰/۱۱۰/۹۰/۷۵/۷۰ طبق رتبه). | Ranking |
| `RankingRow` | ردیف رنکینگ: جعبهٔ رتبه · پرچم+کد · نام · **دو ستون نمرهٔ راند** (`roundScores=[R-1, R-2]` — مقدار خالی `—` نمایش داده می‌شود)؛ فونت/ارتفاع به تفکیک رتبه. | داخل RankingTable |
| `TopFourTable` | جدول چهار نفر برتر (طرح `TOP-4-TABLE`) — **بدون هدر** (هدر متعلق به صفحه) + ۴ ردیف کاهشی. | TopFour |
| `TopFourRow` | ردیف Top Four: نشان رتبه (medal-1..5.webp با سایز کاهشی) · پرچم+کد · بلاک رنگیِ نام (طلایی/نقره‌ای/برنزی/تیره) با نمرهٔ نهایی داخل همان بلاک؛ ارتفاع ۱۵۰/۱۳۰/۱۱۰/۹۰. | داخل TopFourTable |

> **نکته (تغییر معماری):** `ScoringTableBase` **حذف شد** — هر سه جدول بالا مستقل و self-contained هستند (هدر ثابت داخل خود کامپوننت، بدون base مشترک).
> هر سه پراپ `showSubScores: boolean` (پیش‌فرض `true`) و `judges: []` دارند؛ `judgeCount` فقط برای حالت خالی است.
> دادهٔ `judges` همیشه در **ترتیب صندلی (J1..Jn)** می‌آید؛ آینه‌سازیِ Double داخل کامپوننت انجام می‌شود.
>
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
| **`[Proposed]`** | `logoUrl` (لوگوی رویداد)، `execution_duration_seconds` (مدت استاندارد اجرا برای شمارش معکوس)، `entry.country`، `entry.country_code`، `entry.flag_url`، `entry.number` — ~~`entry.photo_url`~~ **حذف موقت** (ردیف ۲ بخش ۴) |
| **نکته** | صرفاً اجرای جاری؛ هیچ داده‌ای برای صف/بعدی لازم ندارد. |

### ۳.۲ LiveBoard

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `bout.court_id` (فیلتر زمین)، `round.sequence/bout.sequence` (ترتیب)، `performance.status` (تشخیص current)، `bout.status=completed` + `winner_entry_id` + `totals` (previous)، `bout.status=pending` (next)، `entries[].name/side`، `form_name` |
| **`[Proposed]`** | `courtId` به‌صورت **پارامتر رسمی صفحه** (query param) — الان fallback به اولین زمین، `timeline` سراسری `previous/current/next` (الان از ترتیب snapshot و **یک زمین** استخراج می‌شود؛ بین زمین‌ها ترتیب سراسری نداریم)، `entry.country/country_code/flag_url`، `logoUrl`، `execution_duration_seconds` |
| **نکته** | در آینده می‌تواند به «همهٔ زمین‌ها» یا «یک Category» توسعه یابد؛ فعلاً فقط یک زمین. |

### ۳.۳ Scoring

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.judge_count` (۵/۷)، `bout.entries[].name/side`، `performance.form_name/form_number`، `bout.totals` (میانگین دو فرم)، `performance.result` |
| **`[Proposed]`** | **`execution_mode`** (`SINGLE \| DOUBLE \| SINGLE-FREESTYLE`) — برای انتخاب جدول درست (الان همیشه `SingleScoringTable`)، **`judge_scores[]` per seat** — نمرهٔ هر قاضی (در display snapshot آرایهٔ `scores[]` خالی می‌آید)، **`entry.accuracy_score`** و **`entry.presentation_score`** (دو امتیاز زیرمجموعه در `ScoreItem`)، `entry.country/country_code/flag`، `logoUrl` |
| **`[Proposed]` — ریزنمرات جدول‌ها** | برای Single/Double: سه مؤلفهٔ بین `accuracy` و `presentation` به‌نام **`EOE` / `S&P` / `R&P`** روی هر صندلی داور. برای Freestyle: **`accuracy_parts[]` (۴ مقدار)** و **`presentation_parts[]` (۶ مقدار)**. در Backend فعلی **فقط** `accuracy` و `presentation` وجود دارد (هیچ criterion دیگری ثبت نمی‌شود)؛ بدون این فیلدها ستون‌های ریزنمرات «—» نمایش داده می‌شوند. |
| **نکته** | `mode` متعلق به Domain است؛ Frontend حدس نمی‌زند. |
| **⚠️ تداخل نام** | ستون `categories.execution_mode` **از قبل در دیتابیس هست** با مقادیر `alternating \| simultaneous` (معنای متفاوت: نوبتی/همزمان). پیشنهاد `SINGLE \| DOUBLE \| SINGLE-FREESTYLE` همین نام را می‌گیرد؛ یا باید فیلد جدیدی با نام دیگر (مثلاً `scoring_mode`) بیاید یا نگاشت صریح از `discipline` (`recognized \| freestyle`) + `entry_type` (`individual \| pair \| team`). **این تصمیم با Backend است.** |

### ۳.۴ Result

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `bout.status=completed`، `winner_entry_id`، `entries[].name/side`، `bout.totals`، `rounds.length` (تشخیص `ONE-ROUND`/`TWO-ROUNDS`) |
| **[Proposed]** | `entry.country/country_code/flag_url`، `form_number` (ترتیب فرم: `1..2`)، `symbol_key` (کلید سمبل فرم: `1..18`)، `logoUrl` |

### ۳.۵ Ranking

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.standings[]` = `{rank, name, wins, played}` (فقط `format=round_robin`)، `champion_id` (فقط `format=knockout`) |
| **`[Proposed]`** | **`placements[]`** یکدست برای **هر دو قالب** (`round_robin` و `knockout`) با شکل پیشنهادی `{rank, entry_id, name, score, round_scores, country, country_code, flag_url}` — `round_scores: [number, number]` نمرهٔ کل هر راند (`[R-1, R-2]`) است و ستون‌های نمرهٔ `RankingTable` از آن می‌آیند (مدال/نشان از روی `rank` نمایش داده می‌شود، پس فیلد جدا لازم نیست) — الان برای knockout رتبه‌بندی نداریم، و `score` عددی نهایی هم نداریم، `logoUrl`، `stage` (مثل `SEMI-FINAL`) و `category` برای **هدر صفحه** (خود جدول هدر ندارد) |

### ۳.۶ TopFour

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | ۴ ردیف اول `standings` (فقط `round_robin`) |
| **`[Proposed]`** | **`placements[]`** (همان فیلد پیشنهادی Ranking — شامل knockout)، `entry.country/country_code/flag_url`، `logoUrl` |
| **نکته** | برای knockout در حال حاضر داده‌ای ندارد و صفحه پیام placeholder نشان می‌دهد. جدول از `TopFourTable`/`TopFourRow` رندر می‌شود و نمرهٔ نهایی از همان `score` می‌آید (بدون ستون راند). |

### ۳.۷ Draw

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.form_names[]` (ترتیب فرم‌ها)، `category.entries[]` (`name/club`) |
| **[Proposed]** | **`Draw.output_snapshot`** (ترتیب واقعی قرعه: شمارهٔ اجرا/جفت‌ها/slot)، `entry.number`, `entry.entry_type`, `entry_members[]` (`{name, club, position}`)، `draw_timing` (زمان قرعه)، `form_number`, `symbol_key`, `logoUrl` |

---

## ۴. خلاصهٔ فیلدهای `[Proposed Backend Contract]`

| # | فیلد | شکل پیشنهادی | مصرف‌کننده |
| - | ---- | ------------ | ---------- |
| 1 | `logoUrl` | `string \| null` روی snapshot | همهٔ صفحات (header) |
| 2 | ~~`entry.photo_url`~~ | **حذف موقت** — تا اطلاع بعدی تأمین نشود (عکس ورزشکار در هیچ صفحه‌ای نمایش داده نمی‌شود) | — |
| 3 | `entry.country` / `entry.country_code` / `entry.flag_url` | `string` روی entry — `country_code` از نوع ISO 3166-1 alpha-3 انگلیسی (مثل `IRI`) | هر جا بج/بلوک کشور (`AthleteInfo`, `RankingRow`, `TopFourRow`) |
| 4 | `entry.number` | `int` روی entry | Standby, Scoring |
| 5 | `execution_duration_seconds` | `int` روی تنظیمات مسابقه | Standby, LiveBoard (Timer) |
| 6 | `execution_mode` | `SINGLE \| DOUBLE \| SINGLE-FREESTYLE` روی category | Scoring (انتخاب جدول) |
| 7 | `judge_scores[]` | `[{seat, values[]}]` روی performance (در display هم) | Scoring (ستون‌های قاضی) |
| 8 | `entry.accuracy_score` | `number` ۰..۴ روی entry — سقف **ثابت**، فرانت جمع نمی‌کند | Result, Scoring (`ScoreItem`) |
| 9 | `entry.presentation_score` | `number` ۰..۶ روی entry — سقف **ثابت** | Result, Scoring (`ScoreItem`) |
| 9 | `bout.totals[entry_id]` | `number` ۰..۱۰ = مجموع دو مورد قبل؛ از Backend می‌آید، نه جمع در فرانت | Result, Scoring (`ScoreItem variant="total"`) |
| 9 | `bout.winner_entry_id` | `int` — تعیین `winner` و در نتیجه کادر زرد روی total | Result (`ScoreCard`) |
| 10 | `placements[]` | `[{rank, entry_id, name, score, round_scores, country, country_code, flag_url}]` روی category — `round_scores = [R-1, R-2]` برای ستون‌های نمرهٔ Ranking؛ مدال/نشان از `rank` مشتق می‌شود | Ranking, TopFour |
| 11 | `Draw.output_snapshot` | خروجی قرعه (ترتیب اجرا/جفت‌ها) | Draw |
| 12 | `entry_members[]` | `[{name, club, position}]` روی entry | Draw, Standby, Scoring |
| 13 | `draw_timing` | `timestamp` | Draw |
| 14 | `form_number` | `int` `1..2` روی performance — ترتیب فرم در اجرای رقابت | Scoring, Result, Draw, Standby, LiveBoard |
| 15 | `symbol_key` | `int` `1..18` روی فرم — کلید سمبل تصویری فرم | `FormBadge` در Scoring, Result, Draw, Standby, LiveBoard |
| 16 | `courtId` (LiveBoard) | query param رسمی | LiveBoard |
| 17 | `timeline` (LiveBoard) | `previous/current/next` صریح با ترتیب سراسری بین زمین‌ها | LiveBoard |
| 18 | `judge_sub_scores` | `{ eoe, sp, rp }` روی هر صندلی داور (Single/Double) و `{ accuracy_parts[], presentation_parts[] }` (Freestyle) — برای `showSubScores` | Scoring (ستون/سطر ریزنمرات) |

**نکتهٔ `NationalityBadge` (کد کشور و پرچم):**

- نمایش بج **فقط کد سه‌حرفی انگلیسی** (`entry.country_code`) است؛ نام فارسی (`entry.country`) صرفاً برای a11y/جست‌جو می‌ماند و جایگزین کد نمی‌شود.
- اگر `flag_url` نبود، Frontend حدس نمی‌زند: همان کد داخل قاب پرچم به‌عنوان placeholder نمایش داده می‌شود (frontend-only، نیاز به دادهٔ جدید ندارد).
- دو جهتِ پرچم (`flagPosition=left|right`) هم صرفاً prop کامپوننت است و قرارداد داده را تغییر نمی‌دهد.
- عکس ورزشکار (`photo_url`) **حذف موقت** است: تا اطلاع بعدی نه در `AthleteInfo` رندر می‌شود نه در `RankingRow`، و backend لازم نیست آن را تأمین کند.

**نکتهٔ سمبل فرم و مدال (asset های داخل مخزن):**

- Backend **URL تصویر نمی‌دهد**؛ فقط کلید دامنه: `symbol_key` (۱..۱۸) برای سمبل فرم و `rank` (۱..۵) برای مدال.
- `form_number` معنای متفاوتی دارد و فقط ترتیب فرم در اجرای رقابت را مشخص می‌کند (`1..2`).
- مسیر فایل‌ها در فرانت و در یک جا نگه‌داری می‌شود: `resources/js/Shared/Services/symbols.js` (`FORM_SYMBOLS`، `MEDAL_SYMBOLS`، `formSymbol()`، `medalSymbol()`)، و asset ها در `public/images/symbols/` و `public/images/medals/` قرار دارند.
- افزودن فرم یا مدال جدید = افزودن فایل + یک خط در map؛ هیچ تغییری در کامپوننت‌ها و قرارداد لازم نیست.
- مدال‌ها از روی `rank` انتخاب می‌شوند (۱ طلا، ۲ نقره، ۳ برنز، ۴ و ۵ خاکستری) و فیلد جداگانهٔ `medal` در قرارداد وجود ندارد.
- تا وقتی فایل‌های webp تحویل/اضافه نشده باشند، مسیرها در map تعریف شده ولی `SymbolContainer` چیزی رندر نمی‌کند (کامپوننت در نبود تصویر fallback ندارد).

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
Frontend  →  پیاده‌سازی شده (۷ صفحه + ۱۵ کامپوننت)، بدون mock، با TODO/Proposed Contract
Backend   →  در انتظار پیاده‌سازی بر اساس بخش ۳ و ۴ همین سند
بعد از تأیید Contract نهایی:
            ۱. فیلدهای Proposed به snapshot اضافه می‌شوند.
            ۲. صفحات از TODOها پاک و داده‌محور می‌شوند.
            ۳. Route/Inertia render به `Pages/Scoreboard/*` repoint می‌شود.
            
```

> تا تکمیل Backend: هیچ Route، Controller، Migration یا Business Logic تغییر نکرده،
> هیچ mock/fixture ساخته نشده و همهٔ دسترسی‌ها `null-safe` است.
