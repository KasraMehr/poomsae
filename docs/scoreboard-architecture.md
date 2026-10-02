# معماری Scoreboard

> **نکتهٔ حیاتی**
>
> هر بخشی که با برچسب **[Proposed Backend Contract]** مشخص شده است، فقط پیشنهادی است.
>
> * در Backend پیاده‌سازی نشده است.
> * در Frontend به‌عنوان دادهٔ قطعی فرض نمی‌شود.
> * برای آن mock یا workaround ساخته نمی‌شود.
>
> قرارداد نهایی پس از شروع Backend implementation و تأیید آن، جداگانه ارائه می‌شود و سپس Frontend با آن هماهنگ خواهد شد.

---

## ۱. هدف

`Scoreboard` یک **surface مستقل** درون اپ است، نه یک صفحهٔ تک‌فایلی.

هدف این مرحله، **architecture و contract** است، نه پیاده‌سازی کامل UI.

اهداف:

* شکستن `Scoreboard` به یک **Layout مشترک** و چند **Page مجزا** مطابق flow واقعی مسابقه.
* مشخص کردن **مرز ownership** بین `Pages/`، `Components/` و `Shared/`.
* مشخص کردن **قرارداد دادهٔ موردنیاز** از Backend؛ فعلاً فقط به‌صورت Proposed.
* امکان تغییر تدریجی، مرحله‌ای و قابل review/rollback بودن هر گام.

### مرجع طراحی

مرجع طراحی، ۱۱ فریم خروجی‌شده از Figma در مسیر زیر است:

`Design/v-final-RTDS/`

تمام فریم‌ها با ابعاد `1280×720` هستند.

```text
STAND-BY-{SINGLE,DOUBLE}
SCORING-{SINGLE,SINGLE-FREESTYLE,DOUBLE}

RESULT-{ONE-ROUND,TWO-ROUNDS}
RANKING-TABLE

TOP-4-TABLE
FORM-DRAW-{SINGLE,DOUBLE}
```

---

## ۲. وضعیت فعلی — خلاصهٔ Audit

### ۲.۱ Frontend

فایل `resources/js/app.js` از resolver زیر استفاده می‌کند:

```js
resolvePageComponent("./Pages/" + name + ".vue")
```

در حال حاضر **هیچ layout resolver سراسری وجود ندارد** و هر Page داخل قالب خودش قرار می‌گیرد.

Layoutهای موجود:

| Layout        | مصرف‌کننده‌ها                                        |
| ------------- | ---------------------------------------------------- |
| `AppLayout`   | `Dashboard`, `Tournaments/Index`, `Tournaments/Show` |
| `ArenaLayout` | `Competition/Console`, `Competition/Judge`           |
| بدون Layout   | `Competition/Scoreboard`                             |

صفحهٔ فعلی `Competition/Scoreboard` یک root کامل دارد و header، فیلتر زمین، banner توقف به‌روزرسانی، لیست boutهای فعال، آخرین نتایج و footer را خودش مدیریت می‌کند.

---

### ۲.۲ Route و Controller — بدون تغییر

Routeهای فعلی:

```php
// routes/web.php
// prefix: tournaments/{tournament}
// middleware: ['auth', 'active']

Route::get('/scoreboard', [ScoreboardController::class, 'show'])
    ->name('scoreboard.show');

Route::get('/results.csv', [ScoreboardController::class, 'export'])
    ->name('scoreboard.export');
```

`ScoreboardController@show`:

```php
Gate::authorize('view', $tournament);

Inertia::render(
    'Competition/Scoreboard',
    ['tournament' => $view->snapshot($tournament, $user, true)]
);
```

### وابستگی‌های حساس

این موارد نباید شکسته شوند:

1. `TournamentController:41`

   کاربر غیر-`operate` بعد از `tournaments.show` به `scoreboard.show` redirect می‌شود.

   بنابراین این route نقش **landing page** کاربر نقش `display` را نیز دارد.

2. تست‌ها:

```text
CompetitionConsoleTest:38
→ assertRedirect(route('scoreboard.show'))

CompetitionWorkflowTest:38
→ GET /scoreboard
→ assertOk
```

### نتیجه

تا زمان نهایی شدن Backend Contract، موارد زیر دست‌نخورده می‌مانند:

```text
route name
URL
Inertia render path
```

---

## ۲.۳ قرارداد دادهٔ فعلی

منبع دادهٔ فعلی:

```text
CompetitionView::snapshot(..., $display = true)
```

وقتی `display=true` باشد:

```text
members = []
judges = []
scores = []
own_score = null
score_components = not fetched
```

ساختار فعلی:

```text
tournament
├── id
├── name
├── venue
├── starts_on
├── status
├── courts[]
├── members[]
└── categories[]

category
├── id
├── name
├── gender
├── minimum_age
├── maximum_age
├── format
├── judge_count
├── rules
├── form_names[]
├── entries[]
├── rounds[]
├── completed
├── champion_id
└── standings[]

entry
├── id
├── name
├── status
└── club

round
├── id
├── name
├── sequence
├── status
└── bouts[]

bout
├── id
├── sequence
├── court_id
├── status
├── winner_entry_id
├── resolution_reason
├── entries[]
├── judges[]
├── totals
└── performances[]

performance
├── id
├── entry_id
├── form_number
├── form_name
├── status
├── version
├── started_at
├── ended_at
├── result
├── submitted_count
├── is_assigned
├── own_score
└── scores[]

standings
└── فقط برای format=round_robin
    [{rank, id, name, wins, played}]

totals
└── فقط وقتی هر دو فرم approved و published شده باشند
```

> این snapshot تنها منبع دادهٔ مجاز برای تمام Pageهای Scoreboard در این مرحله است.

---

# ۳. تصمیم معماری

### ۳.۱ Scoreboard یک Surface مستقل است

ساختار کلی:

```text
Scoreboard
├── ScoreboardLayout
└── Pages
    ├── Standby      ← فقط اجرای فعلی + Timer + state پایان
    ├── LiveBoard    ← جریان یک زمین: PREVIOUS ↓ CURRENT ↓ NEXT
    ├── Scoring
    ├── Result
    ├── Ranking
    ├── TopFour
    └── Draw
```

`ScoreboardLayout.vue` در مسیر زیر قرار می‌گیرد:

```text
resources/js/Shared/Layouts/ScoreboardLayout.vue
```

این Layout هم‌جایگاه `AppLayout` و `ArenaLayout` است.

---

### ۳.۲ نام Pageها

Pageها با نام‌های طراحی‌ها یکسان هستند:

```text
Standby
LiveBoard
Scoring
Result
Ranking
TopFour
Draw
```

> **نام صفحهٔ نمایش زنده `Scoring` است، نه `Live`.**
>
> **`LiveBoard` صفحهٔ جداگانه‌ای است و با `Standby` اشتباه گرفته نمی‌شود:**
>
> * `Standby` = اجرای فعلی + Timer اجرا + state پایان (بدون صف/قبلی/بعدی).
> * `LiveBoard` = broadcast board وضعیت **یک زمین**: اجرای قبلی ↓ فعلی ↓ بعدی (بدون Timer به‌عنوان مسئولیت اصلی).

دلیل:

* هم‌نام فایل‌های طراحی Figma است.
* با واژگان پروژهٔ قبلی هماهنگ است.
* با `performance.status = 'scoring'` هماهنگ است.

---

### ۳.۳ محدودیت این فاز

در این فاز:

```text
Backend changes = NO
Migration       = NO
Route changes   = NO
Store           = NO
```

`variant/mode` نیز به‌صورت `prop` در سطح Page در نظر گرفته می‌شود و route جداگانه نخواهد داشت.

---

# ۴. ساختار Folder و مسئولیت هر بخش

ساختار پیشنهادی:

```text
resources/js/

├── Pages/
│   └── Scoreboard/
│       ├── Standby.vue
│       ├── LiveBoard.vue
│       ├── Scoring.vue
│       ├── Result.vue
│       ├── Ranking.vue
│       ├── TopFour.vue
│       └── Draw.vue
│
├── Components/
│   └── scoreboard/
│       ├── ScoreboardHeader.vue
│       ├── AthleteInfo.vue
│       ├── NationalityBadge.vue
│       ├── FormBadge.vue
│       ├── SymbolContainer.vue
│       ├── Timer.vue
│       ├── ScoreCard.vue
│       ├── ScoreItem.vue
│       └── tables/
│           ├── ScoringTableBase.vue
│           ├── SingleScoringTable.vue
│           ├── FreestyleScoringTable.vue
│           ├── DoubleScoringTable.vue
│           ├── RankingTable.vue
│           └── RankingRow.vue
│
└── Shared/
    ├── Layouts/
    │   └── ScoreboardLayout.vue
    │
    ├── Components/
    │
    ├── Composables/
    │
    └── Services/
        ├── realtime.js
        ├── requestId.js
        └── symbols.js   # نگاشت کلید دامنه (form_number / rank) به مسیر asset در public/
```

> ۱۳ کامپوننت بالا پیاده‌سازی شده‌اند؛ هرکدام در جدول `docs/scoreboard-contract.md` معرفی شده‌اند.
> سمبل فرم‌ها و مدال‌ها asset های ثابت داخل مخزن‌اند و در `Shared/Services/symbols.js` به کلید (`form_number` / `rank`) نگاشت شده‌اند؛ قرارداد داده URL تصویر نمی‌دهد.

### مسئولیت Pageها

```text
Pages/Scoreboard/
```

فقط شامل Pageهای Inertia است.

### مسئولیت Components

```text
Components/scoreboard/
```

شامل componentها و composableهایی است که فقط به Scoreboard تعلق دارند.

### مسئولیت Shared

```text
Shared/
```

شامل موارد واقعاً عمومی اپلیکیشن است و نباید به Page یا Domain خاصی وابسته باشد.

---

## Dependency Direction

جهت وابستگی به شکل زیر است:

```text
Pages/Scoreboard
        ↓
Components/scoreboard
        ↓
Shared
```

قواعد:

1. `Pages/Scoreboard/*` می‌تواند از `Components/scoreboard/*` و `Shared/*` import کند.
2. `Components/scoreboard/*` فقط از `Shared/*` import می‌کند.
3. `Components/scoreboard/*` هرگز از `Pages/*` import نمی‌کند.
4. `Shared/*` هرگز از `Pages/*` یا `Components/*` import نمی‌کند.
5. هیچ Pageای از Page دیگر import نمی‌کند.
6. `ScoreboardLayout` از Pageها خبر ندارد و فقط slot می‌پذیرد.
7. داده فقط از props اینرتیا می‌آید.
8. هیچ fetch مستقیم یا دور زدن Inertia وجود ندارد.
9. چیزی که فقط Scoreboard مصرف می‌کند در `Components/scoreboard/` باقی می‌ماند.
10. انتقال یک component به `Shared` فقط زمانی انجام می‌شود که یک Domain یا Surface دوم واقعاً از آن استفاده کند.

---

# ۵. Pageهای Scoreboard و مسئولیت هرکدام

