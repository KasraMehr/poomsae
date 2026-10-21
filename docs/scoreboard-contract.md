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
| Scoring | `Scoring.vue` | صفحهٔ زندهٔ امتیازدهی؛ layout تک‌نفره، دوتایی یا فری‌استایل بر پایهٔ category و جزئیات نمرهٔ backend. | پیاده‌سازی فرانت؛ هم‌ترازی قرارداد هنگام مرج |
| Result | `Result.vue` | نمایش نتیجهٔ boutهای تکمیل‌شدهٔ اجرای جاری؛ چیدمان یک یا دو راند بر اساس راندهای تکمیل‌شده، بدون برجسته‌سازی برنده. | متصل به snapshot |
| Ranking | `Ranking.vue` | نمایش رتبه، کشور، نام و امتیازهای `R-1` و `R-2` با `RankingTable` بر پایهٔ `category.placements[]`. | پیاده‌سازی فرانت؛ نیازمند تکمیل قرارداد Backend |
| TopFour | `TopFour.vue` | چهار رتبهٔ برتر هر دسته با مدال، اطلاعات کشور و امتیاز نهایی مستقل از `placements[]`. | پیاده‌سازی فرانت؛ نیازمند تکمیل قرارداد Backend |
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
| `ScoreItem` | یک آیتم امتیاز: ردیف `label … /max` و عدد در جعبهٔ تیره زیرش. `variant="sub"` یا `variant="total"`; قالب ۲ رقم اعشار با ممیز لاتین و مقدار غایب `--`. Scoring از اندازه‌های `scoring` و `scoring-compact` استفاده می‌کند. | Result و Scoring |
| `SingleScoringTable` | جدول تک‌نفره — **سطر = داور**، هدر ثابت انگلیسی `JUDGE / ACCURACY / EOE / S&P / R&P / PRESENTATION`. با `showSubScores=false` سه ستون ریزنمرات حذف می‌شوند و **عرض ستون‌های باقی تغییر نمی‌کند**. ستون/ردیف «مجموع» ندارد. | Scoring |
| `FreestyleScoringTable` | جدول فری‌استایل — سطر = داور، سه ستون `JUDGE / ACCURACY / PRESENTATION`. با `showSubScores=true` ستون ACCURACY به **۴** و PRESENTATION به **۶ زیرستون (بدون هدر)** تقسیم می‌شود؛ با `false` همان سه ستون می‌ماند و عرض ثابت است. | Scoring (بعد از داشتن `execution_mode`) |
| `DoubleScoringTable` | جدول **یک سمت** (یک ورزشکار) — ستون = داوران، سطر = دسته‌های نمره. صفحه دو بار رندر می‌کند: `side="chung"` (چپ، برچسب‌ها چپ، آبی) و `side="hong"` (راست، **آینه**: ترتیب Jn..J1 و برچسب‌ها راست، قرمز). با `showSubScores=false` سطرهای ریزنمرات مخفی می‌شوند. | Scoring (بعد از داشتن `execution_mode`) |
| `RankingTable` | جدول رنکینگ (طرح `RANKING-TABLE`) — **بدون هدر** (هدر متعلق به صفحهٔ مصرف‌کننده است)؛ ردیف اول بزرگ‌تر و سایر ردیف‌ها هم‌اندازه‌اند. | Ranking |
| `RankingRow` | ردیف رنکینگ: جعبهٔ رتبه · پرچم+کد · نام · **دو ستون نمرهٔ راند** (`roundScores=[R-1, R-2]` — مقدار خالی `—` نمایش داده می‌شود)؛ رتبهٔ اول بزرگ‌تر و سایر ردیف‌ها هم‌اندازه‌اند. | داخل RankingTable |
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
| **دادهٔ موجود** | `category.discipline/format/execution_mode/judge_count/rules`، `bout.entries[]`، `performance.form_name/form_number` و `performance.detailed_scores` در snapshot امتیازدهی قابل‌نمایش؛ دادهٔ جزئی پیش از انتشار/نمایش مجاز می‌تواند خالی باشد. |
| **قرارداد مصرف فرانت** | `detailed_scores` شامل `discipline`, `method`, `accuracy`, `accuracy_max`, `presentation`, `presentation_max`, `total`, `total_max`, `judges[]` است. فرانت total و میانگین را محاسبه نمی‌کند؛ اعداد و سقف‌ها را از پاسخ می‌خواند. نبودن مقدار به‌شکل `—` می‌ماند. |
| **انتخاب layout** | `discipline=freestyle` → `FreestyleScoringTable`. در غیر این صورت `format=knockout` همراه `execution_mode=simultaneous` → دو جدول `DoubleScoringTable`. سایر دسته‌ها → `SingleScoringTable`. `execution_mode` همچنان فقط `alternating \| simultaneous` است و معنای SINGLE/DOUBLE ندارد. |
| **ساختار `judges[]`** | هر آیتم: `seat`, `label`, `accuracy`, `presentation`, `total`, `accuracy_components[]`, `presentation_components[]`, `accuracy_penalties[]`, `presentation_penalties[]`. Single/Double سه مقدار `presentation_components` را به EOE/S&P/R&P نگاشت می‌کنند. |
| **ریز امتیاز Freestyle** | `method=accuracy_4_presentation_6`: چهار `accuracy_components[]` و شش `presentation_components[]`. `method=accuracy_6_presentation_4`: سه `accuracy_components[]` به‌علاوهٔ چهار `presentation_penalties[]` که فرانت با علامت منفی نمایش می‌دهد. سقف‌ها و نتایج نهایی از `detailed_scores` می‌آیند؛ فرانت از روی شمار زیرامتیازها حدس نمی‌زند. |
| **وضعیت پذیرش امتیاز (آینده)** | فرانت از metadata اختیاری داور مثل `acceptance`، `accepted` یا `score_status`، به‌شکل `{ accuracy, presentation, eoe, sp, rp, accuracy_components[], presentation_components[], presentation_penalties[] }` پشتیبانی می‌کند. مقدار `false` یا `"rejected"` باعث نمایش قرمز و خط‌خورده می‌شود؛ نبود metadata یعنی نمایش عادی سفید. منبع این metadata بعداً با Backend هماهنگ می‌شود. |
| **هویت ورزشکار** | `bout.entries[]` می‌تواند `number`, `country`, `country_code`, `flag_url` داشته باشد؛ نبودنشان اختیاری است و به layout امتیازدهی لطمه نمی‌زند. |

