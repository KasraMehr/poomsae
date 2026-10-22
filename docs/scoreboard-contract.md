# Scoreboard — قرارداد پیشنهادی داده (Frontend → Backend)

> **وضعیت:** صفحات Scoreboard مرحله‌به‌مرحله با snapshot واقعی Backend تطبیق داده می‌شوند؛ این سند وضعیت هر صفحه را جداگانه ثبت می‌کند.
>
> این سند برای مطالعهٔ تیم Backend نوشته شده تا بداند **هر صفحه چه داده‌ای لازم دارد**
> و بر اساس همین، قرارداد نهایی تأمین شود.
>
> * `[Proposed Backend Contract]` فقط برای داده‌ای استفاده می‌شود که بررسی همان صفحه نشان دهد در پاسخ فعلی موجود نیست.
> * پاسخ صفحهٔ نمایشگر از `ScoreboardController` و `CompetitionDisplay::snapshot` می‌آید؛ `CompetitionDisplay` دادهٔ پایه را از `CompetitionView::snapshot(..., $display = true)` می‌گیرد و در `display.tournament` قرار می‌دهد.
> * route فعلی هنوز `Pages/Competition/Scoreboard.vue` را render می‌کند؛ صفحات `Pages/Scoreboard/*` فعلاً در production از این route فراخوانی نمی‌شوند. در مرحلهٔ Standby، route wrapper عمداً تغییر نمی‌کند.

---

## ۱. درخت صفحات و کامپوننت‌ها

### ۱.۱ صفحات (`Pages/Scoreboard/`)

| صفحه | فایل | هدف | وضعیت |
| ------ | ---- | --- | ----- |
| Standby | `Standby.vue` | نمایش **اجرای فعلی + Timer اجرا + state پایان** (`running` / `scoring`). بدون صف، بدون اجرای قبلی/بعدی. | با envelope واقعی `display.tournament` سازگار شد؛ route production هنوز به این صفحه وصل نیست |
| LiveBoard | `LiveBoard.vue` | Broadcast board وضعیت **یک زمین**: `PREVIOUS ↓ CURRENT ↓ NEXT`. Timer اینجا مسئولیت اصلی نیست. | با envelope واقعی `display.tournament` سازگار شد؛ route production هنوز به این صفحه وصل نیست |
| Scoring | `Scoring.vue` | صفحهٔ زندهٔ امتیازدهی؛ layout تک‌نفره، دوتایی یا فری‌استایل بر پایهٔ category و جزئیات نمرهٔ backend. | با envelope واقعی `display.tournament` سازگار شد؛ route production هنوز به این صفحه وصل نیست |
| Result | `Result.vue` | نمایش نتیجهٔ boutهای تکمیل‌شدهٔ اجرای جاری؛ چیدمان یک یا دو راند بر اساس راندهای تکمیل‌شده، بدون برجسته‌سازی برنده. | با envelope واقعی `display.tournament` سازگار شد؛ route production هنوز به این صفحه وصل نیست |
| Ranking | `Ranking.vue` | نمایش رتبه، کشور، نام و امتیازهای `R-1` و `R-2` با `RankingTable`؛ از placements یا standings سازگار تغذیه می‌شود. | با envelope واقعی `display.tournament` و rankهای round-robin سازگار شد؛ کامل‌بودن داده‌ها نیازمند هماهنگی قرارداد است |
| TopFour | `TopFour.vue` | چهار رتبهٔ برتر هر دسته با مدال، اطلاعات کشور و امتیاز نهایی؛ از placements یا standings سازگار تغذیه می‌شود. | با envelope واقعی `display.tournament` و داده‌های round-robin سازگار شد؛ برای knockout کمبود placement باقی است |
| Draw | `Draw.vue` | قرعه‌کشی: فرم‌ها و ورزشکاران هر دسته. | متصل به snapshot (با TODO) |

### ۱.۲ کامپوننت‌ها (`Components/scoreboard/`)