| Page        | مسئولیت                                                     | دادهٔ فعلی                                                              | دادهٔ موردنیاز در آینده                                                  |
| ----------- | ----------------------------------------------------------- | ----------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| `Standby`   | فقط اجرای فعلی + Timer اجرا + state پایان (`running`/`scoring`) | `bout.status=running`, `performance.status/started_at/ended_at`, `entries`, `form_name` | `[Proposed] logoUrl`, `execution_duration_seconds`, `entry.photo_url/country/flag/number` |
| `LiveBoard` | جریان یک زمین: PREVIOUS ↓ CURRENT ↓ NEXT (بدون Timer اصلی)     | `court_id`, `round/bout.sequence`, `performance.status`, `winner_entry_id`, `totals` | `[Proposed] courtId رسمی`, `timeline سراسری`, `entry.photo_url/country/flag`, `logoUrl` |
| `Scoring` | نمایش زندهٔ bout، امتیاز، میانگین و زمان اجرا | `rounds[].bouts[]`, `performances`, `totals`                 | `[Proposed] execution_mode`, `discipline`, `photo_url`              |
| `Result`  | نتیجهٔ نهایی یک یا دو مرحله                   | `winner_entry_id`, `totals`, `performances`, `rounds.length` | `[Proposed] photo_url`                                              |
| `Ranking` | جدول رتبه‌بندی                                | `standings`, `champion_id`                                   | `[Proposed] placements[]`                                           |
| `TopFour` | چهار نفر برتر و مدال‌ها                       | —                                                            | `[Proposed] placements[]`, `photo_url`                              |
| `Draw`    | قرعه‌کشی و ترتیب اجرا                         | —                                                            | `[Proposed] Draw.output_snapshot`, `entry_members[]`, `draw_timing` |

---

## Variant / Mode

```text
Standby
Scoring
Draw
    → SINGLE | DOUBLE | SINGLE-FREESTYLE

LiveBoard
    → بدون variant (فقط یک زمین؛ انتخاب زمین بعداً از param)

Result
    → ONE-ROUND | TWO-ROUNDS

Ranking
TopFour
    → بدون variant
```

---

# ۶. ScoreboardLayout

مسیر:

```text
resources/js/Shared/Layouts/ScoreboardLayout.vue
```

این Layout مشترک هر ۶ Page است.

### منبع implementation

کد آماده‌ای که برای Scoreboard ارائه می‌شود، **مرجع رسمی implementation** است.

قواعد:

* ساختار و ظاهر حفظ می‌شود.
* فقط برای اتصال به اپ تطبیق داده می‌شود.
* importهای Inertia، مسیرها و قراردادهای Tailwind در صورت نیاز اصلاح می‌شوند.
* بدون دلیل معماری یا طراحی بازنویسی نمی‌شود.
* اگر کد مرجع با این سند تضاد داشته باشد، قبل از تغییر گزارش می‌شود.

### Header

بر اساس هندسهٔ ۱۱ طراحی:

* Header دارای سه slot است.
* این ساختار در ۱۰ فایل از ۱۱ فایل تکرار شده است.
* slot چپ: عنوان ثابت
* slot وسط: عنوان صفحه
* slot راست: اطلاعات ثابت

برای Header حالت `compact` نیز در نظر گرفته می‌شود.

پیاده‌سازی فعلی: کامپوننت `ScoreboardHeader` در `Components/scoreboard/` از طریق `slot#header` به Layout تزریق می‌شود؛ اگر Page این slot را ندهد، هدر پیش‌فرض خود Layout رندر می‌شود (جهت وابستگی Shared → scoreboard حفظ است).

در طراحی `RESULT-TWO-ROUNDS`:

```text
y = 48
h = 95

→ compact:

y = 38
h = 51
```

بدنهٔ صفحه از طریق slot اصلی وارد Layout می‌شود.

محتوای Page-specific داخل Layout قرار نمی‌گیرد.

---

## تفاوت با ArenaLayout

`ArenaLayout` برای Console/Judge است و chrome مخصوص خود را دارد:

```text
MERCURE LIVE
slot #actions
LAN FIRST · AUDITED SCORING
max-w-[1720px]
```

این Layout برای محیط desktop/tablet طراحی شده است.

در مقابل، Scoreboard برای نمایشگر/تلویزیون سالن است و نیاز متفاوتی دارد.

بنابراین:

```text
ArenaLayout ≠ ScoreboardLayout
```

هیچ‌کدام جایگزین دیگری نمی‌شود و هیچ‌کدام حذف نخواهد شد.

---

## تم و فونت Scoreboard

### تم — ثابت

محل: `resources/css/themes/scoreboard.css`

این فایل از `resources/css/app.css` import می‌شود و توکن‌های RTDS را در بر دارد.

شامل:

```text
پس‌زمینه‌ها   --color-rtds-bg / bg-secondary / bg-card / bg-elevated
توکن‌های طراحی Scoreboard (اضافه‌شده از Figma):
              --color-rtds-text-light / text-muted / surface-light
              --color-rtds-gold / gold-bright / blue-soft / divider
glow backdrop  --color-rtds-glow-blue / glow-red + opacityها
برند چونگ     --color-rtds-blue / blue-dark / blue-light
برند هونگ     --color-rtds-red / red-dark / red-light
اکسنت زرد     --color-rtds-yellow / yellow-bright / yellow-dark
متن            --color-rtds-text / text-secondary / text-tertiary / text-on-*
بوردر          --color-rtds-border / border-subtle / border-strong
وضعیت          --color-rtds-success / warning / error / info
داور           --color-rtds-judge
```

تم **ثابت** است و برای همهٔ Pageهای Scoreboard مشترک است.

دسترسی از طریق کلاس‌های Tailwind: `bg-rtds-*`، `text-rtds-*`، `border-rtds-*` و …

utilityها طبق رفتار Tailwind v4 فقط به‌محض استفاده در یک فایل تولید می‌شوند.

توکن‌های قدیمی این فایل (`--color-scoreboard-*`) جایگزین شدند چون در هیچ فایلی استفاده نشده بودند.

### فونت — متغیر

محل: توکن `--font-rtds` داخل همان `resources/css/themes/scoreboard.css`.

برخلاف تم، فونت **متغیر** است: بسته به فارسی یا انگلیسی بودن محتوا عوض می‌شود.

نسخهٔ فعلی (فارسی):

```css
--font-rtds: "Oswald", ui-sans-serif, system-ui, sans-serif;
```

نکات:

* این مقدار **موقتی** است و پس از مشخص شدن فونت نهایی توسط کارفرما، فقط همین‌جا ویرایش می‌شود.
* توکن هم‌رتبه در تم اپراتور (`--font-operator` در `resources/css/themes/operator.css`) نیز ثبت شده تا هر سطح فونت خودش را داشته باشد.
* در Layout از کلاس `font-rtds` استفاده می‌شود.

> **محدودیت شناخته‌شده:**
>
> * مکانیزم لود فونت وب (فایل `@font-face` یا link به Google Fonts) هنوز در پروژه وجود ندارد.
> * Oswald پوشش حروف فارسی/عربی ندارد.
>
> تا زمانی که فونت نهایی و روش لود آن مشخص شود، متن فارسی روی fallback سیستم رندر می‌شود. این مورد به تصمیم بعدی موکول است.

---

# ۷. Component Ownership

| محل                                   | مسئولیت                                  | نمونه                                   |
| ------------------------------------- | ---------------------------------------- | --------------------------------------- |
| `Pages/Scoreboard/*`                  | ترکیب Layout و componentها و ساختار Page | هر Page                                 |
| `Components/scoreboard/*`             | component/composable اختصاصی Scoreboard  | کارت entry، banner، `useScoreboardSync` |
| `Shared/Layouts/ScoreboardLayout.vue` | chrome مشترک Scoreboard                  | header، slotها، footer                  |
| `Shared/Composables/*`                | realtime عمومی موجود                     | `useCompetitionUpdates`                 |
| `Shared/Services/*`                   | سرویس‌های زیرساختی                       | `realtime`, `requestId`                 |

### خارج از این فاز

فعلاً انجام نمی‌شود:

* استخراج component از `Pages/Competition/Scoreboard.vue`
* ساخت componentهای جدید از Page فعلی

این تصمیم عمداً خارج از Scope این فاز است.

---

# ۸. تصمیم Mode / Variant و منبع داده

## اصل اصلی

> **Backend مالک mode است، نه Frontend.**

Mode بخشی از Domain Rule است و Frontend نباید آن را حدس بزند یا دوباره تعریف کند.

فیلدهای موردنظر:

```text
categories.discipline
categories.entry_type
categories.execution_mode
categories.forms_per_round
```

---