### ۳.۴ Result

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | boutهای `status=completed` پس از ثبت نمره‌ها؛ هر bout شامل دو `entries[]` با `id/name/side`، مقدارهای `accuracy_score/presentation_score` روی entryها و `totals` با کلید `entry_id`. Tournament می‌تواند همچنان `status=running` باشد. |
| **منطق نمایش** | Result فقط boutهای تکمیل‌شده را می‌خواند و آن‌ها را درون category بر اساس همان دو `entry.id` گروه‌بندی می‌کند. یک bout تکمیل‌شده، چیدمان یک‌راند (`ROUND 1`) می‌دهد؛ دو bout تکمیل‌شده برای همان جفت، چیدمان دوراهه (`RESULT`) می‌دهد. تعداد راندهای category به‌تنهایی معیار نیست. در هر راند، Accuracy و Presentation از entry همان bout و Total از `bout.totals[entry_id]` می‌آیند؛ فرانت امتیازها را محاسبه نمی‌کند. |
| **نمایش برنده** | `winner_entry_id` ممکن است در snapshot موجود باشد، اما فعلاً برای Result مصرف نمی‌شود و هیچ کادر/رنگ ویژه‌ای برای برنده نمایش داده نمی‌شود. |
| **[Proposed]** | برای `AthleteInfo`: `entry.country/country_code/flag_url`؛ برای هدر: `category.stage` یا `tournament.stage` و `category.name`. اگر این فیلدهای هویتی/هدر در snapshot فعلی موجود نباشند، باید در قرارداد نهایی تأمین شوند. |