| کامپوننت | هدف | مصرف‌کننده |
| ---------- | --- | ---------- |
| `ScoreboardHeader` | هدر مشترک: لوگو + نام رویداد + دور/زمین. از `slot#header` به Layout تزریق می‌شود. | همهٔ صفحات |
| `AthleteInfo` | نوار نام رنگی (شماره + نام) + بج کشور؛ چهار چیدمان `name-only`/`horizontal`/`vertical`/`vertical-under` و دو سمت آینه‌ای (`chung` چپ، `hong` راست). بدون عکس. | Standby, LiveBoard, Scoring, Result, Draw |
| `NationalityBadge` | پرچم + کد سه‌حرفی انگلیسی کشور؛ دو جهت پرچم و placeholder وقتی پرچم نباشد. | داخل AthleteInfo (ردیف‌های Ranking/TopFour بلوک کشور خودشان را دارند — ارتفاع relative به ردیف) |
| `FormBadge` | ردیف `[roundLabel] [symبل فرم] [formName]` (بدون کادر)، در سه سایز (`sm/md/lg`). | Standby, LiveBoard, Scoring, Draw |
| `SymbolContainer` | نگه‌دارندهٔ یک تصویر سمبل (فرم یا مدال) در سه سایز؛ مسیر از `Shared/Services/symbols.js`. | داخل FormBadge |
| `Timer` | **فقط نمایش** — شمارش معکوس از prop `durationSeconds` که در Standby و LiveBoard از `performance.live_timing.duration_seconds` پر می‌شود. سه حالت کاملاً مشتق از props: `idle` (پیش از شروع، `startedAt` خالی) / `running` (`startedAt` هست، `endedAt` خالی) / `stopped` (هر دو هست). هیچ state تایمری در فرانت وجود ندارد؛ کنترل کامل از پنل اپراتور است. در صفر روی `00:00` می‌ایستد (نه قرمز، نه اعلام خودکار). دو سایز: `lg` در Standby، `sm` در LiveBoard. | Standby, LiveBoard |
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
| **دادهٔ موجود و محل مصرف** | `display.tournament.status/name/courts[]` برای وضعیت، نام رویداد و حالت بدون اجرای فعال؛ `categories[].execution_mode` برای چیدمان تک/دو نفره؛ `round.name` و `category.name` برای هدر؛ `bout.status/court_id`, `performances[]`, `entries[].id/name/side/country_code` برای یافتن اجرا و هویت؛ `performance.status/started_at/ended_at` برای state و Timer؛ `performance.live_timing.duration_seconds` برای مدت Timer؛ `performance.form_name` برای عنوان فرم؛ `performance.form_badge` برای انتخاب سمبل فرم‌های شناخته‌شده. `country_code` مستقیماً از athlete گرفته می‌شود یا در صورت خالی‌بودن از `club` کوتاهِ لاتین ساخته می‌شود؛ بنابراین همیشه کد کشورِ تأییدشده نیست. |
| **`[Proposed]` دادهٔ واقعاً غایب** | `logoUrl` برای نشان رویداد در `ScoreboardHeader`؛ `entry.country` و `entry.flag_url` برای نام کشور و پرچم واقعی کنار کد کشور. فعلاً `country_code` موجود است و بدون `flag_url` کامپوننت کد را به‌صورت placeholder نشان می‌دهد. ~~`entry.photo_url`~~ **حذف موقت** (ردیف ۲ بخش ۴). |
| **داده‌هایی که لازم نیست از Backend اضافه شوند** | `execution_duration_seconds` در ریشهٔ tournament لازم نیست: مدت اجرا از `performance.live_timing.duration_seconds` موجود است. `entry.number` در UI فعلی نمایش داده نمی‌شود؛ بخش رندر شماره در `AthleteInfo` غیرفعال است. `symbol_key` مستقل ارائه نشده، اما `form_badge` در Backend برای فرم‌های شناخته‌شده موجود است؛ ترتیب `form_number` به‌عنوان کلید سمبل استفاده نمی‌شود. |
| **نکتهٔ اتصال** | `Standby.vue` می‌تواند هم snapshot مستقیم آزمایشگاه و هم envelope واقعی `display` را بخواند؛ بااین‌حال route فعلی `scoreboard.show` هنوز `Competition/Scoreboard` را render می‌کند و این صفحه را فراخوانی نمی‌کند. در این مرحله wrapper فرانت‌اند تغییر نمی‌کند؛ بنابراین Standby هنوز از route production قابل دسترسی نیست. |