## وضعیت فعلی Backend

Backend در حال حاضر برای این بخش آماده نیست.

دو دلیل:

### دلیل اول

فیلدهای mode در snapshot فعلی برنمی‌گردند.

### دلیل دوم

Flow فعلی عملاً تک‌حالتی است.

در حال حاضر:

```text
CompetitionSetup:113
ScheduleRound:21
```

فقط حالت زیر را می‌پذیرند:

```text
recognized
+
individual
+
alternating
+
forms_per_round = 2
```

بنابراین فعلاً داده‌ای که مشخص کند مسابقه `FREESTYLE` یا `DOUBLE` است تولید نمی‌شود.

---

## Backend رندر نمی‌کند

Backend مسئول render کردن mode نیست.

جریان صحیح:

```text
Backend
   ↓
Inertia Snapshot
   ↓
Frontend
   ↓
Render according to mode
```

بنابراین نیاز Backend فقط **اعلام mode در snapshot** است.

---

## اولویت منابع Mode

| سطح                       | منبع                                                            | وضعیت                                      |
| ------------------------- | --------------------------------------------------------------- | ------------------------------------------ |
| ۱ — مرجع قطعی             | `discipline`, `entry_type`, `execution_mode`, `forms_per_round` | `[Proposed Backend Contract]`              |
| ۲ — مشتق امن              | `categories[].rounds.length`                                    | قابل استفاده برای `ONE-ROUND / TWO-ROUNDS` |
| ۳ — موارد غیرقابل‌استنتاج | `SINGLE`, `DOUBLE`, `SINGLE-FREESTYLE`                          | فعلاً قابل استخراج نیستند                  |

در نتیجه:

```text
ONE-ROUND / TWO-ROUNDS
```

را می‌توان از `rounds.length` استخراج کرد.

اما:

```text
SINGLE
DOUBLE
SINGLE-FREESTYLE
```

نباید از snapshot فعلی حدس زده شوند.

در صورت نیاز به default موقت، مقدار `SINGLE` فقط به‌عنوان fallback فنی در نظر گرفته می‌شود و نباید به‌عنوان دادهٔ واقعی Backend تلقی شود.

هر Page همچنان یک component است و `mode` را به‌صورت prop دریافت می‌کند.

Route جداگانه‌ای برای mode وجود ندارد.

---

# ۹. Routing / Inertia Contract

در این فاز Routeها تغییر نمی‌کنند.

| مورد           | مقدار فعلی                     |
| -------------- | ------------------------------ |
| Route name     | `scoreboard.show`              |
| URL            | `/tournaments/{id}/scoreboard` |
| Controller     | `ScoreboardController@show`    |
| Inertia render | `Competition/Scoreboard`       |

Export نیز ثابت می‌ماند:

```text
scoreboard.export
/tournaments/{id}/results.csv
```

---

## مسیر نهایی — بعد از Contract نهایی Backend

در آینده:

```text
Competition/Scoreboard
        ↓
Scoreboard/Scoring
```

یعنی:

```php
Inertia::render('Competition/Scoreboard')
```

به:

```php
Inertia::render('Scoreboard/Scoring')
```

تغییر خواهد کرد.

همچنین:

```text
Pages/Competition/Scoreboard.vue
```

به:

```text
Pages/Scoreboard/Scoring.vue
```

منتقل می‌شود.

این تغییر فقط بعد از تأیید Contract نهایی Backend انجام خواهد شد.

تا آن زمان:

```text
URL        = ثابت
Route      = ثابت
Controller = ثابت
Render     = ثابت
```

Pageهای جدید `Pages/Scoreboard/*` در این فاز از Backend render نمی‌شوند.

این موضوع عمدی است.

---

# ۱۰. Proposed Backend Contract

> **تمام موارد این بخش فقط پیشنهادی هستند.**
>
> هیچ‌کدام در این فاز در Backend پیاده‌سازی نمی‌شوند و Frontend نیز آن‌ها را دادهٔ قطعی فرض نمی‌کند.

