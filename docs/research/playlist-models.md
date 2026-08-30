# نمذجة «البلاي ليست» و«الجدولة» فوق Liquidsoap — AzuraCast مقابل LibreTime

> بحث للتذكرة [#2](https://github.com/thabet-toma/alnaqab-news/issues/2).
> كل ما يلي مقروء من الشيفرة المصدرية للمشروعين على فرع `main` بتاريخ 2026-08-30،
> لا من التوثيق التسويقي. روابط المصادر في آخر الملف.

---

## 0. خلاصة في ثلاثة أسطر

- **AzuraCast**: العقل في PHP. Liquidsoap يسأل «ما التالي؟» عبر `request.dynamic`،
  وPHP يجيب بمسار مقطع واحد مُعنون بـ `annotate:`. توليد ملفات `.m3u` و`switch`
  الزمني موجود لكنه **مسار احتياطي** لا المسار الافتراضي.
- **LibreTime**: العقل في عفريت Python. يجلب جدولاً زمنياً كاملاً من الـ API،
  وخيطٌ ينام حتى لحظة كل عنصر ثم يدفعه إلى أحد أربعة `request.queue`. Liquidsoap
  لا يعرف الوقت إطلاقاً.
- **كلاهما يضع الساعة خارج Liquidsoap.** لا أحد منهما يعتمد فعلياً على
  `switch` بمُسنِدات زمنية كآلية أساسية. **هذه أهم نتيجة في البحث.**

---

## 1. نموذج AzuraCast

### 1.1 البلاي ليست: `.m3u` مولَّد… لكنه غالباً لا يُكتب أصلاً

`PlaylistFileWriter` يكتب لكل بلاي ليست ملف `<var_name>.m3u`، وكل سطر فيه ليس
مساراً بل **مسار مُعنون** يُبنى من قاعدة البيانات:

```
annotate:media_id="12",playlist_id="3",title="…",liq_cue_in="0.5":/var/azuracast/stations/x/media/song.mp3
```

و`ConfigWriter` يصرّح عنه كمصدر مستقل:

```liquidsoap
playlist_var = playlist(id="playlist_var", mode="randomize", reload_mode="watch", "…/playlist_var.m3u")
```

**لكن** — وهذا هو المفتاح — `ConfigWriter::shouldWritePlaylist()` ترجّع `false`
للبلاي ليست العادية (`PlaylistSources::Songs` + `PlaylistTypes::Standard`) ما لم
يُفعَّل `write_playlists_to_liquidsoap` أو `use_manual_autodj` صراحةً. أي أن
البلاي ليست الاعتيادية **لا تظهر في ملف `.liq` المولَّد بتاتاً** في التركيب
الافتراضي. الاستثناءات التي تُكتب دائماً: القاطعة (`interrupt_other_songs`)،
ومقطع-واحد (`play_single_track`)، والمدموجة (`merge`)، والمتقدّمة (`Advanced`).

والتعليق في المصدر صريح فوق الحلقة كلها: `// Set up playlists using older format
as a fallback.`

### 1.2 المسار الفعلي: `request.dynamic` يسأل PHP

```liquidsoap
def azuracast.autodj_next_song() =
    api_response = azuracast.api_call("nextsong", "")
    ...  request.create(uri)
end

def azuracast.enable_autodj(s) =
    dynamic = request.dynamic(id="next_song", timeout=…, retry_delay=10., azuracast.autodj_next_song)
    dynamic_startup = fallback(id="dynamic_startup", track_sensitive=false, [dynamic, blank(120.)…])
    s = fallback(id="autodj_fallback", track_sensitive=true, [dynamic_startup, s])
    ...
end
```

`radio = azuracast.enable_autodj(radio)` يلفّ كل ما سبق. وبما أن `dynamic` جاهز
دائماً (PHP يرجّع مقطعاً دوماً)، فالـ `fallback` لا ينزل إلى مصادر `playlist`
إلا عند تعطّل الـ API. `nextsong` وحدة من أوامر `LiquidsoapCommands` الداخلية
(`nextsong`, `feedback`, `auth`, `djon`, `djoff`, `cp`, `savecache`) وتُخدَم عبر
`Controller/Api/Internal/LiquidsoapAction`.

### 1.3 من يقرّر أي بلاي ليست الآن؟ — `Radio\AutoDJ\Scheduler` في PHP

`AutoDJ\Queue::buildQueue()` يبني مسبقاً **طابوراً في قاعدة البيانات**
(`station_queue`) بطول `autodj_queue_length`. لكل صف:

- `timestamp_cued` — متى دُفع للطابور
- `timestamp_played` — **وقت التشغيل المتوقّع**، محسوب بسَلسَلة مُدد المقاطع
  ناقص مدّة التلاشي (`addDurationToTime()`)

ويسأل `Scheduler::shouldPlaylistPlayNow($playlist, $expectedPlayTime)` لكل بلاي
ليست **بوقت التشغيل المتوقّع لا بالوقت الحالي** — أي أنه يجدول للمستقبل. الدوال
المعنية: `isPlaylistScheduledToPlayNow`, `shouldSchedulePlayNow`,
`shouldPlayInSchedulePeriod`, `shouldPlaylistLoopNow`,
`isPlaylistBlockedByGroupSchedule`.

فحين يسأل Liquidsoap `nextsong`، لا يُحسب شيء: يُقتطع أقدم صف مُجهَّز من الطابور.

### 1.4 الجانب الزمني داخل Liquidsoap (المسار الاحتياطي)

حين تُكتب البلاي ليست فعلاً، تُبنى مُسنِدات زمنية نصّية عبر
`getScheduledPlaylistPlayTime()` بصياغة Liquidsoap الأصلية:

```liquidsoap
radio = switch(id="schedule_switch", track_sensitive=true, [
  ({ (1w or 2w) and 08h00m-10h00m }, morning_show),
  ({ schedule_17_date_range() and 3w and 22h00m-23h59m59s }, night_show),
  ({true}, radio)                       # ← العودة للافتراضي
])
```

تفاصيل تستحقّ الالتقاط:

- **العودة للافتراضي** هي حرفياً البند الأخير `({true}, radio)`، حيث `radio` هو
  `random(id="standard_playlists", weights=[…], […])` المبني قبلها. لا حدث «انتهاء»
  ولا cron يُلغي شيئاً — المُسنِد يكذب فينزل `switch` تلقائياً للبند التالي.
- **البلاي ليست العابرة لمنتصف الليل** تُقسَم إلى مقطعين
  (`22h00m-23h59m59s` + `00h00m-02h00m`) مع **إزاحة أيام الأسبوع بمقدار يوم**
  للنصف الثاني. مصيدة حقيقية لو بنينا هذا بأنفسنا.
- **حدود التاريخ** (start_date/end_date) لا تُعبَّر بمُسنِد زمني بل بدالة
  Liquidsoap مولَّدة تقارن `time()` بطابع زمني ثابت.
- سقف 168 بنداً لكل `switch`، وما زاد يُلفّ في `switch` آخر (`array_chunk(…, 168)`).

### 1.5 القطع أم الإكمال (البند 5)

هذا مُصرَّح به بوضوح تام وبمكانين متناظرين:

| الحالة | التعبير المولَّد | السلوك |
|---|---|---|
| بلاي ليست مجدولة عادية | `switch(track_sensitive=true, …)` | **يُكمل** المقطع الحالي ثم يُبدَّل |
| بلاي ليست بخيار `interrupt_other_songs` | `switch(track_sensitive=false, …)` | **يُقطع** فوراً |
| دفعة فورية عادية | `fallback(id="requests_fallback", track_sensitive=true, [requests, radio])` | تنتظر انتهاء المقطع |
| دفعة قاطعة | `fallback(id="interrupting_fallback", track_sensitive=false, [interrupting_queue, radio])` | تقطع فوراً |

أي أن **AzuraCast يملك طابوري طلبات لا واحداً**، والفرق بينهما `track_sensitive`
فقط. وهناك `predicate.at_most(1, {…})` لبلاي ليست «مقطع واحد فقط لكل موعد».

### 1.6 «الآن يُشغَّل» (البند 4)

مسارٌ ذو اتجاهين:

**من Liquidsoap إلى الويب** — `azuracast.send_feedback(m)` يُستدعى عند كل تغيّر
بيانات وصفية، يقارن بآخر عنوان، ويرسل `POST feedback` بـ
`{song_id, media_id, playlist_id, sq_id, artist, title}`. لاحظ: **معرّفات لا نصوص
فقط** — لأن المقطع دخل أصلاً بـ `annotate:` يحمل تلك المعرّفات.

**الحساب في الويب** — `Api\NowPlaying\CurrentSong::recalculate()`:

```php
$this->elapsed   = time() + SongHistory::PLAYBACK_DELAY_SECONDS - $this->played_at;
$this->remaining = $this->duration - $this->elapsed;
```

فـ«المدّة المتبقّية» **ليست من Liquidsoap إطلاقاً**: هي طرحٌ بين ساعة السيرفر
و`played_at` المسجّل لحظة الـ feedback، مع `duration` من مكتبة الوسائط، ناقص ثابت
تعويض عن تأخير البثّ (`PLAYBACK_DELAY_SECONDS = 5`). و«التالي» يأتي من صفوف
`station_queue` بـ `timestamp_played` المتوقّع.

---

## 2. نموذج LibreTime

### 2.1 لا بلاي ليست في Liquidsoap إطلاقاً — أربعة طوابير فقط

`ls_script.liq` (مولَّد من قالب Jinja) ينشئ أربعة مصادر متطابقة:

```liquidsoap
def create_source()
    l = request.queue(id="s#{this_source_id}")
    l = cue_cut(l)  l = fade.in(l)  l = fade.out(l)
    l = map_metadata(notify_queue, l)
    ...
end
create_source() create_source() create_source() create_source()
queue = add(!sources)
```

`add` لا `fallback` — أي أنها تُخلط صوتياً، وهذا ما يسمح بتراكب التلاشي بين
عنصرين متتاليين في طابورين مختلفين. **لا كلمة `playlist` ولا `.m3u` ولا
`request.dynamic` في المكدّس كلّه.** «البلاي ليست» و«العرض» (show) مفهومان في
قاعدة بيانات الويب فقط، يُسطّحان إلى سلسلة عناصر ذات `start` و`end`.

### 2.2 الساعة في Python صراحةً

`PypoFetch` يجلب الجدول من الـ API (كل 8 دقائق أو عند دفعة)، `PypoPush` يفصله إلى
«يعمل الآن» و«مستقبلي»، ثم `PypoLiqQueue` — وهذه هي الساعة الحقيقية:

```python
media_schedule = self.queue.get(block=True, timeout=time_until_next_play)
except Empty:                      # حان الوقت
    media_item = schedule_deque.popleft()
    self.liquidsoap.play(media_item)
    time_until_next_play = seconds_between(datetime.utcnow(), schedule_deque[0].start)
```

خيط ينام بالضبط حتى `start` العنصر التالي. الدقّة بحدود جزء من الثانية، مقابل
دقيقة كاملة في نموذج cron عندنا.

ما يُدفع هو سطر `annotate:` كامل، لا مسار:

```
annotate:media_id="8",schedule_table_id="41",liq_start_next="0",liq_fade_in="0.5",
         liq_fade_out="0.5",liq_cue_in="2.0",liq_cue_out="211.4",title="…":/path/file.mp3
```

عبر `s{queue_id}.push <annotate…>` على تِلنت.

### 2.3 العودة للافتراضي (البند 3) — `switch` بمُسنِد منطقي لا زمني

```liquidsoap
s = switch(id="switch:blank+schedule", track_sensitive=false,
           transitions=[transition_default, transition],
           [({!schedule_streaming}, queue), ({true}, default)])
```

`schedule_streaming` مرجع منطقي (`ref`) يُقلَب من الويب/العفريت عبر أمرَي
`sources.start_schedule` / `sources.stop_schedule`. و`default` ليس بلاي ليست
احتياطية بل **ضجيج شبه صامت** يحمل بياناته الوصفية `message_offline`:

```liquidsoap
default = amplify(id="silence_src", 0.00001, noise())
```

أي أن **LibreTime لا يملك مفهوم «بلاي ليست افتراضية» أصلاً**. خارج الجدول =
صمت. هذا قرار محطّة إذاعية مرخّصة، ولا يصلح لنا.

### 2.4 القطع أم الإكمال (البند 5)

يُقطع، لكن ليس بمُسنِد — بل بالحساب المسبق:

- كل `FileEvent` يحمل `cue_in` و`cue_out` محسوبَين في الويب، فالمقطع **يُبتر عند
  حدّه المجدول** بواسطة `cue_cut` داخل Liquidsoap. لو صادف انتهاء العرض منتصف
  الأغنية، يخرج `cue_out` مبكّراً.
- `liq_start_next="0"` يعطّل التراكب التلقائي، فالتوقيت يبقى بيد Python.
- `Liquidsoap.verify_correct_present_media()` يوازن ما في الطوابير مع ما يقوله
  الجدول: ما لم يعد مجدولاً يُزال بـ `queues.s{n}_skip`، وما نقص يُدفع. أي **تسوية
  فرق (reconciliation)** لا دفعة عمياء.

### 2.5 «الآن يُشغَّل» (البند 4)

```liquidsoap
def notify(m) = gateway("media '#{m['schedule_table_id']}'") end
```

Liquidsoap يُبلّغ عند بداية كل مقطع، لكنه **يرسل معرّف صفّ الجدول فقط** —
لا عنواناً ولا فناناً. لأن الويب يعرف كل شيء مسبقاً: العنوان والفنان و`start`
و`end` كلها في صفّ الجدول نفسه. فالمدّة المتبقّية عند LibreTime هي ببساطة
`end - now` من صفّ الجدول، والتبليغ مجرّد **تأكيد** أن الجدول يُنفَّذ فعلاً.

هذه معماريّة أنظف من AzuraCast في هذه النقطة تحديداً، وثمنها أن الجدول يجب أن
يكون كاملاً ودقيقاً ولا فراغ فيه.

---

## 3. المقارنة المباشرة

| | AzuraCast | LibreTime | نحن اليوم |
|---|---|---|---|
| البلاي ليست في Liquidsoap | `playlist()` على `.m3u` — **احتياطي فقط** | لا وجود لها | `playlist()` على مجلد |
| الآلية الفعلية | `request.dynamic` يسأل HTTP | `request.queue` ×4 يُدفع بها | `request.queue` ×1 |
| مالك الساعة | PHP (`AutoDJ\Scheduler`) | Python (خيط نائم) | cron كل دقيقة |
| دقّة الموعد | مقطع كامل (يسأل عند الحاجة) | جزء من الثانية | ±60 ثانية |
| العودة للافتراضي | `({true}, radio)` أو ببساطة انتهاء الطابور | صمت — لا افتراضي | `fallback` إلى `music` |
| القطع/الإكمال | خيار لكل بلاي ليست عبر `track_sensitive` | يُقطع بـ `cue_out` محسوب | يُقطع دائماً |
| «الآن يُشغَّل» | feedback ببيانات وصفية + معرّفات | معرّف صفّ الجدول فقط | استطلاع Icecast |
| المدّة المتبقّية | `duration - (now - played_at)` في PHP | `end - now` من الجدول | غير موجودة |
| التكلفة التشغيلية | PHP + Redis + عمّال طوابير | 4 عفاريت Python + Django | cron واحد |

---

## 4. التوصية

### **نتبنّى نموذج AzuraCast مُخفَّضاً: PHP يملك الساعة، وLiquidsoap يسأل عبر طابور مدفوع.**

لكن **بلا** `request.dynamic`، وهذا هو التعديل الجوهري على النموذج.

السبب: `request.dynamic` يفرض أن يستدعي Liquidsoap **HTTP** على الموقع عند كل
مقطع. هذا يعني منفذ HTTP داخلي، ورمز مصادقة (`azuracast.http_api_check_token`)،
وتحمّل PHP-FPM لطلب حسّاس زمنياً كل ثلاث دقائق إلى الأبد على سيرفر بنواة واحدة.
عندنا سوكيت Unix يعمل بالفعل ويعمل بالاتجاه المعاكس — فنقلب السهم: **PHP يدفع
مسبقاً بدل أن ينتظر Liquidsoap أن يسأل.** هذا مطابق لنموذج LibreTime في اتجاه
البيانات، ولنموذج AzuraCast في مكان المنطق.

### ما نأخذه، وما نرفضه، ولماذا

#### ✅ نأخذ

| ما نأخذه | من | لماذا |
|---|---|---|
| **البلاي ليست جدولٌ في MySQL لا ملف** | AzuraCast + LibreTime | كلاهما يعتبر البلاي ليست بياناتٍ في قاعدة البيانات. ملف `.m3u` عند AzuraCast مشتقّ لا مصدر. عندنا `radio_tracks` موجود؛ نضيف `radio_playlists` + `radio_playlist_tracks` (بترتيب `weight`) و`radio_playlist_schedule`. |
| **`annotate:` بدل المسار العاري** | كلاهما | يحلّ البند 4 كاملاً بلا استطلاع Icecast: نمرّر `title` و`playlist_id` فيصل الاسم الصحيح من قاعدة بياناتنا لا من وسوم ID3. `radioPlayNow()` تتغيّر من `requests.push $path` إلى `requests.push annotate:title="…",playlist_id="3":$path`. |
| **الطابور المُجهَّز مسبقاً بـ`timestamp_played` متوقّع** | AzuraCast (`AutoDJ\Queue`) | مصدر «التالي» و«المتبقّي» في الواجهة، وينهي اعتمادنا على استطلاع Icecast كلّياً. |
| **`elapsed/remaining` تُحسب في PHP من `played_at` + `duration`** | AzuraCast (`CurrentSong::recalculate`) | `audioDuration()` موجودة عندنا وتخزّن `duration`. ينقصنا فقط تسجيل `played_at`. لا نُطلق برمجية جديدة. |
| **طابورا طلبات لا واحد: `track_sensitive=true` و`false`** | AzuraCast | تعديل من سطرين على `radio.liq` يعطينا «شغّله الآن فوراً» و«شغّله بعد المقطع الحالي» — وهذا فرق حقيقي لخبر عاجل مقابل فاصل موسيقي. |
| **الأولوية بمُسنِد منطقي (`ref`) لا زمني** | LibreTime (`schedule_streaming`) | إن احتجنا لاحقاً «وضع البلاي ليست المجدولة» يُقلَب من اللوحة لا من مُسنِد زمني مولَّد. |
| **تسوية الفرق لا الدفع الأعمى** | LibreTime (`verify_correct_present_media`) | لو عُدِّل الموعد بين لحظة الدفع والتشغيل، الطابور يبقى صحيحاً. عندنا اليوم: `last_run` يُعلَّم ولا رجعة. |
| **`mksafe` أو ما يعادله كخطّ أخير** | AzuraCast (`add_fallback`) | موجود عندنا. نبقيه. |

#### ❌ نرفض

| ما نرفضه | لماذا |
|---|---|
| **`request.dynamic` والـ API الداخلي** | يقلب اتجاه الاعتماد: Liquidsoap يصبح عميل HTTP لموقعنا. منفذ إضافي + رمز مصادقة + طلب PHP حسّاس زمنياً كل ~3 دقائق على 1 vCPU. سوكيتنا يفعل الشيء نفسه بلا شيء من هذا. |
| **توليد `radio.liq` من PHP** | جوهر معماريتنا أن الملف يُنسخ يدوياً إلى `/srv/radio/` ويُفحص بـ `liquidsoap --check`. توليد ديناميكي يعني كتابة PHP على القرص خارج جذر الويب، ثم إعادة تشغيل خدمة systemd من طلب ويب. لا. وAzuraCast نفسه لا يحتاج التوليد إلا لبلاي ليست الحالات الخاصة. |
| **`switch` بمُسنِدات زمنية مولَّدة كآلية أساسية** | AzuraCast يبنيها ثم لا يستعملها افتراضياً، وLibreTime لا يبنيها أصلاً. وتجرّ خلفها كل تعقيد `getScheduledPlaylistPlayTime`: تقسيم منتصف الليل، إزاحة أيام الأسبوع، حدود التواريخ بدوال مولَّدة، سقف 168 بنداً. كلّه ليُعاد نشر الملف عند كل تعديل موعد من اللوحة. |
| **مفهوم LibreTime عن «خارج الجدول = صمت»** | `default = amplify(0.00001, noise())`. نحن محطّة موسيقى مستمرة لا محطّة برامج. `music` يبقى القاع دائماً — وهذا بالضبط ما يفعله `({true}, radio)` عند AzuraCast. |
| **الطوابير الأربعة و`add()`** | ثمنها تعقيد `find_available_queue` و`liq_queue_tracker` وتسوية بأربعة مسارات، ومكسبها تراكب تلاشٍ دقيق بين عنصرين متتاليين. غير مبرَّر عندنا. طابوران يكفيان. |
| **عفريت مقيم يملك الساعة (نموذج Python)** | يعني عملية PHP طويلة العمر تحت systemd. cron كل دقيقة يحتفظ بميزة أن كل شيء بلا حالة ويُشخَّص بـ `php file.php` واحد. الثمن: ±60 ثانية على الموعد — مقبول لمقطع مجدول، لا لعرض إذاعي مباشر (ونحن لا نبثّ عروضاً مجدولة). |
| **`PLAYBACK_DELAY_SECONDS` كثابت مُقحَم** | AzuraCast يزيح المؤقّت 5 ثوانٍ ليعوّض تأخير البثّ. حيلة لا نموذج. نتركها حتى تظهر الحاجة قياساً. |

### أثر ذلك على `radio.liq`

التغيير أصغر بكثير مما توحي به الورقة — سطران ونصف:

```liquidsoap
music    = playlist(mode="randomize", reload_mode="watch", "/srv/radio/music")
music    = mksafe(music)

requests     = request.queue(id = "requests")       # يُكمل المقطع الحالي
interrupting = request.queue(id = "interrupting")   # يقطع فوراً
live         = input.harbor("live", port = 8005, password = live_pass)

radio = fallback(id="requests_fallback",     track_sensitive = true,  [requests, music])
radio = fallback(id="interrupting_fallback", track_sensitive = false, [interrupting, radio])
radio = fallback(id="live_fallback",         track_sensitive = false, [live, radio])
```

لاحظ أن ترتيب `fallback` انقلب عمداً: عندنا اليوم `fallback(track_sensitive=false,
[live, requests, music])` — أي أن **كل** دفعة تقطع المقطع الحالي فوراً. النموذج
الجديد يفصل القرار: `requests` تنتظر، `interrupting` تقطع، `live` يقطع دائماً.

كل ما عدا ذلك يحدث في PHP: بناء الطابور، اختيار البلاي ليست المستحقّة، توليد
`annotate:`, وحساب `remaining`. ولا تغيير على `zenoMount()` — تبقى ميتة كما هي.

### نقاط لم أجد لها جواباً قاطعاً

- **ما إذا كان `request.queue` يقبل `annotate:` بنفس صيغة `s0.push` عند
  LibreTime على إصدار Liquidsoap المثبّت عندنا.** كلا المشروعين يفعل ذلك لكن
  بإصدارات محدّدة (LibreTime يشحن ثلاثة مجلدات: 1.4 و2.0 و2.1). يجب التحقق
  عملياً بـ `liquidsoap --version` ثم تجربة دفعة واحدة قبل البناء عليها.
- **من أين يأخذ AzuraCast `played_at` بالضبط** — `feedback` لا يحمل طابعاً
  زمنياً، فالأرجح أن PHP يستعمل وقت وصول الطلب، لكني لم أقرأ
  `Command/FeedbackCommand` نفسه فلا أجزم.
- **سلوك `switch(track_sensitive=true)` مع مقطع طويل جداً** — نظرياً يمكن أن
  يتأخّر التبديل بمقدار طول المقطع كاملاً. AzuraCast لا يعالج هذا صراحةً في
  الشيفرة التي قرأتها.

---

## 5. المصادر (مقروءة فعلياً)

**AzuraCast** — `github.com/AzuraCast/AzuraCast` @ `main`

- `backend/src/Radio/Backend/Liquidsoap/ConfigWriter.php` — توليد `.liq`،
  `writePlaylistConfiguration()`، `getScheduledPlaylistPlayTime()`،
  `shouldWritePlaylist()`
- `backend/src/Radio/Backend/Liquidsoap/PlaylistFileWriter.php` — كتابة `.m3u`
  المُعنون و`<playlist>.reload`
- `util/docker/stations/liquidsoap/azuracast.liq` — `azuracast.enable_autodj()`،
  `azuracast.autodj_next_song()`، `azuracast.send_feedback()`،
  `azuracast.add_fallback()`
- `backend/src/Radio/AutoDJ/Queue.php` — `buildQueue()`، `getInterruptingQueue()`،
  `addDurationToTime()`
- `backend/src/Radio/AutoDJ/Scheduler.php` — `shouldPlaylistPlayNow()` وأخواتها
- `backend/src/Entity/Api/NowPlaying/CurrentSong.php` — `recalculate()`
- `backend/src/Radio/Enums/LiquidsoapCommands.php` —
  `backend/src/Controller/Api/Internal/LiquidsoapAction.php`
- `backend/src/Entity/StationPlaylist.php` — `backendInterruptOtherSongs()` وأخواتها

**LibreTime** — `github.com/LibreTime/libretime` @ `main`

- `playout/libretime_playout/liquidsoap/2.1/ls_script.liq` — `create_source()`،
  `switch:blank+schedule`، أوامر `sources.*`
- `playout/libretime_playout/liquidsoap/2.1/ls_lib.liq` — `notify()`،
  `notify_queue()`، `gateway()`
- `playout/libretime_playout/liquidsoap/templates/entrypoint.liq.j2` — قالب Jinja
- `playout/libretime_playout/player/queue.py` — `PypoLiqQueue` (الساعة)
- `playout/libretime_playout/player/push.py` — `PypoPush::separate_present_future()`
- `playout/libretime_playout/player/liquidsoap.py` —
  `create_liquidsoap_annotation()`، `verify_correct_present_media()`
- `playout/libretime_playout/player/fetch.py` — `PypoFetch` واستطلاع الجدول
- `playout/libretime_playout/player/events.py` — `FileEvent` بـ `cue_in/cue_out`
- `playout/libretime_playout/liquidsoap/client/_client.py` — `s{n}.push`،
  `queues.s{n}_skip`
- `playout/libretime_playout/notify/main.py` — `notify_media_item_start_playing`