### ۳.۲ LiveBoard

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود و محل مصرف** | `display.tournament.status/name/courts[]` برای هدر و انتخاب زمین؛ `display.courts[].current` منبع مرجع CURRENT و `display.courts[].upcoming[]` منبع مرجع NEXT است؛ اطلاعات تکمیلی bout با `bout_id` از `display.tournament.categories[].rounds[].bouts[]` پیدا می‌شود. `bout.completed/winner_entry_id/totals[entry_id]` و snapshot ترتیب دسته/راند/bout برای PREVIOUS؛ `round.name/sequence` و `bout.sequence` برای برچسب‌ها؛ `entries[].name/side/country_code` برای هویت؛ `performance.form_name/form_badge` برای عنوان و سمبل فرم؛ `started_at/ended_at` و `live_timing.duration_seconds` برای Timer. اگر مدت در `live_timing` نباشد، از `timing.maximum_duration_seconds` استفاده می‌شود؛ در نبود هر دو تایمر نمایش داده نمی‌شود. |
| **محدودیت استنتاج ترتیب** | Backend برای هر زمین current و upcoming مرتب‌شده را می‌دهد و صفحه از آن‌ها برای دو بخش استفاده می‌کند. snapshot برای PREVIOUS فهرست تاریخچه یا timeline مرتب‌شدهٔ اجراهای گذشته نمی‌دهد؛ صفحه آخرین bout تکمیل‌شده را با ترتیب دسته/راند/bout داخل snapshot پیدا می‌کند. چون `sequence` در هر راند محلی است، در صورت تفاوت این ترتیب با برنامهٔ واقعی اجرا، previous قطعی نیست. |
| **`[Proposed]` دادهٔ واقعاً غایب** | `courtId` به‌صورت query param/prop قابل اتکای route برای انتخاب زمین (در نبود آن صفحه اولین زمین snapshot را انتخاب می‌کند)؛ تاریخچه/previous مرتب‌شده برای همان زمین؛ `entry.country` و `entry.flag_url` برای نام کشور و تصویر پرچم واقعی؛ `logoUrl` برای نشان رویداد در هدر. `country_code` موجود است اما ممکن است از نام کوتاه لاتین باشگاه مشتق شده باشد. |
| **داده‌هایی که لازم نیست از Backend اضافه شوند** | `execution_duration_seconds` جداگانه لازم نیست: snapshot فعلی `performance.live_timing.duration_seconds` را برای Timer دارد. `symbol_key` مستقل لازم نیست: برای فرم‌های شناخته‌شده، `performance.form_badge` کلید عددی سمبل است؛ `form_number` به‌عنوان کلید سمبل استفاده نمی‌شود. |
| **نکتهٔ اتصال** | `LiveBoard.vue` می‌تواند هم snapshot مستقیم آزمایشگاه و هم envelope واقعی `display` را بخواند؛ بااین‌حال route فعلی `scoreboard.show` هنوز `Competition/Scoreboard` را render می‌کند و این صفحه را فراخوانی نمی‌کند. در این مرحله wrapper فرانت‌اند تغییر نمی‌کند؛ بنابراین LiveBoard هنوز از route production قابل دسترسی نیست و route نیز `courtId` را به‌عنوان prop نمی‌فرستد. |