| # | Field                  | ساختار پیشنهادی                              | مصرف‌کننده             | دلیل                                |
| - | ---------------------- | -------------------------------------------- | ---------------------- | ----------------------------------- |
| 1 | `discipline`           | `recognized \| freestyle` روی category       | Standby, Scoring, Draw | variant `SINGLE-FREESTYLE`          |
| 2 | `entry_type`           | `individual \| pair \| team` روی category    | Standby, Scoring, Draw | variant `DOUBLE`                    |
| 3 | `execution_mode`       | `alternating \| simultaneous` روی category   | Scoring                | چیدمان دوطرفهٔ DOUBLE               |
| 4 | `forms_per_round`      | `int`، پیش‌فرض `2`                           | Scoring, Result        | تعداد فرم هر اجرا                   |
| 5 | ~~`photo_url`~~        | **حذف موقت** (عکس ورزشکار نمایش داده نمی‌شود) | —                 | تا اطلاع بعدی                         |
| 6 | `entry_members[]`      | `[{name, club, position}]` روی entry         | Standby, Scoring, Draw | نمایش اعضای DOUBLE                  |
| 7 | `placements[]`         | `[{rank, entry_id, name, country, country_code, flag_url}]` | TopFour, Ranking | رتبه‌های نهایی؛ مدال از `rank`     |
| 8 | `Draw.output_snapshot` | خروجی قرعه، ترتیب اجرا و slot زمانی          | Draw                   | اطلاعات Draw فعلاً در snapshot نیست |

### موارد ثانویه

در صورت نیاز Backend:

```text
draw_timing
min_duration_seconds
max_duration_seconds
entry_members.position
schema_version
```

### مواردی که Contract جدید لازم ندارند

این موارد از snapshot فعلی قابل استفاده هستند:

```text
rounds.length
→ ONE-ROUND / TWO-ROUNDS

standings
→ برای format=round_robin
```

---

# ۱۱. Realtime Requirements

مکانیزم فعلی بدون تغییر باقی می‌ماند.

```text
Backend
    ↓
CompetitionUpdated
    ↓
private channel: tournaments.{id}
    ↓
competition.updated
    ↓
useCompetitionUpdates(id)
```

در Frontend:

```text
Echo / Mercure
    ↓
debounce 200ms
    ↓
router.reload({
    only: ['tournament']
})
```

در حالت بدون Mercure:

```text
state = polling
```

همچنین Scoreboard فعلی:

```text
usePoll(2000, {
    only: ['tournament']
})
```

را دارد و این polling به‌عنوان پوشش امن باقی می‌ماند.

Banner توقف به‌روزرسانی:

```text
اگر props بیش از ۱۲ ثانیه به‌روز نشده باشد
→ «به‌روزرسانی متوقف شده»
```

---

## رابطهٔ Realtime با Layout

در این فاز:

```text
Realtime
    ↓
Page
```

نه:

```text
Realtime
    ↓
Layout
```

`ScoreboardLayout` فقط chrome است و داده را مدیریت نمی‌کند.

اگر در آینده realtime بین تمام Scoreboard Pageها مشترک شد، می‌توان آن را به composable اختصاصی Scoreboard منتقل کرد:

```text
Components/scoreboard/composables/
```

اما فعلاً چنین composable جدیدی ساخته نمی‌شود.

اگر وضعیت اتصال در Layout نمایش داده شود، از طریق prop دریافت خواهد شد.

Layout خودش وضعیت اتصال را تولید نمی‌کند.

---

# ۱۲. Dependency Rules

قواعد نهایی:

1. `Pages/Scoreboard → Components/scoreboard → Shared`
2. وابستگی در جهت معکوس ممنوع است.
3. `Shared` نباید به Page یا Domain خاص وابسته باشد.
4. Layout فقط slot می‌پذیرد و از Page و دادهٔ Domain خبر ندارد.
5. داده فقط از Inertia props می‌آید.
6. fetch مستقیم ممنوع است.
7. Store جدید ساخته نمی‌شود.
8. دادهٔ موجود نبودن باید با `null-safe` handling مدیریت شود.
9. برای دادهٔ آینده فقط `[Proposed Backend Contract]` نوشته می‌شود.
10. mock، fixture و workaround ساخته نمی‌شود.
11. هیچ Backend change یا migration در این فاز انجام نمی‌شود.
12. Route و URL `scoreboard.show` ثابت می‌مانند.

