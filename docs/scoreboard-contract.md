# وضعیت دادهٔ صفحات Scoreboard

این سند شکل داده‌ای را ثبت می‌کند که کد فعلی backend تولید می‌کند، نحوهٔ مصرف آن در صفحات Scoreboard و کمبودهایی را که روی انتخاب یا نمایش داده اثر می‌گذارند. بررسی route و دسترسی صفحه خارج از محدودهٔ این سند است.

## منبع دادهٔ فعلی

`ScoreboardController` دادهٔ نمایش را از `CompetitionDisplay::snapshot` می‌گیرد. این action، `CompetitionView::snapshot(..., display: true)` را فراخوانی می‌کند و پاسخ را به این شکل می‌سازد:

```text
display
├── tournament   ← snapshot مسابقه، رده‌ها، راندها، boutها، اجراها و standings
├── courts[]     ← زمین‌ها با current و upcoming
├── revision
└── generated_at
```

فیلدهای اصلی در snapshot فعلی:

- `tournament`: نام، وضعیت، زمین‌ها و `categories[]`.
- `category`: قالب و رشته، تعداد فرم و داور، قوانین، شرکت‌کنندگان، راندها، `champion_id` و `standings[]`. دادهٔ `standings` فقط برای `round_robin` پر می‌شود.
- `round`: نام، `stage_label`، شماره و وضعیت راند، boutها و فرم‌های انتخاب‌شده.
- `bout`: وضعیت، زمین، شرکت‌کنندگان، برنده، `totals[entry_id]` و اجراها.
- `performance`: شماره/نام/نشان فرم، وضعیت، زمان، نتیجهٔ منتشرشده یا موقت و `detailed_scores`.
- `display.courts[]`: برای هر زمین `current` و فهرست مرتب‌شدهٔ `upcoming`. اگر همهٔ اجراهای یک bout تأیید شده باشند اما نتیجهٔ bout هنوز تعیین نشده باشد، `current.status` می‌تواند `decision` باشد.

`detailed_scores` تا وقتی نتیجه منتشر نشده یا شرایط محاسبهٔ موقت فراهم نشده باشد ممکن است `null` باشد. محاسبهٔ موقت در وضعیت `scoring` و پس از دریافت نمرهٔ همهٔ داوران در دسترس است.

## وضعیت صفحات

| صفحه | اتصال داده در کد فعلی | کمبود یا محدودیت |
|---|---|---|
| `Competition/Scoreboard` | رده، راند، bout، اجراها، نتیجه‌ها و standings را از `display.tournament` می‌خواند؛ به‌روزرسانی زنده و polling هم از همین envelope استفاده می‌کنند. | «آخرین نتیجه‌ها» با شناسهٔ bout مرتب می‌شود، نه زمان پایان واقعی؛ ممکن است آخرین اجرای تمام‌شده را دقیق نشان ندهد. |
| `Competition/Rtds` | `display.courts[].current/upcoming` را مستقیماً می‌خواند؛ وضعیت `decision` نیز در برچسب‌های فرانت تعریف شده است. | برای داده‌های فعلی کمبود اصلی ندارد. |
| `Standby` | از وضعیت bout و performance، مشخصات ورزشکار، فرم و `live_timing` دادهٔ لازم را می‌گیرد. | `courtId` اختیاری است؛ بدون آن اولین اجرای فعال در ترتیب snapshot انتخاب می‌شود و در چند زمین فعال ممکن است اجرای زمین موردنظر نباشد. |
| `LiveBoard` | `current` و `upcoming` را از دادهٔ زمین می‌گیرد و جزئیات bout را با شناسه از snapshot پیدا می‌کند. | بدون `courtId` به زمین اول برمی‌گردد. `previous` از ترتیب category/round/bout حدس زده می‌شود؛ backend تاریخچهٔ مرتب‌شدهٔ اجراهای همان زمین را نمی‌فرستد، پس انتخاب قبلی قطعی نیست. |
| `Scoring` | اجراهای فعال، نوع جدول، هویت شرکت‌کنندگان و جزئیات داورها را از category/bout/performance می‌خواند. | جزئیات ممکن است تا ثبت نمرهٔ همهٔ داوران خالی بماند. نام کشور و پرچم واقعی هم در دادهٔ bout نیست. |
| `Result` | boutهای تکمیل‌شده، هویت شرکت‌کنندگان و Total رسمی هر bout را می‌خواند؛ برای هر entry نمرهٔ جزئی را از performance می‌گیرد. | صفحه همهٔ matchupهای تکمیل‌شده را می‌سازد، چون نشانگر «نتیجهٔ جاری» ندارد. اگر هر entry در bout چند performance داشته باشد، Accuracy/Presentation تجمیعی در snapshot نیست و صفحه آن‌ها را خودش محاسبه نمی‌کند. |
| `Ranking` | `placements[]` را ترجیح می‌دهد؛ در نبود آن برای `round_robin` از `standings[]` و فیلدهای `rank`, `name`, `country_code`, `r1/r2` استفاده می‌کند. | Backend فعلاً `placements[]` نمی‌فرستد؛ در نتیجه ranking کامل knockout در دسترس نیست. `r1/r2` امتیاز فرم‌های آخرین standings round است، نه الزاماً دو راند مسابقه. فیلد stage یکنواخت برای هدر هم وجود ندارد. |
| `TopFour` | از `placements[]` یا standings مربوط به `round_robin` رتبه‌های ۱ تا ۴ را می‌گیرد. | placementهای knockout موجود نیستند. `standings.score` برای قالب‌های غیر score-based ممکن است `null` باشد؛ بنابراین امتیاز Top Four خالی می‌ماند. فیلد stage هم موجود نیست. |
| `Draw` | از `display.courts[].current` اجرای جاری را می‌گیرد؛ سپس bout، راند، entryها و فرم‌ها را از snapshot پیدا می‌کند. | به `courtId` نیاز دارد؛ اگر مقدار نرسد، اجرای نمایشی پیدا نمی‌شود. فرم‌های راند و `form_badge` اجرا برای محتوای فعلی کافی‌اند. |
| `Winner` | اگر دادهٔ `current_result` موجود باشد، صفحه می‌تواند boutها، برنده، Total و فرم‌ها را به آن وصل کند. | `current_result` در snapshot فعلی وجود ندارد. fallback فقط وقتی کار می‌کند که دقیقاً یک bout تکمیل‌شدهٔ واجد شرایط در کل snapshot باشد؛ ممکن است نتیجهٔ جاری را نشان ندهد. Total تجمیعی و سقف آن هم ارائه نشده‌اند. |