### ۳.۳ Scoring

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود و محل مصرف** | `display.tournament.status` برای وضعیت Layout و `name` برای متن مرکزی آن؛ `categories[]` برای پنل‌ها؛ `category.discipline/format/execution_mode/judge_count/rules` برای انتخاب جدول و تعداد داورها؛ `bout.status=running` به‌همراه `performance.status` برای پنل‌های فعال؛ `bout.entries[]` و `performance.entry_id` برای اتصال هویت؛ `round.sequence/name` برای برچسب راند؛ `performance.form_name/form_badge` برای مشخصات فرم؛ `performance.detailed_scores` برای نمره‌ها و breakdown داورها؛ `result/preview_result` فقط برای Total در نبود مقدار `detailed_scores.total`. عنوان ثابت `SCORING` در هدر فعلاً از داده نمی‌آید و دست‌نخورده مانده است. |
| **زمان در دسترس بودن جزئیات** | Backend پاسخ `detailed_scores` را از نتیجهٔ منتشرشده یا محاسبهٔ موقت می‌سازد؛ محاسبهٔ موقت وقتی در دسترس است که performance در `scoring` باشد و همهٔ داوران assign‌شده نمره داده باشند. در غیر این صورت، جدول خالی می‌ماند و عددی از فرانت ساخته نمی‌شود. |
| **قرارداد مصرف فرانت** | `detailed_scores` شامل `discipline`, `method`, `accuracy`, `accuracy_max`, `presentation`, `presentation_max`, `total`, `total_max`, `judges[]` است. فرانت total و میانگین را محاسبه نمی‌کند؛ اعداد و سقف‌ها را از پاسخ می‌خواند. `judges[]` می‌تواند `accuracy_components[]`, `presentation_components[]` و `presentation_penalties[]` داشته باشد. |
| **انتخاب layout** | `discipline=freestyle` → `FreestyleScoringTable`. در غیر این صورت `format=knockout` همراه `execution_mode=simultaneous` → دو جدول `DoubleScoringTable`. سایر دسته‌ها → `SingleScoringTable`. `execution_mode` همچنان فقط `alternating \| simultaneous` است و معنای SINGLE/DOUBLE ندارد. |
| **ساختار `judges[]`** | هر آیتم: `seat`, `label`, `accuracy`, `presentation`, `total`, `accuracy_components[]`, `presentation_components[]`, `accuracy_penalties[]`, `presentation_penalties[]`. Single/Double سه مقدار `presentation_components` را به EOE/S&P/R&P نگاشت می‌کنند. |
| **ریز امتیاز Freestyle** | `method=accuracy_4_presentation_6`: چهار `accuracy_components[]` و شش `presentation_components[]`. `method=accuracy_6_presentation_4`: سه `accuracy_components[]` به‌علاوهٔ چهار `presentation_penalties[]` که فرانت با علامت منفی نمایش می‌دهد. سقف‌ها و نتایج نهایی از `detailed_scores` می‌آیند؛ فرانت از روی شمار زیرامتیازها حدس نمی‌زند. |
| **وضعیت پذیرش امتیاز (آینده)** | فرانت از metadata اختیاری داور مثل `acceptance`، `accepted` یا `score_status`، به‌شکل `{ accuracy, presentation, eoe, sp, rp, accuracy_components[], presentation_components[], presentation_penalties[] }` پشتیبانی می‌کند. مقدار `false` یا `"rejected"` باعث نمایش قرمز و خط‌خورده می‌شود؛ نبود metadata یعنی نمایش عادی سفید. منبع این metadata بعداً با Backend هماهنگ می‌شود. |
| **دادهٔ واقعاً غایب** | `entry.country` و `entry.flag_url` در `bout.entries[]` نیستند؛ `country_code` موجود است و صفحه آن را به `AthleteInfo` می‌دهد، اما ممکن است از نام کوتاه لاتین باشگاه مشتق شده باشد. شمارهٔ entry در snapshot bout ارائه نمی‌شود؛ `AthleteInfo` در حال حاضر شماره را رندر نمی‌کند. `symbol_key` جدا وجود ندارد، اما صفحه از `performance.form_badge` موجود استفاده می‌کند. |
| **نکتهٔ اتصال** | `Scoring.vue` می‌تواند snapshot مستقیم آزمایشگاه و envelope واقعی `display` را بخواند؛ route فعلی `scoreboard.show` هنوز `Competition/Scoreboard` را render می‌کند و این صفحه را فراخوانی نمی‌کند. در این مرحله wrapper فرانت‌اند تغییر نمی‌کند؛ بنابراین Scoring هنوز از route production قابل دسترسی نیست. |

