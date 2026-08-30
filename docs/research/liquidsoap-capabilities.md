# قدرات Liquidsoap لثلاث حاجات — تبديل زمني، بيانات «الآن يُشغَّل»، ومذيعان معاً

> بحث تذكرة [#3](https://github.com/thabet-toma/alnaqab-news/issues/3).
> **خط الأساس: Liquidsoap 2.2.4** (الإصدار المثبّت على السيرفر فعلياً، من جرد
> التذكرة #5). كل صيغة في هذا الملف موسومة بـ **✅ 2.2.4** إن كانت تعمل كما هي
> على المثبّت، أو **⬆️ ترقية** مع رقم الإصدار الأدنى إن كانت تحتاج أحدث.
>
> **طريقة العمل:** لم أعتمد على أمثلة الإنترنت ولا على الذاكرة. كل ادّعاء مشتقّ
> من الكود المصدري لـ Liquidsoap عند وسم الإصدار نفسه (`v2.2.4` و`v2.4.5`) أو
> من التوثيق الرسمي. الروابط في آخر كل قسم.
>
> **ما لم أستطع تأكيده مذكور صراحةً كذلك** — يُفحص على السيرفر بـ
> `liquidsoap --check /srv/radio/radio.liq` قبل أي نشر.

---

## 0. خلاصة سريعة

| الحاجة | الجواب باختصار |
|---|---|
| تبديل بلاي ليست بلا إعادة تشغيل وبلا انقطاع | **نعم** — بلاي ليست واحدة + أمر السوكيت `<id>.uri <path>` أو `<id>.reload`. الانتقال ينتظر نهاية المقطع الجاري (لا قطع). يعمل على 2.2.4. |
| الآن يُشغَّل: العنوان + المنقضي/المتبقي | **نعم** — ميثودات `last_metadata()` و`elapsed()` و`remaining()` و`duration()` موجودة على 2.2.4، بالإضافة إلى أمر جاهز `/radio.remaining` مسجَّل تلقائياً من المخرج. الدفع إلى نقطة HTTP ممكن لكن **يجب لفّه بـ `thread.run`** على 2.2.4. |
| مذيعان معاً بـ `add` | **نعم** — المدخل الخالي لا يمرّر صمتاً؛ `add` جاهز ما دام أحدهما جاهزاً. **لا تضع `mksafe` على المداخل** (تكسر الأولوية). المحذور الحقيقي `normalize=true` الافتراضي (قفزة مستوى 6dB). الكلفة الحسابية = فكّ ترميز MP3 إضافي واحد؛ **لا يوجد قياس رسمي، ويجب أن يُقاس عندنا قبل النشر** خصوصاً وأن السواب مستهلك بالكامل. |

---

## 1. الإصدارات — أين نقف

| | الإصدار | التاريخ |
|---|---|---|
| **المثبّت عندنا** | **2.2.4** (حزمة `2.2.4-1+dev`) | نُشر 2024-02-08 |
| أحدث مستقر اليوم | **2.4.5** | نُشر 2026-06-15 |
| بداية سلسلة 2.4 | 2.4.0 | نُشر 2025-11-18 |
| آخر إصدار في سلسلة 2.3 | 2.3.3 | نُشر 2025-05-26 |

التواريخ من واجهة إصدارات GitHub الرسمية (`repos/savonet/liquidsoap/releases`).
ملاحظة: `CHANGES.md` يكتب لـ 2.4.0 تاريخ `2025-09-01` بينما تاريخ النشر الفعلي
على GitHub هو `2025-11-18` — اعتمدتُ تاريخ النشر.

**نحن متأخّرون بإصدارين رئيسيين.** بيننا وبين المستقر كسران في الصيغة:
2.2 → 2.3 ثم 2.3 → 2.4 (أُعيد تصميم الـ callbacks بالكامل في 2.4.0). أي مثال
تجده على الإنترنت اليوم مكتوب غالباً لـ 2.4، وسيفشل عندنا بصمت أو بخطأ نوع.

### جدول التوافق — ما نستطيع استعماله اليوم

| الميزة | أدنى إصدار | على 2.2.4 |
|---|---|---|
| `switch` بمُسنِدات وقت `{9h-12h}` | ≤ 1.4 | ✅ |
| `time.predicate("00m-30m")` (تحليل وقت التشغيل) | 2.0.1 | ✅ |
| `thread.when(p, f)` | 2.0.0 | ✅ |
| `predicate.activates` / `changes` / `once` | 2.0.0 | ✅ |
| `playlist(reload_mode=...)` بما فيه `"watch"` | 2.0.0 | ✅ |
| أوامر السوكيت `<id>.reload` و`<id>.uri` من `playlist` | مؤكّد على 2.2.4 | ✅ |
| ميثود `s.reload(~empty_queue, ~uri)` | مؤكّد على 2.2.4 | ✅ |
| `source.remaining` / `elapsed` / `duration` | 2.0.0 | ✅ |
| ميثود `s.last_metadata()` | 2.0.2 | ✅ |
| ميثودات `s.on_metadata(f)` و`s.on_track(f)` | مؤكّد على 2.2.4 | ✅ (بلا `synchronous`) |
| عوامل `source.on_end` و`on_offset` | مؤكّد على 2.2.4 | ✅ |
| `add(...)` بدلالاته الموصوفة أدناه | مؤكّد على 2.2.4 | ✅ |
| `server.register(namespace=..., ...)` | مؤكّد على 2.2.4 | ✅ |
| `http.post(data=, headers=, timeout=, url)` | مؤكّد على 2.2.4 | ✅ |
| `file.write(~data, ~perms, ~append, path)` | مؤكّد على 2.2.4 | ✅ |
| `json.stringify(compact=true, v)` | مؤكّد على 2.2.4 | ✅ |
| `harbor.http.register` (نمط express) | مؤكّد على 2.2.4 | ✅ |
| `s.on_position(position=, remaining=, allow_partial=, f)` | **2.4.0** | ⬆️ ترقية |
| `synchronous=false` في أي callback | **2.4.0** | ⬆️ ترقية |
| ميثود `s.register_command(...)` | **2.4.0** (غير موجود في 2.3.3) | ⬆️ ترقية — البديل `server.register` |
| ميثود `s.insert_metadata([...])` | **2.4.0** | ⬆️ ترقية — البديل عامل `server.insert_metadata` |
| `cron.add` / `cron.remove` | **2.4.0** | ⬆️ ترقية |
| `source.dynamic` بلا وسم تجريبي | **2.3.0** | ⚠️ تجريبي على 2.2.4 |
| `source.dynamic` بميثودَي `.current_source()` و`.prepare()` | **2.4.0** | ⬆️ ترقية |

المصادر: `CHANGES.md` عند وسم `v2.4.5`، ومقارنة مباشرة بين شجرة `v2.2.4`
وشجرة `v2.4.5`. حيث كتبتُ «مؤكّد على 2.2.4» فذلك يعني أنني قرأت التعريف في
كود 2.2.4 نفسه لكنني لم أفحص إصدارات أقدم لتحديد أوّل ظهور.

---

## 2. الحاجة الأولى — تبديل مصدر زمنياً

### 2.1 الجواب المباشر

الطرق الثلاث المذكورة في التذكرة كلها تعمل، لكنها ليست متكافئة:

| الطريقة | بلا إعادة تشغيل؟ | بلا انقطاع مسموع؟ | الجدولة تُدار من اللوحة؟ | مصادر حيّة بالذاكرة |
|---|---|---|---|---|
| `switch` بمُسنِدات وقت | ✅ يعمل بلا توقّف | ✅ | ❌ الشبكة الزمنية مكتوبة في السكربت | N (واحد لكل بلاي ليست) |
| **`<id>.uri` / `<id>.reload` عبر السوكيت** | ✅ | ✅ | ✅ | **1** |
| `reload_mode="watch"` | ✅ | ✅ | ✅ | 1 |
| `source.dynamic` | ✅ | ✅ | ✅ | N + تعقيد |

**التوصية: الطريقة الثانية.** هي الوحيدة التي تجمع الثلاثة معاً، وهي الأرخص
ذاكرةً — وهذا حاسم عندنا كما في القسم 2.6.

### 2.2 الطريقة الموصى بها — بلاي ليست واحدة تُبدَّل عبر السوكيت ✅ 2.2.4

```liquidsoap
# احتفظ بمقبض البلاي ليست منفصلاً عن النسخة المؤمّنة، لأن mksafe
# يرجّع fallback جديداً لا يحمل ميثودات playlist.
music_pl = playlist(
  id          = "music",           # ⚠️ إلزامي — انظر المحاذير
  mode        = "randomize",
  reload_mode = "never",
  "/srv/radio/playlists/current.m3u"
)

music = mksafe(music_pl)
```

`playlist` يسجّل أربعة أوامر سوكيت تلقائياً تحت مساحة اسم `id`:

| الأمر | ماذا يفعل |
|---|---|
| `music.uri` | يطبع مسار البلاي ليست الحالي |
| `music.uri /srv/radio/playlists/evening.m3u` | يضبط مساراً جديداً **ويحمّله فوراً** |
| `music.reload` | يعيد تحميل الملف نفسه (بعد أن تكتب فيه من PHP) |
| `music.skip` | يتخطّى المقطع الجاري |
| `music.next` | يطبع حتى عشرة عناوين قادمة |

من PHP، عبر `radioCommand()` الموجودة أصلاً في `includes/radio-control.php`:

```php
radioCommand('music.uri /srv/radio/playlists/evening.m3u');   // تبديل البلاي ليست
radioCommand('music.reload');                                  // إعادة تحميل بعد تعديل الملف
radioCommand('music.next');                                    // معاينة القادم
```

### 2.3 سلوك الانتقال — قطع فوري أم انتظار نهاية المقطع؟

**ينتظر نهاية المقطع. لا قطع، ولا انقطاع مسموع.** هذا ليس تخميناً؛ مقروء من
الكود:

- دالة `reload` داخل `playlist` تنفّذ `q = s.queue(); s.set_queue([]); list.iter(request.destroy, q)`.
- `s` هنا مصدر من نوع `request.dynamic`. في
  `src/core/sources/request_source.ml` يوجد **حقلان منفصلان**: `current` وهو
  الطلب الجاري بثّه، و`retrieved` وهو طابور الجلب المسبق. وميثود `queue` و
  `set_queue` يمسّان `retrieved` وحده.
- إذن `reload` يرمي ما جُلب مسبقاً ولم يُبثّ بعد، ولا يلمس المقطع على الهواء.
  البلاي ليست الجديدة تبدأ من **المقطع التالي**.

لو أردت التبديل فوراً بدل انتظار النهاية، أتبع الأمر بـ `music.skip`:

```php
radioCommand('music.uri /srv/radio/playlists/news-hour.m3u');
radioCommand('music.skip');   // قطع فوري للمقطع الجاري
```

### 2.4 المحاذير — اقرأها قبل الكتابة

1. **اضبط `id` صراحةً.** بلا `id` يشتقّه `playlist` من اسم الملف أو المجلد عبر
   `playlist.id` (يأخذ `path.basename`)، فيصير اسم أمر السوكيت غير متوقّع —
   ملفّنا الحالي `/srv/radio/music` سيعطي مساحة اسم `music` بالمصادفة، وأي
   تغيير في المسار يكسر أوامر PHP بصمت.
2. **لا تخلط `watch` مع `<id>.uri`.** في كود `playlist` نفسه: إن كان
   `reload_mode == "watch"` وغيّرت الـ uri، يُطبع تحذير
   `"Warning: the watched file is not updated for now when changing the uri!"`
   ويبقى المراقب على الملف القديم (تعليق `# TODO` في المصدر). اختر واحداً:
   إمّا `watch` + ملف ثابت، وإمّا `never` + أوامر صريحة.
3. **`max_fail`** — إن فشل حلّ عشرة طلبات متتالية اعتُبرت البلاي ليست فاشلة.
   مسارات غير قابلة للقراءة من مستخدم `liquidsoap` تصل بك إلى هذا بسرعة.
4. `mksafe` يرجّع `fallback` جديداً — فقدت ميثودات البلاي ليست. احتفظ بالمقبض
   الأصلي كما في المثال أعلاه.

### 2.5 الطرق الأخرى — متى وكيف

#### أ) `switch` بمُسنِدات وقت ✅ 2.2.4

```liquidsoap
morning = playlist(id="pl_morning", mode="normal", reload_mode="never",
                   "/srv/radio/playlists/morning.m3u")
evening = playlist(id="pl_evening", mode="normal", reload_mode="never",
                   "/srv/radio/playlists/evening.m3u")

programme = switch(
  track_sensitive = true,     # الافتراضي: لا يعاد الاختيار إلا عند نهاية المقطع
  [
    ({ 6h-12h },  morning),
    ({ 18h-23h }, evening)
  ]
)

programme = mksafe(programme)
```

- توقيع `switch` على 2.2.4 و2.4.5 متطابق في ما يهمّنا:
  `track_sensitive` (getter، افتراضه `true`)، `transition_length` (افتراضه `5.`)،
  `override` (افتراضه `"liq_transition_length"`)، `replay_metadata`
  (افتراضه `true`)، `transitions`، `single`، وميثود `.selected()`.
- **سلوك الانتقال:** `track_sensitive=true` ⇒ حدّ الساعة 12:00 لا يُطبَّق حتى
  تنتهي الأغنية الجارية. `track_sensitive=false` ⇒ قطع فوري في منتصف المقطع.
- صيغة مُسنِدات الوقت (من التوثيق الرسمي): `{11h15-13h}` بين الساعتين،
  `{12h}` طوال الساعة الثانية عشرة، `{12h00}` عند اللحظة، `{00m}` أول دقيقة من
  كل ساعة، `{00m-09m}` أول عشر دقائق، `{2w}` الثلاثاء، `{6w-7w}` عطلة الأسبوع.
  الأحد هو `0w` و`7w` معاً.
- للتحليل وقت التشغيل: `f = time.predicate("00m-30m")` (منذ 2.0.1).
- **عيبه القاتل عندنا:** الشبكة الزمنية مكتوبة داخل `radio.liq`. أي تعديل من
  لوحة التحكم يعني تعديل الملف ثم `systemctl restart liquidsoap` — أي **إيقاف
  البث**، وهو ممنوع بنصّ التذكرة. صالح للقواعد الثابتة التي لا يغيّرها أحد
  (نشرة أخبار كل ساعة مثلاً)، لا للجدولة المُدارة.

#### ب) `reload_mode="watch"` ✅ 2.2.4

```liquidsoap
music_pl = playlist(id="music", mode="randomize", reload_mode="watch",
                    "/srv/radio/playlists/current.m3u")
```

يعيد التحميل تلقائياً عند تغيّر الملف، بنفس سلوك عدم القطع الموصوف في 2.3.

**محذور inotify — مهم:** المراقب في `src/core/file_watcher.inotify.ml` يضيف
للمسار الأحداث `S_Moved_to, S_Moved_from, S_Delete, S_Create`، ويضيف `S_Modify`
**فقط إن لم يكن المسار مجلداً**. من هنا نتيجتان:

- الكتابة في مكان الملف (فتح + تفريغ + كتابة، وهو ما يفعله `file.write` وPHP
  الاعتيادي) تُطلق `S_Modify` → يعمل.
- الاستبدال بـ `mv tmp current.m3u` يغيّر الـ inode. المراقب مربوط بالـ inode
  القديم، وLiquidsoap لا يعيد ربطه إلا حين يتغيّر الـ uri نفسه. **الأرجح أن
  التحديثات تتوقّف صامتة بعد أول استبدال بهذا الأسلوب.** لم أختبر هذا عملياً —
  **يُفحص على السيرفر قبل الاعتماد عليه.**
- مجلدنا الحالي `/srv/radio/music` مراقَب كمجلد، فلا يُضاف له `S_Modify`. هذا
  يكفي لالتقاط رفع ملف جديد أو حذفه (وهو المطلوب اليوم)، ولا يلتقط تعديل محتوى
  ملف قائم.

**الأسلم عندنا:** `reload_mode="never"` + استدعاء `music.reload` من PHP بعد
الكتابة. السوكيت موجود ومستعمَل أصلاً، فلا داعي للاعتماد على inotify.

#### ج) `source.dynamic` — لا تستعمله على 2.2.4 ⚠️

هذا هو أكثر ما تغيّرت صيغته بين إصداراتنا، وأكثر ما ستجد عنه أمثلة قديمة:

| | 2.2.4 | 2.4.5 |
|---|---|---|
| الوسم | `Experimental` | مستقرّ (رُفع الوسم في 2.3.0) |
| `track_sensitive` | `bool` | `getter(bool)` |
| `resurection_time` | موجود (افتراضه `1.`) | **أُزيل** |
| `merge` | غير موجود | موجود |
| ميثود `.set(s)` | **موجود** | **أُزيل** |
| ميثود `.current_source()` | غير موجود | موجود |
| ميثود `.prepare(s)` | غير موجود | موجود |

أي مثال إنترنت يستعمل `.set(...)` سيفشل على 2.4.x، وأي مثال يستعمل `.prepare()`
سيفشل عندنا. وفوق ذلك، `CHANGES.md` لـ 2.4.5 يسجّل
`Fixed sources leaks in source.dynamic (#4835)` — أي أن تسريبات المصادر في هذا
العامل بقيت حيّة حتى منتصف 2026، ونحن على 2.2.4. على سيرفر بـ 974 م.ب متاحة
و**صفر احتياطي سواب**، هذا خطر لا مقابل له: كل ما يعطيه `source.dynamic` يعطيه
`<id>.uri` بلا مصادر إضافية.

### 2.6 الذاكرة — لماذا الطريقة الثانية بالذات

القيد المُلزِم من جرد السيرفر: **نواة واحدة، 974 م.ب متاحة، والسواب مستهلك
بالكامل (2046 من 2047)**. أي أن النظام بلا شبكة أمان: أي ذروة ذاكرة تذهب
مباشرةً إلى قاتل OOM بدل أن تُبدَّل إلى القرص.

- نموذج `switch` ونموذج `source.dynamic` يُبقيان **N مصدر بلاي ليست حيّاً معاً**.
  كل مصدر يحمل قائمة ملفاته في الذاكرة، و`prefetch` طلبات محلولة مسبقاً
  (الافتراضي 1)، ودورة إعادة تحميل خاصّة به.
- نموذج `<id>.uri` يبقي **مصدراً واحداً مهما بلغ عدد البلاي ليستات**، لأن
  التبديل يجري على مستوى محتوى الملف لا على مستوى رسم المصادر.

**لا أملك رقماً لفرق الذاكرة بين النموذجين ولن أخترعه.** طريقة القياس على
السيرفر:

```bash
ps -o pid,rss,pcpu,comm -C liquidsoap
```

قبل التعديل وبعده. ويوجد أيضاً `runtime.memory()` داخل Liquidsoap (منذ 2.0.3)
لكنه يحتاج بناءً مع `mem_usage`، وهو غير مضمون في حزمة Debian.

**الخلاصة العملية:** حتى لو كان الفرق عشرات الميغابايت فقط، فالنموذج ذو المصدر
الواحد أبسط وأقلّ سطح خطأ، ولا يخسر شيئاً مقابل ذلك. لا سبب لاختيار غيره.

**المراجع:**
[توثيق اللغة والمُسنِدات الزمنية](https://www.liquidsoap.info/doc-2.4.5/language.html) ·
[توثيق الجدولة](https://www.liquidsoap.info/doc-2.4.5/scheduling.html) ·
[`src/libs/playlist.liq` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/libs/playlist.liq) ·
[`src/core/sources/request_source.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/sources/request_source.ml) ·
[`src/core/operators/switch.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/switch.ml) ·
[`src/core/operators/dyn_op.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/dyn_op.ml) ·
[`src/core/file_watcher.inotify.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/file_watcher.inotify.ml)

---

## 3. الحاجة الثانية — بيانات «الآن يُشغَّل» الغنيّة

### 3.1 ما هو متاح على 2.2.4

كلها **ميثودات على أي مصدر**، مقروءة من `src/core/lang_source.ml` عند v2.2.4:

| الميثود | يرجّع | ملاحظة |
|---|---|---|
| `s.last_metadata()` | `[(string*string)]?` | آخر ميتاداتا أنتجها المصدر (منذ 2.0.2) |
| `s.remaining()` | `float` ثوانٍ | **تقديري**. يرجّع `infinity` حين لا تُعرف |
| `s.elapsed()` | `float` ثوانٍ | المنقضي في المقطع الجاري — **دقيق** |
| `s.duration()` | `float` ثوانٍ | مدة تقديرية للمقطع الجاري |
| `s.id()` · `s.time()` · `s.is_ready()` · `s.skip()` · `s.seek()` | | |
| `s.on_metadata(f)` · `s.on_track(f)` | يسجّل callback | **بلا `synchronous`** على 2.2.4 |
| `s.buffered()` · `s.is_up()` · `s.is_active()` · `s.fallible()` | | |

وللبلاي ليست تحديداً، ميثودان إضافيان يعطيان **الموقع في المصدر**:

| الميثود | يرجّع |
|---|---|
| `music_pl.length()` | طول القائمة |
| `music_pl.remaining_files()` | الملفات المتبقّية في الدورة الحالية |

**محذور دقّة:** توثيق `on_position` في 2.4 ينصّ صراحةً على أن «الزمن المنقضي
دقيق بينما المتبقّي تقديري دائماً، والمتبقّي أدقّ عادةً للمصادر الملفّية». أي:
`elapsed()` موثوق، و`remaining()` تقريب — جيّد لملفات محلية، وبلا معنى لمصدر
harbor (يرجّع `infinity`).

### 3.2 أوامر السوكيت الجاهزة — الجواب المباشر على سؤال التذكرة

سألت التذكرة عن `<id>.remaining` وما شابه. الجواب:

**نعم، `<id>.remaining` موجود — لكن على المخرجات لا على المصادر.** كل مخرج
(`output.icecast` عندنا) يسجّل تلقائياً ثلاثة أوامر تحت مساحة اسم معرّفه، من
`src/core/outputs/output.ml`:

| الأمر | ماذا يرجّع |
|---|---|
| `<id>.skip` | يتخطّى المقطع الجاري (نستعمله اليوم) |
| `<id>.metadata` | آخر عشر ميتاداتا، نصّاً |
| `<id>.remaining` | الوقت المتبقّي بصيغة `%.2f`، أو `(undef)` حين لا يُعرف |

**ومعرّف مخرجنا هو `/radio`** — لأن `output.icecast` يمرّر `~name` إلى صنف
المخرج، وقيمته الافتراضية هي نقطة الوصل `mount`، والمخرج ينفّذ
`self#set_id ~definitive:false name`. لهذا يعمل `/radio.skip` في
`radioCommand()` عندنا اليوم. وبالمنطق نفسه:

```php
radioCommand('/radio.remaining');   // "213.44" أو "(undef)"
radioCommand('/radio.metadata');    // آخر الميتاداتا نصّاً
```

**ما ليس موجوداً تلقائياً:** لا يوجد `<id>.remaining` لمصدر عادي، ولا أمر
`<id>.status` لمدخل harbor (انظر القسم 5)، ولا أي أمر يعطي JSON. لتسجيل أوامرك
الخاصّة على 2.2.4 استعمل `server.register` — لأن ميثود `s.register_command`
لم تُضَف قبل 2.4.

الأوامر العامّة المتاحة دائماً (من التوثيق الرسمي للسيرفر):
`uptime` · `version` · `list` · `help [<command>]` · `request.alive` ·
`request.all` · `request.metadata <rid>` · `request.on_air` ·
`request.resolving` · `request.trace <rid>` · `var.get` · `var.set` ·
`var.list` · `exit` · `quit`.

### 3.3 الصيغة الموصى بها — نقطة JSON واحدة على السوكيت ✅ 2.2.4

هذه أرخص طريقة عندنا: لا منفذ HTTP إضافي، لا كاش ملف، والسوكيت مستعمَل أصلاً في
`includes/radio-control.php`.

```liquidsoap
server.register(
  namespace   = "nowplaying",
  description = "حالة البث الآن كـ JSON",
  usage       = "get",
  "get",
  fun (_) ->
    begin
      m = radio.last_metadata() ?? []
      json.stringify(
        compact = true,
        {
          title     = m["title"],
          artist    = m["artist"],
          filename  = m["filename"],
          elapsed   = radio.elapsed(),
          remaining = radio.remaining(),
          duration  = radio.duration(),
          on_air    = live.status(),
          left      = list.length(music_pl.remaining_files())
        }
      )
    end
)
```

ومن PHP:

```php
$json = radioCommand('nowplaying.get');   // سطر JSON واحد ثم END
$data = json_decode((string) $json, true);
```

**تحقّقات الصيغة:** `server.register` بمعاملات `~namespace` و`~description` و
`~usage` مؤكّد على 2.2.4. `json.stringify(compact=..., json5=..., v)` مؤكّد على
2.2.4. `??` و`m["title"]` و`list.length` كلها من لغة 2.x الأساسية.
**مع ذلك لم أنفّذ هذا السكربت** — افحصه بـ `liquidsoap --check` قبل النشر،
خصوصاً استنتاج نوع السجلّ (`record`) داخل `json.stringify`.

**محذور:** `remaining` قد يكون `infinity`، و`json.stringify` لعدد لانهائي قد
يعطي قيمة غير صالحة في JSON. عالجها في Liquidsoap قبل الطباعة:

```liquidsoap
r = radio.remaining()
r = if r == infinity then -1. else r end
```

### 3.4 الدفع إلى نقطة HTTP عند كل تغيير مقطع ✅ 2.2.4 — بشرط

```liquidsoap
def on_new_track(m) =
  payload = json.stringify(compact = true, {
    title  = m["title"],
    artist = m["artist"]
  })

  # ⚠️ لا تنفّذ طلباً شبكياً داخل خيط البث — انقله إلى خيط بطيء
  thread.run(fast = false, delay = 0., fun () ->
    ignore(http.post(
      data    = payload,
      headers = [("Content-Type", "application/json"),
                 ("X-Radio-Token", push_token)],
      timeout = 5.,
      "https://<الدومين>/api/nowplaying-push.php"
    ))
  )
end

radio.on_track(on_new_track)
```

**المحذور الحاسم على 2.2.4:** لا يوجد `synchronous=false` — أُضيف في 2.4.0.
وتوثيق العامل نفسه في 2.2.4 ينصّ على أن الدالة «يجب أن تكون سريعة لأنها تُنفَّذ
في خيط البث الرئيسي». وثائق الترحيل إلى 2.4 تشرح السبب: إن استغرق الـ callback
وقتاً طويلاً تأخّرت دورة البث وظهرت أخطاء catchup. **على نواة واحدة هذا يعني
تقطيعاً مسموعاً على الهواء.** لذلك:

- إمّا `thread.run(fast=false, ...)` كما أعلاه — `fast=false` تحديداً لأن
  التوثيق يعرّفها بـ «المهام الحاجبة مثل جلب بيانات عبر الإنترنت».
- وإمّا استغنِ عن الشبكة كلياً واكتب ملفاً يقرؤه PHP:

```liquidsoap
radio.on_track(fun (m) ->
  file.write(
    data  = json.stringify(compact = true, m),
    perms = 0o644,
    "/srv/radio/nowplaying.json"
  )
)
```

الكتابة المحلية سريعة، لكنها تبقى I/O داخل خيط البث — لفّها بـ `thread.run`
أيضاً إن كان القرص تحت ضغط (وهو مرجّح عندنا والسواب ممتلئ).

**ملاحظة معمارية:** الطريقة الأبسط هي **السحب لا الدفع**. `api/nowplaying.php`
يسأل Icecast كل 15 ثانية ويكاش 5 ثوانٍ. أضف إليه قراءة `nowplaying.get` من
السوكيت داخل الكاش نفسه، ولا تحتاج نقطة دفع ولا رمز مشاركة ولا مسار كتابة جديد
على السيرفر.

### 3.5 أحداث زمنية داخل المقطع ✅ 2.2.4

للتفاعل قبل نهاية المقطع أو عند موضع محدّد فيه:

```liquidsoap
# ينفَّذ حين يتبقّى ≤ 10 ثوانٍ. لاحظ الترتيب: المصدر أولاً ثم الدالة.
radio = source.on_end(delay = 10., radio, fun (rem, m) -> ...)

# ينفَّذ عند تجاوز الثانية 30 من المقطع. لاحظ الترتيب المعكوس: الدالة أولاً!
radio = on_offset(offset = 30., force = false, fun (pos, m) -> ..., radio)
```

**فخّان صيغيّان مؤكّدان من كود 2.2.4:**
- `source.on_end(~delay, s, f)` — المصدر قبل الدالة.
- `on_offset(~offset, ~force, ~override, f, s)` — الدالة قبل المصدر، والاسم
  بلا بادئة `source.`.
- كلتاهما تُنفَّذان في خيط البث الرئيسي (نفس محذور 3.4).

⬆️ على 2.4.0 دُمج العاملان في ميثود واحدة:
`s.on_position(position=, remaining=, allow_partial=, synchronous=, f)`.
**لا تستعملها عندنا — غير موجودة على 2.2.4.**

### 3.6 `harbor.http.register` — موجود، لكن لا تحتاجه

نمط express متاح على 2.2.4 (`harbor.http.register` و`harbor.http.register.simple`
و`http.response`). لكن على نواة واحدة، وبما أن الموقع خلف Apache أصلاً، فتح
منفذ HTTP ثانٍ داخل Liquidsoap يضيف مساحة هجوم وحملاً بلا مقابل: أمر السوكيت
يعطي النتيجة نفسها بكلفة أقل وبعزل أفضل (السوكيت ملفّي بصلاحية 0660 ولا يُرى
من الشبكة إطلاقاً).

**المراجع:**
[توثيق السيرفر والأوامر](https://www.liquidsoap.info/doc-2.4.5/server.html) ·
[توثيق الميتاداتا](https://www.liquidsoap.info/doc-2.4.5/metadata.html) ·
[توثيق harbor كخادم HTTP](https://www.liquidsoap.info/doc-2.4.5/harbor_http.html) ·
[دليل الترحيل — الـ callbacks في 2.4](https://www.liquidsoap.info/doc-2.4.5/migrating.html) ·
[`src/core/lang_source.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/lang_source.ml) ·
[`src/core/outputs/output.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/outputs/output.ml) ·
[`src/core/operators/on_end.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/on_end.ml) ·
[`src/core/operators/on_offset.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/on_offset.ml)

---

## 4. الحاجة الثالثة — مذيعان معاً

### 4.1 الصيغة ✅ 2.2.4

```liquidsoap
live1 = input.harbor(
  id       = "live1",          # ⚠️ إلزامي — انظر 4.6
  "live1",                     # نقطة الوصل التي يكتبها المذيع في BUTT
  port     = 8005,
  password = live_pass,
  buffer   = 6.,               # الافتراضي 12. — خفّضه إن لم تستعمل crossfade
  max      = 12.               # الافتراضي 20.
)

live2 = input.harbor(
  id       = "live2",
  "live2",
  port     = 8006,             # منفذ مختلف — نقاط الوصل تُنسب لكل منفذ
  password = live_pass,
  buffer   = 6.,
  max      = 12.
)

# ⚠️ normalize=false — انظر 4.4
studio = add(normalize = false, weights = [1., 1.], [live1, live2])

radio = fallback(track_sensitive = false, [studio, requests, music])
```

توثيق harbor الرسمي: «حين تستعمل منافذ مختلفة مع مداخل harbor مختلفة، تُنسب
نقاط الوصل لكل منفذ» — أي يجوز أن تتكرّر نقطة الوصل نفسها على منفذين. ولو
فعّلت `icy=true` لعميل shoutcast على المنفذ `n` فعلى العميل أن يتّصل على `n+1`.

### 4.2 حين يتصل واحد فقط — هل يمرّ صمت من المدخل الخالي؟

**لا. المدخل الخالي لا يُجمَع أصلاً.** مقروء من `src/core/operators/add.ml` عند
v2.2.4:

```ocaml
method private _is_ready ?frame () =
  List.exists (fun s -> s#is_ready ?frame ()) sources
```

و في توليد الإطار: `if source#is_ready ~frame:buf () then { source; fields } :: tracks`
— أي أن المصادر غير الجاهزة تُستبعد من قائمة المسارات المجموعة قبل الجمع.

النتائج المباشرة:

- `add` جاهز ما دام **واحد** من مدخليه جاهزاً.
- مذيع واحد متصل ⇒ صوته وحده. **لا صمت مُضاف، ولا خفض من المدخل الخالي.**
- مذيعان ⇒ الصوتان مجموعان.
- لا أحد ⇒ `add` غير جاهز، فيسقط `fallback` إلى `requests` ثم `music` كما اليوم.

### 4.3 هل نحتاج `mksafe` لكل مدخل؟ — **لا، ويجب ألّا تفعل**

في `add.ml` عند v2.2.4:

```ocaml
method stype =
  if List.exists (fun s -> s#stype = `Infallible) sources then `Infallible
  else `Fallible
```

و`mksafe` معرَّف في `src/libs/source.liq` بأنه
`fallback(track_sensitive=false, [s, blank()])` — أي أنه **يجعل المدخل infallible
ويدسّ صمتاً حين انقطاعه**.

لو كتبت `add([mksafe(live1), mksafe(live2)])` لصار `add` **جاهزاً دائماً**،
فيبتلع فرع الأولوية الأول في `fallback` إلى الأبد: تختفي الموسيقى والطلبات، ويبثّ
الراديو صمتاً 24 ساعة. هذا بالضبط سيناريو الفشل الذي يجب تجنّبه.

**القاعدة:** اترك مدخلَي harbor **بلا `mksafe`** (كلاهما `fallible = true`
بطبيعته)، وضع `mksafe` على الموسيقى وحدها كما هو الحال في `radio.liq` اليوم.

### 4.4 `normalize` — أهمّ محذور في هذا القسم

`add` له معاملان (متطابقان بين 2.2.4 و2.4.5):

| المعامل | الافتراضي | المعنى الرسمي |
|---|---|---|
| `normalize` | `true` | «القسمة على مجموع أوزان المصادر **الجاهزة** (أو على عددها إن لم تُحدَّد أوزان)» |
| `power` | `false` | «تطبيع بقدرة ثابتة» |

بما أن القسمة تحسب **الجاهزة فقط**:

- مذيع واحد ⇒ القسمة على 1 ⇒ مستوى كامل.
- يدخل الثاني ⇒ القسمة على 2 ⇒ **هبوط 6 dB مفاجئ في صوت الأول**.
- يخرج الثاني ⇒ قفزة 6 dB عكسية.

قفزتان مسموعتان في كل مرة يدخل مذيع أو يخرج. هذا غير مقبول على الهواء.

**التوصية:** `normalize=false` مع أوزان صريحة. عندها تُستعمل الأوزان «كمعاملات
تضخيم» بنصّ التوثيق، فلا قفزة. الثمن احتمال clipping حين يعلو الاثنان معاً —
عالجه بمحدّد ذروة واحد **بعد** الخلط لا قبله:

```liquidsoap
studio = add(normalize = false, weights = [1., 1.], [live1, live2])
studio = normalize(studio)      # أو compress(...) حسب الذوق
```

`power=true` وسط بين الحالتين (جذر مجموع مربّعات الأوزان) لكنه لا يلغي القفزة،
يخفّفها فقط إلى ~3 dB.

### 4.5 الميتاداتا وعلامات المقاطع — تتكسّر داخل `add`

نصّ التوثيق الرسمي لـ `add` (متطابق في 2.2.4 و2.4.5):

> «Mix sources, with optional normalization. Only relay metadata from the first
> available source. Track marks are dropped from all sources.»

أي:

- ميتاداتا المذيع الثاني **تُهمَل** — الأولى فقط تمرّ.
- **علامات المقاطع تختفي من المزيج بالكامل** ⇒ أي `on_track` مركّب فوق `studio`
  لن يُستدعى أبداً.

**العلاج:** علّق `on_track` و`on_metadata` على `music` أو على مخرج Icecast، لا
على المزيج. ولإظهار اسم المذيعين استعمل ميتاداتا يدوية عند اتصال كلٍّ منهما عبر
`on_connect` (موجود على 2.2.4 كمعامل `~on_connect` لـ `input.harbor`).

### 4.6 محذور صيغي — `id` إلزامي مع مدخلين

**توثيق harbor الرسمي مضلّل هنا.** صفحة `harbor.md` تقول إن «نقطة الوصل
المسجَّلة للمصدر هي أيضاً معرّف المصدر». **هذا غير صحيح على 2.2.4 ولا على
2.4.5:** قائمة معاملات `input.harbor` في الكود لا تحوي `id` إطلاقاً، والمعامل
الوحيد غير المسمّى هو `Mountpoint to look for.`. أما `~id` فيضيفه Liquidsoap
تلقائياً **لكل عامل** (في `lang_source.ml`: `("id", nullable_t string_t, Some null,
Some "Force the value of the source ID.")`)، ومعرّفه الافتراضي مشتقّ من اسم
العامل `input.harbor` لا من نقطة الوصل.

⇒ **مع مدخلين، اضبط `id=` صراحةً لكلٍّ منهما**، وإلا صار الاثنان يتنازعان اسماً
واحداً ولن تعرف أيّهما في السجلّات ولا في أوامر السوكيت.

### 4.7 `blank.strip` لكل مدخل؟ — بحذر

`blank.strip` يجعل المصدر غير متاح أثناء بثّه صمتاً، وهو مفيد لتغطية تلعثم شبكة
المذيع (وهو مقترح فعلاً في تعليقات `radio.liq` الحالية). لكن:

- الصنف `active_operator` (مؤكّد في 2.2.4 و2.4.5). وتوثيق 2.4.5 يصرّح بذلك:
  «This is an active operator, meaning that the source used in this operator
  will be consumed continuously, even when it is not actively used.»
- في المقابل، `input.harbor` وحده — رغم كونه `active_source` — لا يسحب إطاراً
  إلا حين يكون جاهزاً: `method private output = if self#is_ready then ignore self#get_frame`.
  **أي أن مدخلاً غير موصول لا يكلّف شيئاً.** لفّه بـ `blank.strip` قد يغيّر هذه
  المعادلة.
- معاملاته: `threshold=-40.` · `max_blank=20.` · `min_noise=0.` ·
  `track_sensitive=true` · `start_blank=false`.

**التوصية:** إن أضفته، أضفه لمدخل واحد أولاً وقس الفرق في `pcpu` قبل تعميمه.

### 4.8 الكلفة على نواة واحدة — ما أؤكّده وما لا أؤكّده

**لا يوجد قياس رسمي منشور من Savonet لهذه التركيبة، ولن أخترع رقماً.** ما
أستطيع تثبيته هو بنية الكلفة:

| البند | التغيّر بإضافة مذيع ثانٍ |
|---|---|
| ترميز MP3 للمخرج | **صفر** — مخرج Icecast واحد بـ `%mp3(bitrate=128)`، كلفته ثابتة مهما بلغ عدد المداخل |
| عملية الخلط نفسها | مهملة — ضرب وجمع لكل عيّنة في `add.ml`، لا شيء أمام الترميز |
| **فكّ ترميز MP3** | **+1 عملية فكّ ترميز** طوال مدة اتصال المذيع الثاني، وصفر حين لا يكون متصلاً |
| الذاكرة | +تخزين مؤقّت واحد، تقديره أدناه |

**تقدير الذاكرة (حساب مشتقّ لا قياس):** `input.harbor` يخزّن `buffer=12.` ثانية
افتراضياً بحدّ أقصى `max=20.`. وتوثيق الذاكرة الرسمي ينصّ على أن الصوت يُخزَّن
بأعداد OCaml عشرية 64-بت (8 بايت للعيّنة). عند 44.1 kHz ستيريو:

```
44100 عيّنة/ث × 2 قناة × 8 بايت ≈ 0.71 م.ب لكل ثانية
20 ثانية × 0.71 ≈ 14 م.ب لكل مدخل عند امتلاء الحدّ الأقصى
مدخلان ≈ 28 م.ب
```

بخفض `buffer=6.` و`max=12.` كما في مثال 4.1 ينزل الرقم إلى ≈ 17 م.ب للاثنين.

**الخلاصة الصادقة:** الذاكرة ليست العائق الأول — 28 م.ب ليست شيئاً أمام 974
م.ب متاحة. **العائق الفعلي هو أن السواب مستهلك بالكامل (2046 من 2047).** أي أن
النظام يعمل بلا احتياطي: أي ذروة ذاكرة جديدة — من GC في Liquidsoap أو من
MySQL/PHP بجواره — تذهب مباشرة إلى قاتل OOM، وأول ضحيّة محتملة هي العملية الأكبر
مقيمةً. إضافة المذيع الثاني لن تستهلك 974 م.ب، لكنها تضيّق هامشاً ضيّقاً أصلاً.

**توصية صريحة:** قبل تفعيل المذيع الثاني على الهواء:

1. **حرّر السواب أولاً.** امتلاؤه بهذا الشكل سببه على الأرجح Docker/MySQL لا
   Liquidsoap. الحلّ ليس في هذا البحث، لكن البثّ بمذيعين فوق نظام بلا احتياطي
   ذاكرة قرارٌ سيّئ مهما كانت أرقام Liquidsoap.
2. **قِس بدل أن تفترض:**
   ```bash
   ps -o pid,rss,pcpu,comm -C liquidsoap    # خطّ الأساس، بلا مذيع
   # اربط مذيعاً واحداً، انتظر دقيقة، أعد الأمر
   # اربط الثاني، انتظر دقيقة، أعد الأمر
   systemd-cgtop -1 --order=cpu | head
   ```
   الفرق بين القياسين هو جواب السؤال. لن يُعرف بغير ذلك.
3. **إن ظهر الحمل عالياً**، بهذا الترتيب:
   - `%mp3(bitrate=96)` بدل 128 — أوفر خطوة وأقلّها أثراً على الجودة المسموعة.
   - `%mp3(bitrate=128, samplerate=44100, stereo=false)` — أحادي، يوفّر أكثر.
   - خفض `buffer`/`max` لكل مدخل harbor.
   - إلغاء أي `blank.strip` مضاف.

**المراجع:**
[توثيق harbor input](https://www.liquidsoap.info/doc-2.4.5/harbor.html) ·
[توثيق التحكّم بالذاكرة](https://www.liquidsoap.info/doc-2.4.5/memory.html) ·
[`src/core/operators/add.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/add.ml) ·
[`src/core/sources/harbor_input.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/sources/harbor_input.ml) ·
[`src/core/operators/noblank.ml` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/noblank.ml) ·
[`src/libs/source.liq` عند v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/libs/source.liq)

---

## 5. اكتشاف جانبي — خطأ مؤكّد في الكود الحالي

`includes/radio-control.php` → دالة `radioLiveOnAir()` ترسل الأمر
`input.harbor.status` وتستنتج أن المذيع على الهواء إن لم يبدأ الردّ بـ `no `.

**هذا الأمر لا وجود له على 2.x.**

- على **1.4.x** كان `status` أمر سوكيت مسجَّلاً فعلاً
  (`"status" ~descr:"Display current status."` في `src/sources/harbor_input.ml`).
- منذ **2.2.x على الأقل** — مؤكّد في v2.2.4 و v2.2.5 و v2.3.3 و v2.4.5 — تحوّل
  إلى **ميثود على المصدر** (`status` ضمن `~meth`) ولم يعد أمر سوكيت. ولا يوجد
  تسجيل تلقائي لأوامر المصادر في `source.ml` (التسجيل الوحيد هو
  `register_command` الذي يُستدعى صراحةً).

**الأثر:** السوكيت يردّ برسالة خطأ لا تبدأ بـ `no `، فتقرأها الدالة على أن مذيعاً
على الهواء ⇒ **إيجابية كاذبة دائمة**. اللوحة ستقول «المذيع على الهواء» أبداً.

**الإصلاح على 2.2.4** — سجّل الأمر بنفسك في `radio.liq`:

```liquidsoap
server.register(
  namespace   = "live",
  description = "هل المذيع متصل الآن؟",
  usage       = "status",
  "status",
  fun (_) -> live.status()
)
```

ثم في PHP بدّل الأمر إلى `live.status`. الردّ عند عدم الاتصال هو
`no source client connected` بالضبط (من `status_cmd` في `harbor_input.ml`)، وهو
ما تتوقّعه الدالة أصلاً — فمنطق `str_starts_with($res, 'no ')` يبقى صحيحاً بلا
تعديل.

⬆️ على 2.4.0+ يمكن استعمال `live.register_command(...)` بدل `server.register`،
لكن ليس عندنا.

**تُفتح لهذا تذكرة منفصلة** — هي إصلاح خطأ لا نتيجة بحث.

---

## 6. ما يحتاج فحصاً على السيرفر

القائمة التي لم أستطع حسمها من الكود وحده، مرتّبة بالأهمية:

1. **سلوك inotify مع `mv`** (قسم 2.5.ب) — هل يتوقّف `reload_mode="watch"` بعد
   أول استبدال بالنقل؟ الفحص: فعّل `watch`، بدّل الملف بـ `mv`، راقب سجلّ
   Liquidsoap بحثاً عن `Reloading playlist with URI`.
2. **كلفة المذيع الثاني على المعالج** (قسم 4.8) — قياس `pcpu` بثلاث حالات.
3. **صحّة سكربت `nowplaying.get`** (قسم 3.3) — `liquidsoap --check` أولاً،
   خصوصاً استنتاج نوع السجلّ داخل `json.stringify`.
4. **`/radio.remaining`** (قسم 3.2) — تأكيد أن معرّف المخرج هو `/radio` فعلاً:
   ```bash
   echo -e "list\nquit" | socat - /srv/radio/liquidsoap.sock
   ```
   هذا الأمر وحده يحسم كل التخمينات حول أسماء الأوامر المتاحة.
5. **`input.harbor.status`** (قسم 5) — تأكيد الخطأ بنفس الأمر أعلاه.

`liquidsoap --check /srv/radio/radio.liq` بعد كل تعديل، وقبل
`systemctl restart liquidsoap`. وهذه القاعدة موثّقة أصلاً في `CLAUDE.md`.