## داده‌های موجود و معانی مهم

- `country_code` در دادهٔ entry موجود است، اما اگر athlete کد کشور نداشته باشد ممکن است از club کوتاه لاتین ساخته شده باشد؛ آن را کد کشور تأییدشده فرض نکنید. `country` و `flag_url` ارائه نمی‌شوند.
- `standings.r1/r2` از نتیجهٔ فرم‌ها بر اساس `form_number` در `standings_round_id` ساخته می‌شوند. این نام‌ها معادل «راند اول و دوم مسابقه» نیستند.
- `standings` فقط برای `round_robin` است. رتبهٔ knockout و placement کامل هر دو قالب در snapshot نیست.
- `bout.totals[entry_id]` Total منتشرشدهٔ bout را می‌دهد، اما سقف Total تجمیعی را نمی‌دهد.
- `performance.form_badge` برای فرم‌های شناخته‌شده کلید سمبل فرانت است؛ `form_number` شمارهٔ ترتیب اجراست و معنای متفاوتی دارد.
- `entry.number`, `photo_url`, `symbol_key` مستقل و `execution_duration_seconds` ریشهٔ tournament برای نمایش فعلی لازم نیستند: شماره/عکس استفاده نمی‌شوند، سمبل از `form_badge` می‌آید و مدت از `live_timing.duration_seconds` قابل دریافت است.

## کمبودهای داده برای تکمیل نمایش

این موارد در backend فعلی وجود ندارند و فقط در صورت نیاز محصول باید به قرارداد افزوده شوند:

1. **نتیجهٔ جاری:** `tournament.current_result` با حداقل `status`, `category_id`, `round_count`, `bout_ids`, `winner_entry_id`, `winner_total` و `total_max`. `round_count` یک یا دو است و `bout_ids` باید مرتب و مربوط به همان matchup باشد. سقف Total هر bout هم در صورت نمایش لازم است.
2. **Placement یکنواخت:** `category.placements[]` برای هر دو قالب، با `rank`, `entry_id`, `name`, `score`, `round_scores`, `country_code` و در صورت پشتیبانی `flag_url`. `score` باید امتیاز رتبه‌بندی نهایی باشد؛ فرانت از wins یا bracket رتبه و امتیاز نسازد.
3. **نتیجهٔ چندفرمی:** اگر Result باید Accuracy/Presentation یک entry را برای bout چندفرمی نشان دهد، backend باید مقدار تجمیع‌شده و سقف‌های همان bout را بدهد؛ فرانت نباید منطق امتیازدهی را تکرار کند.
4. **تاریخچهٔ زمین:** اگر LiveBoard باید Previous قطعی داشته باشد، backend باید اجرای قبلی یا timeline مرتب‌شدهٔ همان زمین را ارائه کند.
5. **هویت کشور:** اگر نام کشور و پرچم واقعی لازم است، دادهٔ معتبر `country` و `flag_url` به entry اضافه شود؛ مقدار حدس‌زده از club برای این منظور کافی نیست.

`courtId` برای انتخاب زمین در Standby، LiveBoard و Draw ورودی صفحه است؛ در کد فعلی الزامی نیست یا fallback یکسانی ندارد. هر مصرف‌کننده باید زمین هدف را صریحاً تعیین کند تا اجرای اشتباه انتخاب نشود.

## جمع‌بندی وضعیت

- صفحات فعلی `Competition/Scoreboard` و `Competition/Rtds` دادهٔ نمایش زنده را مصرف می‌کنند.
- صفحات ماژولار `Pages/Scoreboard/*` از نظر شکل propها می‌توانند `display.tournament` را مصرف کنند؛ داده‌های صفحه‌های Lab نمونه‌اند و backend را جایگزین نمی‌کنند.
- Standby، Scoring و Draw دادهٔ پایهٔ لازم برای محتوای فعلی را دارند، با محدودیت انتخاب زمین در Standby/Draw.
- LiveBoard برای Previous، Result برای انتخاب نتیجهٔ جاری، Winner برای انتخاب برندهٔ نتیجهٔ جاری، و Ranking/TopFour برای placement کامل به دادهٔ تکمیلی نیاز دارند.
- این سند فقط وضعیت و قرارداد داده را پوشش می‌دهد؛ اتصال صفحه‌ها به route یا تغییر route بخشی از این بررسی نیست.