### ۳.۴ Result

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود و محل مصرف** | `display.tournament.categories[].rounds[]` برای نتیجه‌های هر دسته؛ `round.stage_label/sequence/name` برای stage و عنوان راند؛ boutهای `status=completed` با دو `entries[]` شامل `id/name/side/country_code` برای matchup و هویت؛ `performances[].entry_id/detailed_scores` برای breakdown هر performance؛ `bout.totals[entry_id]` برای Total تجمیعی رسمی راند؛ `category.rules` و `detailed_scores.*_max` برای سقف امتیاز. `winner_entry_id` موجود است اما صفحه آن را برجسته نمی‌کند. |
| **منطق نمایش** | Result boutهای تکمیل‌شده را درون category بر اساس جفت entryها گروه‌بندی می‌کند و roundها را با `round.sequence` مرتب می‌کند. یک bout برای جفت، چیدمان یک‌راند و دو bout چیدمان دوراهه می‌دهد. Total فقط از `bout.totals[entry_id]` می‌آید؛ فرانت امتیازها را محاسبه نمی‌کند. Accuracy/Presentation از `performance.detailed_scores` فقط وقتی یک performance برای هر entry در bout باشد قابل نمایش مستقیم است. |
| **دادهٔ واقعاً غایب** | برای دسته‌های چندفرمی که هر entry در bout چند performance دارد، snapshot نتیجهٔ تجمیعی راند برای Accuracy و Presentation ارائه نمی‌دهد؛ فقط breakdown هر فرم و Total تجمیعی را دارد. صفحه از میانگین‌گیری فرمی خودداری می‌کند. `entry.country` و `entry.flag_url` نیز در bout وجود ندارند؛ `country_code` موجود است، ولی ممکن است از نام کوتاه لاتین باشگاه مشتق شده باشد. |
| **نکتهٔ اتصال** | `Result.vue` می‌تواند snapshot مستقیم آزمایشگاه و envelope واقعی `display` را بخواند؛ route فعلی `scoreboard.show` هنوز `Competition/Scoreboard` را render می‌کند و این صفحه را فراخوانی نمی‌کند. در این مرحله wrapper فرانت‌اند تغییر نمی‌کند؛ بنابراین Result هنوز از route production قابل دسترسی نیست. |

### ۳.۵ Ranking

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود و محل مصرف** | envelope واقعی `display.tournament`; برای `format=round_robin`، `category.standings[]` شامل `rank/id/name/country_code/wins/played/score/r1/r2` است و صفحه رتبه، نام و کد کشور را از آن می‌خواند. `rank=null` حذف می‌شود چون `RankingRow` رتبهٔ واقعی لازم دارد. برای knockout فقط `champion_id` و `entries[]` موجودند؛ رتبه‌بندی کامل از آن‌ها به‌دست نمی‌آید. |
| **معنای `r1/r2` و مصرف آن‌ها** | در `CompetitionView` این دو مقدار از `performance.result.score` مرتب‌شده بر اساس `performance.form_number` ساخته می‌شوند؛ بنابراین امتیاز فرم ۱ و فرم ۲ در آخرین `standings_round_id` هستند، نه لزوماً امتیاز دو راند مسابقه. طبق انتخاب کاربر، صفحه همین دو فیلد را در ستون‌های ثابت R-1/R-2 نمایش می‌دهد؛ عنوان‌ها و ظاهر صفحه تغییر نکرده‌اند. |
| **[Proposed] منبع ردیف‌ها** | `category.placements[]` یکدست برای هر دو قالب (`round_robin` و `knockout`): `{rank, entry_id, name, score, round_scores, country, country_code, flag_url}`. `round_scores: [number, number]` باید با معنای تأییدشدهٔ ستون‌های صفحه منطبق باشد؛ فرانت امتیاز را از تعداد برد یا boutها محاسبه نمی‌کند. `score` رتبه‌بندی نهایی/امتیاز کل است و برای نمایش ستون‌های تصویر استفاده نمی‌شود. |
| **دادهٔ واقعاً غایب** | در standings، `country` و `flag_url` نیستند؛ `country_code` هست اما ممکن است از club کوتاه لاتین مشتق شده باشد. برای knockout کل rank/placement همهٔ entryها و امتیازهای لازم موجود نیستند. `category.stage` و `tournament.stage` هم در snapshot فعلی نیستند؛ `round.stage_label` فقط برای راندهاست و stage واحد category را تعیین نمی‌کند. |
| **[Proposed] هدر** | `category.stage` یا `tournament.stage` برای ستون stage؛ `category.name` موجود است. `logoUrl` اختیاری است و در تصویر مرجع لوگو نمایش داده نمی‌شود. برچسب‌های score columns فعلاً ثابت `R-1` و `R-2` هستند. |
| **رفتار فعلی فرانت** | از `placements[]` استفاده می‌کند، یا اگر placements موجود نباشد از standings round-robin برای rank/نام/کد کشور و `r1/r2` استفاده می‌کند. Lab با fixture نمونهٔ placements هر پنج ردیف مرجع را نمایش می‌دهد. |