---

# ۱۳. خارج از Scope

فعلاً انجام نمی‌شود:

* تغییر کد Backend
* Migration
* Contract قطعی Backend
* استخراج component از `Competition/Scoreboard.vue`
* wiring اختصاصی Pageها فراتر از دادهٔ فعلی
* تغییر `Inertia::render`
* انتقال `Competition/Scoreboard.vue`
* پیاده‌سازی کامل UI همهٔ Pageها
* پشتیبانی واقعی از `team`
* پشتیبانی واقعی از `freestyle`
* پشتیبانی واقعی از `simultaneous`
* ساخت Pinia store
* ایجاد معماری data جدید

---

# ۱۴. مسیر توسعه

## فاز جاری

هر مرحله جداگانه review و تأیید می‌شود.

| مرحله | محتوا                                                        | وضعیت            |
| ----- | ------------------------------------------------------------ | ---------------- |
| PR-A  | `docs/scoreboard-architecture.md`                            | انجام شد         |
| PR-B  | `Shared/Layouts/ScoreboardLayout.vue` (+ `slot#header`)      | انجام شد         |
| ۱     | ساخت ۱۳ کامپوننت در `Components/scoreboard/` + توکن‌های پالت | انجام شد         |
| ۲     | اتصال ۶ صفحه به کامپوننت‌ها + ساخت `LiveBoard`               | انجام شد         |
| ۳     | گزارش و `docs/scoreboard-contract.md` برای Backend          | در جریان         |
| ۴     | پیاده‌سازی Backend بر اساس Contract + وصل صفحات             | منتظر Backend    |

---

## PR-B

فایل:

```text
resources/js/Shared/Layouts/ScoreboardLayout.vue
```

از روی کد مرجع ارائه‌شده ساخته می‌شود.

قواعد:

```text
Preserve structure
Preserve appearance
Adapt only for app integration
No unnecessary rewrite
```

---

## PR-C

صفحات:

```text
Pages/Scoreboard/
├── Standby.vue
├── Scoring.vue
├── Result.vue
└── Ranking.vue
```

این صفحات:

* داخل `ScoreboardLayout` قرار می‌گیرند.
* فقط از دادهٔ فعلی استفاده می‌کنند.
* mock ندارند.
* route جدید ندارند.
* Controller تغییر نمی‌کند.
* `Competition/Scoreboard.vue` حذف یا منتقل نمی‌شود.

---

## PR-D

صفحات:

```text
Pages/Scoreboard/
├── TopFour.vue
└── Draw.vue
```

این دو صفحه در این مرحله فقط skeleton هستند.

داده‌های موردنیاز آینده با:

```text
[Proposed Backend Contract]
```

مشخص می‌شوند.

---

# مراحل بعد از این فاز

بعد از نهایی شدن Backend Contract:

1. Backend Contract نهایی تأیید و ارائه می‌شود.
2. `Competition/Scoreboard` به `Scoreboard/Scoring` منتقل می‌شود.
3. `Inertia::render` به مسیر جدید اشاره می‌کند.
4. فایل قدیمی حذف می‌شود.
5. صفحات بر اساس Contract قطعی داده‌محور می‌شوند.
6. mode و variant واقعی اضافه می‌شوند.
7. `placements[]`، `Draw.output_snapshot` و عکس‌ها اضافه می‌شوند.
8. UI نهایی مطابق طراحی‌های Figma پیاده‌سازی می‌شود.

---

# Verification

بعد از هر مرحله:

```bash
npm run build
php artisan test
```

به‌خصوص:

```text
CompetitionConsoleTest
CompetitionWorkflowTest
```

باید همچنان pass باشند.