### ۳.۵ Ranking

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.standings[]` = `{rank, id, name, wins, played}` فقط برای `format=round_robin`، و `champion_id` برای knockout. این داده‌ها تعداد برد/بازی را دارند و امتیاز اجرای راندها را تأمین نمی‌کنند. snapshot فعلی همچنین entryهای این صفحه را بدون `country/country_code/flag_url` می‌دهد. |
| **[Proposed] منبع ردیف‌ها** | `category.placements[]` یکدست برای هر دو قالب (`round_robin` و `knockout`): `{rank, entry_id, name, score, round_scores, country, country_code, flag_url}`. `round_scores: [number, number]` امتیاز کل هر ورزشکار در راندهای `R-1` و `R-2` است؛ فرانت امتیاز را از تعداد برد یا boutها محاسبه نمی‌کند. `score` رتبه‌بندی نهایی/امتیاز کل است و برای نمایش ستون‌های تصویر استفاده نمی‌شود. |
| **[Proposed] هدر** | `category.stage` (یا `tournament.stage`) برای سمت چپ و `category.name` برای عنوان دسته؛ برچسب‌های دو ستون فعلاً ثابت `R-1` و `R-2` هستند. `logoUrl` اختیاری است و در تصویر مرجع لوگو نمایش داده نمی‌شود. |
| **رفتار فعلی فرانت** | فقط `placements[]` را به `RankingTable` می‌دهد؛ در نبود placements پیام خالی نمایش داده می‌شود و `standings.wins` به‌عنوان نمرهٔ راند استفاده نمی‌شود. Lab با fixture نمونهٔ placements هر پنج ردیف مرجع را نمایش می‌دهد. |

### ۳.۶ TopFour

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.standings[]` فقط رتبه/برد/تعداد بازی را برای `round_robin` می‌دهد؛ snapshot فعلی نمرهٔ نهایی اجرای ورزشکار یا اطلاعات کشور را ندارد و برای knockout نیز placement ارائه نمی‌کند. |
| **`[Proposed]`** | `category.placements[]` مشترک با Ranking شامل `{rank, entry_id, name, score, country, country_code, flag_url}` برای هر دو قالب. فرانت چهار placement با rankهای ۱ تا ۴ را نمایش می‌دهد؛ مدال از rank مشتق می‌شود و `score` همان نمرهٔ نهایی مستقل است، نه تعداد برد و نه `round_scores[1]`. |
| **هدر و Lab** | `category.stage` (یا `tournament.stage`) و `category.name` برای هدر؛ Lab از چهار placement fixture با نمرهٔ مستقل استفاده می‌کند. `logoUrl` در مرجع تصویری استفاده نشده است. |

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
| 6 | `category.discipline/format/execution_mode` | `recognized \| freestyle`; `knockout \| round_robin`; `alternating \| simultaneous` | Scoring (انتخاب layout) |
| 7 | `performance.detailed_scores` | نتایج/سقف‌های هر اجرای فرم به‌همراه `judges[]` | Scoring |
| 8 | `detailed_scores.judges[]` | breakdown داور و زیرامتیازها مطابق بخش ۳.۳ | Scoring (جدول‌ها) |
| 9 | `detailed_scores.accuracy/presentation/total` | عدد nullable از Backend؛ total را فرانت جمع نمی‌کند | Scoring (`ScoreItem`) |
| 9 | `bout.winner_entry_id` | `int` — برندهٔ bout؛ Result فعلاً از آن برای استایل‌دهی استفاده نمی‌کند | اطلاعات نتیجه (نمایش برنده بعداً تعیین می‌شود) |
| 10 | `placements[]` | `[{rank, entry_id, name, score, round_scores, country, country_code, flag_url}]` روی category — `score` امتیاز نهایی مستقل TopFour است؛ `round_scores = [R-1, R-2]` برای ستون‌های Ranking؛ مدال از `rank` مشتق می‌شود | Ranking, TopFour |
| 11 | `Draw.output_snapshot` | خروجی قرعه (ترتیب اجرا/جفت‌ها) | Draw |
| 12 | `entry_members[]` | `[{name, club, position}]` روی entry | Draw, Standby, Scoring |
| 13 | `draw_timing` | `timestamp` | Draw |
| 14 | `form_number` | `int` `1..2` روی performance — ترتیب فرم در اجرای رقابت | Scoring, Result, Draw, Standby, LiveBoard |
| 15 | `symbol_key` | `int` `1..18` روی فرم — کلید سمبل تصویری فرم | `FormBadge` در Scoring, Result, Draw, Standby, LiveBoard |
| 16 | `courtId` (LiveBoard) | query param رسمی | LiveBoard |
| 17 | `timeline` (LiveBoard) | `previous/current/next` صریح با ترتیب سراسری بین زمین‌ها | LiveBoard |
| 18 | `detailed_scores.judges[].*_components/ *_penalties` | سه component برای Single/Double؛ Freestyle با قاعدهٔ ۴+۶ یا ۶+۴ | Scoring (ستون/سطر ریزنمرات) |

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