### ۳.۶ TopFour

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود و محل مصرف** | envelope واقعی `display.tournament`; برای `format=round_robin`، `category.standings[]` شامل `rank/id/name/country_code/score` است و TopFour ردیف‌های rank 1 تا 4 را از آن می‌سازد. `score` در حالت score-based حاوی امتیاز نهایی اجرای athlete است؛ در حالت‌های دیگر ممکن است null بماند و UI همان مقدار خالی را نشان می‌دهد. |
| **محدودیت knockout** | snapshot فقط `champion_id` را برای دستهٔ knockout کامل‌شده مشخص می‌کند و rank/score سه placement دیگر را نمی‌دهد. فرانت از bracket یا بردهای دورها رتبهٔ کامل استنتاج نمی‌کند. |
| **دادهٔ واقعاً غایب** | `country` و `flag_url` در standings نیستند؛ `country_code` موجود است، اما ممکن است از club کوتاه لاتین مشتق شده باشد. `category.stage` و `tournament.stage` هم در snapshot فعلی نیستند. |
| **`[Proposed]`** | `category.placements[]` مشترک برای هر دو قالب: `{rank, entry_id, name, score, country, country_code, flag_url}`؛ به‌ویژه برای رتبه‌بندی کامل knockout. `score` امتیاز نهایی مستقل است، نه تعداد برد و نه `round_scores[1]`. |
| **هدر و Lab** | `category.name` موجود است؛ فیلد stage یکنواخت برای هدر تأمین نشده. Lab از چهار placement fixture با نمرهٔ مستقل استفاده می‌کند. `logoUrl` در مرجع تصویری استفاده نشده است. |

### ۳.۷ Draw

| دسته | موارد |
| ---- | ----- |
| **دادهٔ موجود** | `category.form_names[]` (ترتیب فرم‌ها)، `category.entries[]` (`name/club`) |
| **[Proposed]** | **`Draw.output_snapshot`** (ترتیب واقعی قرعه: شمارهٔ اجرا/جفت‌ها/slot)، `entry.number`, `entry.entry_type`, `entry_members[]` (`{name, club, position}`)، `draw_timing` (زمان قرعه)، `form_number`, `symbol_key`, `logoUrl` |

---

## ۴. خلاصهٔ فیلدهای `[Proposed Backend Contract]`

> این فهرست مشترک هنوز برای همهٔ صفحات بازبینی نشده است. برای Standby، LiveBoard، Scoring، Ranking و TopFour، `country_code` در پاسخ فعلی موجود است؛ برای سه صفحهٔ اول، `performance.form_number` و `performance.form_badge` نیز موجودند؛ برای Standby و LiveBoard مدت Timer هم موجود است. آن‌ها را برای صفحات بررسی‌شده دادهٔ غایب حساب نکنید.

| # | فیلد | شکل پیشنهادی | مصرف‌کننده |
| - | ---- | ------------ | ---------- |
| 1 | `logoUrl` | `string \| null` روی snapshot | همهٔ صفحات (header) |
| 2 | ~~`entry.photo_url`~~ | **حذف موقت** — تا اطلاع بعدی تأمین نشود (عکس ورزشکار در هیچ صفحه‌ای نمایش داده نمی‌شود) | — |
| 3 | `entry.country` / `entry.flag_url` | رشته روی entry؛ `country_code` در snapshot فعلی برای Standby، LiveBoard، Scoring، Ranking و TopFour موجود است | هر جا نام کشور یا پرچم واقعی لازم باشد؛ سایر صفحات هنوز بررسی نشده‌اند |
| 4 | `entry.number` | `int` روی entry | در snapshot bout موجود نیست؛ `AthleteInfo` شماره را فعلاً رندر نمی‌کند |
| 5 | ~~`execution_duration_seconds`~~ | برای Standby و LiveBoard لازم نیست؛ مدت از `performance.live_timing.duration_seconds` می‌آید | — |
| 6 | `category.discipline/format/execution_mode` | `recognized \| freestyle`; `knockout \| round_robin`; `alternating \| simultaneous` | Scoring (انتخاب layout) |
| 7 | `performance.detailed_scores` | نتایج/سقف‌های هر اجرای فرم به‌همراه `judges[]` | Scoring |
| 8 | `detailed_scores.judges[]` | breakdown داور و زیرامتیازها مطابق بخش ۳.۳ | Scoring (جدول‌ها) |
| 9 | `detailed_scores.accuracy/presentation/total` | عدد nullable از Backend؛ total را فرانت جمع نمی‌کند | Scoring (`ScoreItem`) |
| 10 | `bout.winner_entry_id` | `int` — برندهٔ bout؛ Result فعلاً از آن برای استایل‌دهی استفاده نمی‌کند | اطلاعات نتیجه (نمایش برنده بعداً تعیین می‌شود) |
| 11 | `placements[]` | `[{rank, entry_id, name, score, round_scores, country, country_code, flag_url}]` روی category — `score` امتیاز نهایی مستقل TopFour است؛ `round_scores = [R-1, R-2]` برای ستون‌های Ranking؛ مدال از `rank` مشتق می‌شود | Ranking, TopFour |
| 12 | `Draw.output_snapshot` | خروجی قرعه (ترتیب اجرا/جفت‌ها) | Draw |
| 13 | `entry_members[]` | `[{name, club, position}]` روی entry | Draw (Scoring از اعضای تیم استفاده نمی‌کند) |
| 14 | `draw_timing` | `timestamp` | Draw |
| 15 | `symbol_key` | `int` `1..18` روی فرم — کلید سمبل تصویری فرم | `FormBadge` در Result, Draw (هنوز بررسی نشده‌اند؛ Standby, LiveBoard و Scoring از `form_badge` استفاده می‌کنند) |
| 16 | `courtId` (LiveBoard) | query param رسمی | LiveBoard |
| 17 | `timeline.previous` (LiveBoard) | تاریخچهٔ مرتب‌شدهٔ boutهای همان زمین؛ current/upcoming هم‌اکنون در `display.courts[]` هستند | LiveBoard |
| 18 | `detailed_scores.judges[].*_components/ *_penalties` | سه component برای Single/Double؛ Freestyle با قاعدهٔ ۴+۶ یا ۶+۴ | Scoring (ستون/سطر ریزنمرات) |

**نکتهٔ `NationalityBadge` (کد کشور و پرچم):**

- نمایش بج **فقط کد سه‌حرفی انگلیسی** (`entry.country_code`) است؛ نام فارسی (`entry.country`) صرفاً برای a11y/جست‌جو می‌ماند و جایگزین کد نمی‌شود.
- اگر `flag_url` نبود، Frontend حدس نمی‌زند: همان کد داخل قاب پرچم به‌عنوان placeholder نمایش داده می‌شود (frontend-only، نیاز به دادهٔ جدید ندارد).
- دو جهتِ پرچم (`flagPosition=left|right`) هم صرفاً prop کامپوننت است و قرارداد داده را تغییر نمی‌دهد.
- عکس ورزشکار (`photo_url`) **حذف موقت** است: تا اطلاع بعدی نه در `AthleteInfo` رندر می‌شود نه در `RankingRow`، و backend لازم نیست آن را تأمین کند.

**نکتهٔ سمبل فرم و مدال (asset های داخل مخزن):**

- Backend **URL تصویر نمی‌دهد**؛ snapshot فعلی برای فرم‌های شناخته‌شده `form_badge` (عدد قابل نگاشت به سمبل) می‌دهد؛ قراردادهای پیشنهادی صفحات دیگر از `symbol_key` (۱..۱۸) استفاده می‌کنند. کلید مدال از `rank` (۱..۵) می‌آید.
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

## ۵. وضعیت اتصال و کار باقی‌مانده

```text
Backend   →  snapshot اجرایی فعلی از `CompetitionDisplay` و `CompetitionView` تأمین می‌شود.
Standby   →  با شکل snapshot واقعی تطبیق داده شده؛ route production هنوز آن را render نمی‌کند.
Pages     →  `Pages/Scoreboard/*` فعلاً در `scoreboard-lab` پیش‌نمایش می‌شوند.
باقی‌مانده → تصمیم دربارهٔ wrapper/اتصال فرانت‌اند و ادامهٔ بررسی صفحه‌به‌صفحه.
            
```

> در تطبیق Standby هیچ Route، Controller، Migration یا Business Logic بک‌اندی تغییر نکرده است. Fixtureهای Lab برای پیش‌نمایش باقی می‌مانند و جایگزین snapshot واقعی نیستند.

## الحاقیه قرارداد صفحه Winner (برنده)

صفحه‌ی `Winner` بلافاصله پس از `Result`، برنده‌ی اجرای جاری را اعلام می‌کند. اجرای جاری می‌تواند شامل یک راند یا نتیجه‌ی نهاییِ تجمیعیِ دو راند باشد. در حالت یک‌راندی، صفحه هویت برنده، Total همان راند و زیر آن FormBadge همان راند را وسط‌چین نمایش می‌دهد. در حالت دوراندی، صفحه هویت برنده و Total نهایی را نشان می‌دهد و پایین آن دو ستون دارد که هر کدام FormBadge و Total همان راند را نمایش می‌دهد. `Accuracy` و `Presentation` نمایش داده نمی‌شوند.

| فیلد | ساختار / قانون | کاربرد |
| --- | --- | --- |
| `tournament.current_result.status` | باید `completed` باشد | تعیین آماده‌بودن نتیجه برای نمایش |
| `tournament.current_result.category_id` | شناسه‌ی دسته‌ی اجرای جاری | پیدا کردن اطلاعات شرکت‌کنندگان |
| `tournament.current_result.round_count` | `1` یا `2` | مشخص‌کردن اجرای تک‌راند یا دو‌راند |
| `tournament.current_result.bout_ids` | آرایه‌ی شناسه‌ی boutهای تکمیل‌شده‌ی همین اجرا؛ به تعداد `round_count` | اتصال نتیجه‌ی نهایی به دو شرکت‌کننده‌ی اجرای جاری |
| `tournament.current_result.winner_entry_id` | شناسه‌ی یکی از دو شرکت‌کننده‌ی اجرای جاری | انتخاب ورزشکار برنده |
| `tournament.current_result.winner_total` | Total نهایی و آماده‌ی نمایش که Backend طبق قاعده‌ی رسمی نتیجه تعیین می‌کند | مقدار `ScoreItem` مرکزی؛ فرانت‌اند آن را از نمره‌های راندها محاسبه نمی‌کند |
| `tournament.current_result.total_max` | سقف عددی متناسب با `winner_total` | نمایش سقف Total در `ScoreItem` |
| `bout.totals[entry_id]` | Total منتشرشده‌ی ورزشکار برنده برای همان bout؛ در اجرای دو‌راندی از هر یک از دو bout خوانده می‌شود | `ScoreItem` جداگانه‌ی هر راند؛ سقف هر راند `10` است |
| `round.name` و `performance.form_number/form_name/symbol_key` | نام راند و مشخصات فرم ورزشکار برنده؛ `form_number` با شماره‌ی R-1/R-2 متناظر می‌شود | نمایش `FormBadge` بالای `AthleteInfo`؛ تک‌راندی `xxxl` و دوراندی هر دو badge در یک ردیف با سایز `lg` |
| `entry.name`, `entry.side`, `entry.number`, `entry.country`, `entry.country_code`, `entry.flag_url` | اطلاعات هویتی entry در bout تکمیل‌شده؛ اطلاعات کشور اختیاری است | نمایش هویت برنده با `AthleteInfo` |

برای اجرای یک‌راند، `bout_ids` یک شناسه دارد؛ برای اجرای دو‌راند، دو شناسه دارد. همه‌ی boutهای ارجاع‌شده باید تکمیل شده و متعلق به همان matchup باشند. هر `FormBadge` از round متناظر ساخته می‌شود و مشخصات فرم برنده را از performance همان round می‌گیرد؛ شکل و ترتیب نمایش مانند Draw است. Backend باید برنده و Total نهایی را پس از اعمال قاعده‌ی رسمی تعیین کند؛ فرانت‌اند دو نمره را برای Total نهایی جمع یا میانگین نمی‌گیرد و برنده را از روی `winner_entry_id` یک راند انتخاب نمی‌کند. Total هر راند از `bout.totals[entry_id]` می‌آید. این قرارداد پیشنهادی است و snapshot فعلی هنوز `tournament.current_result` را ندارد.

تا زمان ارائه‌ی این فیلد، fallback فعلی فقط زمانی استفاده می‌شود که دقیقاً یک bout تکمیل‌شده با برنده در snapshot باشد. در حالت چند bout یا نتیجه‌ی دو‌راند بدون `current_result`، صفحه حالت خالی نشان می‌دهد تا برنده را حدس نزند. Lab fixtureهای جداگانه‌ی یک‌راند و دو‌راند را با `current_result` نمایش می‌دهد. تغییری در Backend انجام نشده است.
