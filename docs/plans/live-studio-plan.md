# خطة «الاستوديو المباشر» — ضيوف برابط، غرفة تحكم، عرض مرئي، ونشر على فيسبوك وتيك توك

> **الحالة:** خطة معتمدة نظرياً — **لم يُنفَّذ منها شيء**. التنفيذ يبدأ بالمرحلة M0 بعد موافقة المالك على هذا الملف.
> **التاريخ:** 2026-09-23 · **السيرفر المستهدف:** `radio.ktra-pro.tech` (Nginx · Liquidsoap 2.2.4 · ffmpeg 6.1.1 · PHP 8.3 · نواة واحدة · رام 3.9 غ.ب والسواب ممتلئ) — المصدر: `PROJECT_MAP.md`.

---

## 1. الهدف بجملة واحدة

المذيع يدير حلقة حيّة كاملة من المتصفح: يدخل على الهواء بلا برامج، يُدخل ضيفاً برابط يفتحه ويتكلم، يكتم أو يطرد أي صوت، يعرض صوراً وفيديو يراها الجمهور، ويبثّ الحلقة نفسها على فيسبوك وتيك توك.

## 2. قرارات المالك (مُجمَّدة)

| # | القرار |
|---|---|
| 1 | المذيع يبث **من المتصفح** من غرفة التحكم، مع اختيار جهاز الصوت (مايك أو Voicemeeter). |
| 2 | **BUTT يبقى احتياطاً** يعمل بكلمات السر الحالية دون أي تغيير. |
| 3 | **الضيف برابط فقط** — لا برنامج، لا تسجيل، لا كلمة سر. |
| 4 | **الضيف الكفيف أو غير التقني:** مكالمة واتساب على كمبيوتر المذيع تُدمج بالبث عبر **Voicemeeter** (ويندوز). لا تعديل على الموقع. |
| 5 | **السماع المتبادل** بين المذيع وضيف الرابط: مكالمة واتساب جانبية + سماعات (الخيار الأول). لا WebRTC. |
| 6 | **غرفة تحكم:** من على الهواء، كتم/إلغاء كتم/طرد لكل صوت، إنشاء وإلغاء روابط الضيوف. |
| 7 | **رابط مشاركة** للبث: نسخ + فيسبوك + واتساب + X، ومعاينة صحيحة عند اللصق. |
| 8 | **عرض صور وفيديو** من غرفة التحكم يراها الجمهور. **صوت الفيديو:** صامت ما دام أحد على الهواء، ومسموع حين لا يتكلم أحد. |
| 9 | **فيسبوك وتيك توك:** صفحة «شاشة الاستوديو» يلتقطها **OBS** على كمبيوتر المذيع ويبثها — وقت البرامج فقط، لا 24/7. |

## 3. خارج النطاق (صراحةً)

- خط سماع WebRTC بين المذيع والضيف (مؤجَّل — يُبنى إن احتيج لاحقاً).
- بث فيديو من السيرفر نفسه إلى RTMP (الطريقة «ج») — السيرفر لا يتحمّل ترميز فيديو.
- أكثر من مدخلين صوتيين متزامنين (مذيع + ضيف رابط). الضيف الهاتفي يدخل من خلال مدخل المذيع عبر Voicemeeter فيصير المجموع ثلاثة أصوات.
- كاميرا/فيديو للمذيع أو الضيف.
- رمز QR (اقتُرح سابقاً ثم أُسقط: مكتبة qrcodejs المتاحة على cdnjs متوقفة منذ 2015، ومشاركة واتساب تغطي الحاجة).
- تسجيل الحلقات وأرشفتها.

---

## 4. الصورة الكاملة

```
                ┌───────────── غرفة التحكم  admin/radio-live.php ─────────────┐
                │ 🎙 ادخل على الهواء  │ 🔇 كتم / 🚫 طرد لكل مدخل │ 🔗 روابط الضيوف │
                │ 🖼 اعرض صورة/فيديو  │ 📤 انشر رابط البث         │ ⏱ تأخير العرض   │
                └───────────────┬──────────────────────────────────────────┘
                                │ ajax (جلسة + CSRF) → includes/radio-live.php → سوكيت Liquidsoap
                                ▼
 المذيع: متصفح (رمز host) ─ wss /live-in/1 ─┐
 المذيع: BUTT احتياط (كلمة سر ثابتة) ─ :8005 ─┤──► live1 ─┐
 ضيف الهاتف: واتساب → Voicemeeter → المذيع ───┘            ├─► خلط ─► fallback ─► + صوت الفيديو ─► Icecast /radio
 الضيف: رابط guest.php (رمز guest) ─ wss /live-in/2 ─► live2 ─┘   (أولوية: مباشر ← طلبات ← أغاني)
                                                                                    │
                        ┌───────────────────────────────────────────────────────────┤ https://…/stream
                        ▼                                                           ▼
             radio.php (الزوار: صوت + لوحة العرض)                 studio.php (شاشة الاستوديو: صوت + صورة)
                                                                                    │ OBS على كمبيوتر المذيع
                                                                                    ▼
                                                                         فيسبوك Live · تيك توك Live
```

**البث واحد.** صفحة الراديو وشاشة الاستوديو واجهتان للمصدر نفسه. OBS ناقل فقط.

---

## 5. رحلات المستخدم (مع حالات الخطأ ومخارجها)

### 5.1 المذيع يدخل على الهواء من المتصفح

| الخطوة | الحالة قبل | الفعل | النتيجة | الخطأ ← المخرج |
|---|---|---|---|---|
| 1 | غرفة التحكم مفتوحة | يختار جهاز الصوت من القائمة | مؤشر مستوى يتحرك | لا إذن مايك ← رسالة «اسمح للمايك من القفل بجانب الرابط» + زر «أعد المحاولة» |
| 2 | مؤشر يعمل | يفعّل «مصدر خارجي (Voicemeeter)» إن لزم | تُطفأ معالجة المتصفح (إلغاء الصدى وضبط المستوى) لأن Voicemeeter يعالج الصوت | — |
| 3 | جاهز | «ادخل على الهواء» | الصفحة تطلب رمز host قصير العمر ← WebSocket ← «🔴 على الهواء» | رفض الرمز ← «انتهت الجلسة، حدّث الصفحة» · انقطاع الشبكة ← إعادة اتصال تلقائية (1، 2، 4… حتى 30 ثانية) مع عدّاد ظاهر |
| 4 | على الهواء | «اخرج من الهواء» | إغلاق نظيف ← البث يرجع للأغاني | — |
| احتياط | المتصفح لا يعمل | يفتح BUTT كالعادة | نفس المدخل live1 بكلمة السر الثابتة | — |

### 5.2 الضيف يدخل برابط

| الخطوة | الحالة قبل | الفعل | النتيجة | الخطأ ← المخرج |
|---|---|---|---|---|
| 1 | المذيع في غرفة التحكم | «رابط ضيف جديد»: الاسم + الصلاحية (ساعتان / 24 ساعة) | رابط `guest.php?t=…` + زر «أرسل واتساب» | — |
| 2 | الضيف استلم الرابط | يفتحه | «أهلاً <الاسم>» + زر واحد كبير «اسمح بالمايك» | رابط منتهٍ أو ملغى ← «الرابط لم يعد صالحاً، اطلب رابطاً جديداً من المذيع» (لا تفاصيل تقنية) |
| 3 | زر المايك | يسمح | مؤشر صوت + نغمة قصيرة + إعلان صوتي لقارئ الشاشة «المايك يعمل» | رفض الإذن ← شرح مصوّر بخطوتين · متصفح غير مدعوم (Safari قديم) ← «افتح الرابط من كروم» + زر نسخ |
| 4 | جاهز | «ادخل على الهواء» | نغمتان صاعدتان + «أنت على الهواء الآن» + إبقاء الشاشة مضاءة (Wake Lock) | انقطاع ← إعادة اتصال تلقائية + نغمة هابطة + إعلان |
| 5 | على الهواء | المذيع يكتمه | الضيف يرى ويسمع «المذيع كتم صوتك مؤقتاً» | — |
| 6 | على الهواء | المذيع يطرده أو ينتهي الرابط | الاتصال يُغلق، والرابط يُلغى فلا يعود للدخول | — |

**إمكانية الوصول (إلزامية):** زر واحد فعّال في كل لحظة، `aria-live="assertive"` لكل تغيّر حالة، نغمات مميّزة (دخول ↑↑ · خروج ↓ · كتم ⸱⸱)، تباين عالٍ، خط كبير، لا شيء يعتمد على اللون وحده. يُختبر بـ TalkBack (أندرويد) وVoiceOver (آيفون).

### 5.3 الكتم والطرد

| الفعل | ما يحدث في المحرّك | ما يحدث للصوت | يمكن التراجع؟ |
|---|---|---|---|
| كتم | `var.set liveN_gain = 0.` | صمت فوري، الاتصال باقٍ | نعم — «إلغاء الكتم» |
| طرد | إلغاء الرمز في قاعدة البيانات + أمر `liveN.stop` على المحرّك | ينقطع الاتصال، ولا يستطيع العودة بنفس الرابط | لا — رابط جديد |

**تنبيه:** طرد مدخل BUTT يقطع الاتصال فقط، وBUTT يعيد الاتصال تلقائياً بكلمة السر الثابتة. لذلك الكتم هو الأداة الصحيحة مع BUTT، والواجهة تقول ذلك.

### 5.4 العرض المرئي

| الخطوة | الفعل | النتيجة | الخطأ ← المخرج |
|---|---|---|---|
| 1 | يرفع صورة (jpg/png/webp) أو فيديو (mp4) إلى «مكتبة العرض» | يظهر في المكتبة بمعاينة | نوع مرفوض أو حجم زائد ← رسالة بالحد المسموح |
| 2 | «اعرض الآن» | تظهر في صفحة الراديو وشاشة الاستوديو بعد مقدار «تأخير العرض» | المحرّك متوقف ← تُعرض الصورة، وصوت الفيديو لا يُبث، مع تحذير في اللوحة |
| 3 | فيديو + لا أحد على الهواء | صوت الفيديو يُسمع، والأغاني صامتة تحته | — |
| 3′ | فيديو + أحد على الهواء وغير مكتوم | الفيديو يستمر صامتاً | — |
| 4 | «أوقف العرض» أو انتهاء الفيديو | العودة للشعار أو صورة المحطة | — |

**التزامن:** صوت البث يصل المستمع متأخراً بضع ثوانٍ (Icecast + ذاكرة المتصفح). الصفحات تؤخّر إظهار المرئي بنفس المقدار (`radio_visual_delay`، افتراضياً 6 ثوانٍ، قابل للضبط من غرفة التحكم)، فتتطابق الصورة مع الصوت. الفيديو في الصفحات يعمل **دائماً صامتاً**: صوته الحقيقي يأتي من البث نفسه.

### 5.5 المشاركة وفيسبوك وتيك توك

- **لوحة «انشر»** في غرفة التحكم: نسخ رابط `radio.php`، وأزرار فيسبوك (`facebook.com/sharer`) وواتساب (`wa.me/?text=`) وX (`twitter.com/intent/tweet`). لتيك توك: نسخ فقط مع تنبيه «ضعه في البايو»، لأن الروابط داخل المنشورات لا تُضغط.
- **المعاينة:** `og:url` و`og:type` و`og:site_name` و`og:image` و`twitter:card` في `includes/header.php`، وصورة المحطة في `radio.php`.
- **البث على المنصتين:** OBS ← Browser Source = `studio.php` (أفقي 1920×1080 لفيسبوك، و`?layout=vertical` بمقاس 1080×1920 لتيك توك) ← مفتاح البث.
- **قيود المنصتين (حقائق، لا اختيارات):** فيسبوك يقطع بث البرامج الخارجية بعد 8 ساعات، ويعطي مفتاح بث ثابتاً. تيك توك يشترط عادة ألف متابع و18 سنة، ومفتاح RTMP لا يُعطى لكل الحسابات (البديل TikTok LIVE Studio). المنصتان تكتمان أو توقفان البث عند اكتشاف أغانٍ عليها حقوق، **فالبث عليهما وقت الحوار فقط**.

---

## 6. التصميم التقني

### 6.1 وحدات جديدة ومعدّلة

| الملف | جديد/معدّل | المسؤولية |
|---|---|---|
| `includes/radio-live.php` | جديد | وحدة متماسكة واحدة: الرموز (إنشاء، تحقق، إلغاء)، الكتم والطرد عبر `radioCommand()`، حالة المرئي، ومن على الهواء. تعتمد على `radio-control.php` ولا تكرّر منطقه |
| `cron/radio-live-auth.php` | جديد | سكربت CLI يستدعيه Liquidsoap عند كل اتصال: يقرأ الرمز من متغيّر بيئة (لا من سطر الأوامر، كي لا يظهر في `ps`)، ويخرج بـ 0 أو 1 |
| `radio-server/radio.liq` | معدّل | التحقق بالرمز على live1/live2 مع إبقاء كلمات BUTT، والكتم (`amplify`)، وطابور `visual` بقاعدة الصوت |
| `assets/js/radio-broadcast.js` | جديد | **مشترك** بين غرفة التحكم وصفحة الضيف (استعمالان، فالتجريد مبرَّر): الأجهزة، مؤشر المستوى، MediaRecorder، بروتوكول webcast، إعادة الاتصال، Wake Lock |
| `guest.php` + `assets/css/guest.css` + `assets/js/guest.js` | جديد | صفحة الضيف |
| `admin/radio-live.php` + `admin/ajax/radio-live.php` | جديد | غرفة التحكم ونقطة JSON واحدة بحقل `action` |
| `assets/js/radio-live-admin.js` + أنماط في `assets/css/radio-admin.css` | جديد/معدّل | سلوك غرفة التحكم |
| `api/live-state.php` | جديد | حالة عامة للعرض: من على الهواء (أسماء فقط)، المرئي الحالي، وقت السيرفر. كاش ثانيتان كنمط `nowplaying.php` |
| `studio.php` + `assets/css/studio.css` + `assets/js/studio.js` | جديد | شاشة الاستوديو لـ OBS |
| `radio.php` + `assets/js/radio.js` + `assets/css/radio.css` | معدّل | لوحة العرض المرئي للزوار + صورة المعاينة |
| `includes/header.php` | معدّل | وسوم Open Graph الناقصة |
| `install.php` | معدّل | الجدولان الجديدان (+ أمر SQL للسيرفر الحيّ) |
| `admin/*.php` (9 صفحات) | معدّل | رابط «الاستوديو المباشر» في القائمة الجانبية المكرَّرة في كل صفحة |

### 6.2 قاعدة البيانات (القاعدة 6: `install.php` + أمر للسيرفر الحيّ)

```sql
CREATE TABLE IF NOT EXISTS radio_live_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL COMMENT 'sha256 للرمز — الرمز الخام في الرابط فقط',
    role ENUM('host','guest') NOT NULL,
    slot TINYINT NOT NULL COMMENT '1 = live1 (المذيع) · 2 = live2 (الضيف)',
    name VARCHAR(80) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    last_auth_at DATETIME DEFAULT NULL COMMENT 'آخر اتصال ناجح — يحدّد اسم من على الهواء',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_slot_auth (slot, last_auth_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radio_visuals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('image','video') NOT NULL,
    filename VARCHAR(255) NOT NULL COMMENT 'نسبي داخل uploads/visuals',
    title VARCHAR(160) NOT NULL DEFAULT '',
    duration INT DEFAULT NULL COMMENT 'للفيديو، بالثواني عبر ffprobe',
    filesize INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- **حالة العرض الجارية** (لا جدول لها): صفوف في `settings` الموجود: `radio_visual_now` (JSON: `{id, type, url, started_at}` أو فارغ) و`radio_visual_delay` (ثوانٍ).
- **الرموز:** 32 بايت من `random_bytes` بترميز base64url. تُخزَّن كـ sha256 فقط: من يقرأ قاعدة البيانات لا يستطيع الدخول على الهواء. رمز host يُنشأ عند الضغط على «ادخل على الهواء» ويعيش 12 ساعة، ورمز guest حسب ما يختاره المذيع.

### 6.3 تعديلات المحرّك `radio.liq` (المنطق المستهدف — تُثبَّت الصيغ في M0)

```liquidsoap
# التحقق: كلمة BUTT الثابتة أولاً (بلا استدعاء PHP)، وإلا الرمز من قاعدة البيانات
def live_auth(slot, static_pass) =
  fun (login) -> login.password == static_pass or
    process.test(env=[("RADIO_TOKEN", login.password)],
                 "php /var/www/radio.ktra-pro.tech/cron/radio-live-auth.php #{slot}")
end
live1 = input.harbor(id="live1", "live",  port=8005, auth=live_auth(1, live_pass))
live2 = input.harbor(id="live2", "live2", port=8006, auth=live_auth(2, live2_pass))

# الكتم — متغيّرات تفاعلية تكتبها غرفة التحكم عبر السوكيت: var.set live1_gain = 0.
live1_gain = interactive.float("live1_gain", 1.)
live2_gain = interactive.float("live2_gain", 1.)
live1 = amplify(live1_gain, live1)
live2 = amplify(live2_gain, live2)
# (الخلط والـ fallback الحاليان يبقيان كما هما)

# «أحد يتكلم» = مدخل متصل وغير مكتوم
def talking() =
  (source.is_ready(live1) and live1_gain() > 0.) or
  (source.is_ready(live2) and live2_gain() > 0.)
end

# صوت الفيديو: طابور مستقل يُسحب دائماً (add يحرّك كل مصادره) فيبقى متزامناً مع الصورة حتى وهو صامت
visual = request.queue(id="visual")
program = fallback(track_sensitive=false, [live, requests, music])
program = amplify({if source.is_ready(visual) and not talking() then 0. else 1.}, program)
visual  = amplify({if talking() then 0. else 1.}, visual)
radio   = add(normalize=false, [program, visual])
```

**أثر جانبي مقبول وموثَّق:** أثناء فيديو مسموع تستمر الأغنية تحته صامتة، وتُستأنف من حيث وصلت، لا من حيث توقفت. وهذا نفس سلوك قطع التلفزيون.

### 6.4 النقل من المتصفح إلى المحرّك

- **البروتوكول:** webcast عبر WebSocket، وهو نفسه ما يستعمله Web DJ في AzuraCast. أول إطار JSON: `{"type":"hello","data":{"mime":"audio/webm","user":"source","password":"<الرمز>","audio":{"channels":1,"samplerate":48000}}}`، ثم إطارات ثنائية من `MediaRecorder` كل 250 مللي ثانية.
- **الترميز:** `audio/webm;codecs=opus` أولاً، ثم `audio/ogg;codecs=opus` لفايرفوكس. إن لم يتوفر أي منهما: رسالة «افتح الرابط من كروم».
- **Nginx** (السيرفر الحيّ؛ ويُكتب مقابله لـ Apache في README):
  ```nginx
  location = /live-in/1 { proxy_pass http://127.0.0.1:8005/live;  include snippets/radio-ws.conf; }
  location = /live-in/2 { proxy_pass http://127.0.0.1:8006/live2; include snippets/radio-ws.conf; }
  # radio-ws.conf: proxy_http_version 1.1; proxy_set_header Upgrade $http_upgrade;
  #                proxy_set_header Connection "upgrade"; proxy_read_timeout 3600s; proxy_buffering off;
  ```
- **مسار BUTT** على المنفذين 8005 و8006 مباشرة لا يتغيّر.

### 6.5 الأمن

| الخطر | المعالجة |
|---|---|
| تسرّب رابط الضيف | صلاحية محدودة، وإلغاء فوري من اللوحة، والطرد يلغي الرابط |
| قراءة قاعدة البيانات | الرموز مخزّنة sha256 فقط |
| ظهور الرمز في قائمة العمليات | يُمرَّر لـ PHP عبر متغيّر بيئة لا وسيطاً |
| حقن أوامر في السوكيت | كل أوامر الكتم والطرد تُبنى من `slot` بعد تحويله لـ int والتحقق أنه ∈ {1,2}، ومن ثوابت نصية. لا نص من المستخدم يدخل أمراً |
| لوحة التحكم | `requireLogin()` في الصفحة، و`isLoggedIn()` + CSRF في نقطة ajax (القاعدة 3) |
| رفع الملفات | الصور عبر `uploadImage()` (القاعدة 4). الفيديو عبر دالة شقيقة بنفس المنهج: MIME حقيقي من المحتوى، والامتداد مشتقّ منه، و`video/mp4` فقط. `uploads/.htaccess` يمنع PHP أصلاً، ويُضاف مثله في Nginx لـ `uploads/visuals` |
| نقطة عامة جديدة `api/live-state.php` | تُرجع أسماء من على الهواء والمرئي فقط. لا رموز ولا معرّفات داخلية. تفشل بهدوء بإرجاع حالة فارغة، مثل `nowplaying.php` |
| الطباعة | كل قيمة عبر `e()` (القاعدة 1)، وكل استعلام prepared (القاعدة 2) |

### 6.6 السجلّات

- سطر واحد لكل حدث عبر `error_log`، بالصيغة: `[radio-live] cid=<8 hex> event=<auth_ok|auth_fail|mute|unmute|kick|token_create|token_revoke|visual_show|visual_stop> slot=<n> token_id=<id> admin=<id>`.
- لا رموز ولا أسماء ضيوف ولا عناوين IP في السجل. سكربت التحقق يسجّل النتيجة فقط.
- لا سجلّ في مسار الاستطلاع (`api/live-state.php`)، فهو المسار الساخن.

### 6.7 الاعتماديات

**لا مكتبات جديدة.** كل ما في المتصفح واجهات أصلية (`getUserMedia`، `MediaRecorder`، `WebSocket`، `AudioContext`، `Wake Lock`). وعلى السيرفر Liquidsoap 2.2.4 وffmpeg 6.1.1 الموجودان. يلتزم الحل بقاعدة «بلا npm وبلا خطوة بناء».

---

## 7. المراحل

| # | المرحلة | الطبقة | المنفّذ | يعتمد على | الهدف القابل للفحص |
|---|---|---|---|---|---|
| **M0** | فحص جدوى على السيرفر | T3 | Claude يكتب، المالك ينفّذ على السيرفر | — | صوت من متصفح يُسمع على `/stream` عبر WebSocket إلى live2، وصيغ Liquidsoap الخمس مثبتة من `liquidsoap -h` |
| **M1** | دليل Voicemeeter وواتساب | T1 | Claude + المالك يجرّب | — | مكالمة واتساب تُسمع على البث، والضيف يسمع المذيع |
| **M2** | الرموز وقاعدة البيانات | T2 | Claude | — | رمز صالح يعطي 0، ومنتهٍ أو ملغى أو خاطئ يعطي 1 |
| **M3** | المحرّك | T3 | Claude | M0, M2 | `liquidsoap --check` ينجح، والكتم والطرد وقاعدة صوت الفيديو تعمل |
| **M4** | وحدة البث من المتصفح | T3 | Claude | M0 | صوت كروم على الهواء، وإعادة الاتصال بعد قطع الشبكة |
| **M5** | صفحة الضيف | T2 | Claude | M2, M4 | ضيف بكروم أندرويد يدخل بثلاث لمسات، وقارئ الشاشة يعلن كل حالة |
| **M6** | غرفة التحكم | T2 | Claude | M2, M3, M4 | كل زر ينعكس على البث خلال ثانيتين |
| **M7** | العرض المرئي | T2 | Claude | M2, M3 | صورة وفيديو يظهران في `radio.php`، وقاعدة الصوت تعمل |
| **M8** | شاشة الاستوديو ودليل OBS | T2 | Claude + المالك يضبط OBS | M7 | بث تجريبي على فيسبوك يُظهر الشاشة والصوت |
| **M9** | المشاركة والمعاينة | T1 | Claude | — | Facebook Sharing Debugger يعرض صورة وعنواناً صحيحين |
| **M10** | النشر والقبول | T2 | المالك بأوامر جاهزة من Claude | الكل | قائمة القبول (القسم 9) كلها ناجحة |

**الترتيب المقترح:** M0 وM1 وM9 بالتوازي، لأنها مستقلة. **M0 بوابة:** إن فشل، لا يُبنى M3 وM4 وM5 قبل تطبيق الخطة البديلة (القسم 8، الخطر 1).

---

## 8. المخاطر

| # | الخطر | الاحتمال | الفحص | الخطة البديلة |
|---|---|---|---|---|
| 1 | Liquidsoap 2.2.4 لا يقبل WebSocket أو لا يفك `audio/webm` | متوسط | M0 | ترميز MP3 في المتصفح بمكتبة lamejs (من jsdelivr، بنسخة مثبّتة تُفحص حينها) بـ `mime: audio/mpeg`، أو ترقية Liquidsoap إلى 2.4.x |
| 2 | صيغة `auth` أو `process.test(env=…)` أو `interactive.float` مختلفة في 2.2.4 | متوسط | M0: `liquidsoap -h` | تعديل الصيغة. للتحقق بديل: `process.run` مع قراءة `status.code` |
| 3 | Safari على الآيفون لا يسجّل Opus | متوسط | M5 على جهاز حقيقي | اكتشاف آلي، ثم رسالة «افتح من كروم» |
| 4 | قفل شاشة الموبايل يقطع المايك | عالٍ | M5 | Wake Lock + تنبيه ظاهر ومسموع «لا تطفئ الشاشة» |
| 5 | حدّ الرفع 8 م.ب لا يكفي للفيديو | مؤكَّد | — | في M10: رفع `upload_max_filesize` و`post_max_size` في `99-naqab.ini`، و`client_max_body_size` في Nginx إلى 200M |
| 6 | الحمل على نواة واحدة | منخفض | M10: `top` أثناء البث | فكّ Opus لمدخلين وصوت فيديو واحد خفيف. **لا ترميز فيديو على السيرفر إطلاقاً** |
| 7 | عدم التطابق بين الصورة والصوت | متوسط | M7 | «تأخير العرض» قابل للضبط من اللوحة |
| 8 | تعديلات غير محفوظة في `includes/radio-control.php` (قراءة «الآن يُشغَّل») | مؤكَّد | — | تُحفظ في commit مستقل قبل بدء M2 |

---

## 9. قائمة القبول النهائية (M10)

1. بلا أي مذيع: الأغاني تُسمع (يحمي من ابتلاع `add` للأولوية).
2. المذيع من المتصفح: يُسمع. BUTT بكلمة السر القديمة: يُسمع.
3. ضيف برابط من كروم أندرويد: يُسمع مع المذيع معاً، بلا قفزة في المستوى.
4. كتم الضيف: صمت خلال ثانيتين، ولا انقطاع للمذيع. إلغاء الكتم: يعود الصوت.
5. طرد الضيف: ينقطع، وفتح الرابط نفسه يعطي «الرابط لم يعد صالحاً».
6. رابط منتهي الصلاحية: مرفوض.
7. فيديو بلا أحد على الهواء: صوته مسموع. دخول المذيع: يصمت الفيديو ويستمر. خروجه: يعود صوت الفيديو.
8. صورة معروضة: تظهر في `radio.php` و`studio.php`.
9. OBS ← فيسبوك: البث يظهر بالصورة والصوت.
10. رابط `radio.php` في Sharing Debugger: صورة وعنوان صحيحان.
11. `includes/config.php` يعطي 403، والصفحات تعطي 200 (بوابة CLAUDE.md).
12. مكالمة واتساب عبر Voicemeeter: الضيف مسموع على البث ويسمع المذيع.

---

## 10. التذاكر

```
TASK-ID: M0-SPIKE
TIER: T3 deep
EXECUTOR: Claude يكتب صفحة الفحص والأوامر · المالك ينفّذها على السيرفر ويرسل المخرجات
CONTEXT: radio-server/radio.liq (مدخل live2) · PROJECT_MAP.md [RADIO SERVER] · webcast SPECS.md
GOAL: إثبات أن متصفحاً يستطيع البث إلى live2 عبر WebSocket على Liquidsoap 2.2.4، وتثبيت الصيغ الدقيقة لخمس دوال.
SUCCESS CRITERIA:
  (أ) مخرجات `liquidsoap -h input.harbor`, `-h process.test`, `-h amplify`, `-h interactive.float`, `-h source.is_ready`، وقائمة `help` على السوكيت بعد تشغيل المحرّك، محفوظة في هذا الملف تحت «نتائج M0».
  (ب) صفحة HTML مؤقتة على XAMPP المحلي تتصل بـ ws://<IP السيرفر>:8006/live2 بكلمة live2_pass، والمالك يسمع صوته على https://radio.ktra-pro.tech/stream.
CONSTRAINTS: لا تعديل على radio.liq الحيّ. صفحة الفحص خارج المستودع (scratchpad) وتُحذف بعد الفحص.
OUT OF SCOPE: الرموز، الكتم، أي واجهة.
DELIVERABLE: قسم «نتائج M0» في هذا الملف + قرار: نكمل بـ webm أو ننتقل للخطة البديلة.
ROUTING REASON: كل ما بعده مبني على افتراض لم يُختبر على هذه النسخة.
```

```
TASK-ID: M1-VOICEMEETER-GUIDE
TIER: T1 mechanical
EXECUTOR: Claude يكتب · المالك يطبّق على كمبيوتر ويندوز
CONTEXT: قرار المالك #4 · BUTT الحالي
GOAL: دليل خطوة بخطوة يجعل مكالمة واتساب ديسكتوب تُسمع على البث، والضيف يسمع المذيع.
SUCCESS CRITERIA: المالك يجري مكالمة تجريبية ويسمع صوت المتصل على /stream، والمتصل يسمع المذيع، ولا صدى (ضمن القبول #12).
CONSTRAINTS: أدوات مجانية فقط (Voicemeeter Banana + WhatsApp Desktop). الدليل بالعربية مع أسماء الأزرار كما تظهر بالإنجليزية. يعمل مع BUTT ومع البث من المتصفح (اختيار "Voicemeeter Out B1" كجهاز إدخال).
OUT OF SCOPE: أي كود.
DELIVERABLE: docs/guides/voicemeeter-whatsapp.md
ROUTING REASON: لا منطق فيه، ويُفيد فوراً للضيف الكفيف.
```

```
TASK-ID: M2-TOKENS
TIER: T2 standard
EXECUTOR: Claude
CONTEXT: install.php (كتلة CREATE TABLE) · includes/radio-control.php (radioCommand) · includes/auth.php · cron/radio-scheduler.php (نمط CLI)
GOAL: جدولا radio_live_tokens وradio_visuals، ووحدة includes/radio-live.php للرموز، وسكربت cron/radio-live-auth.php.
SUCCESS CRITERIA:
  `php -l` نظيف لكل ملف معدّل.
  محلياً: `RADIO_TOKEN=<صالح> php cron/radio-live-auth.php 2; echo $?` → 0 · بعد الإلغاء → 1 · منتهٍ → 1 · خاطئ → 1 · slot غير مطابق → 1 · تشغيل من المتصفح → 403.
CONSTRAINTS: prepared statements فقط. تخزين sha256 فقط. لا أسرار في git. القاعدة 6: أمر SQL للسيرفر الحيّ مكتوب في radio-server/README.md.
OUT OF SCOPE: المحرّك والواجهات.
DELIVERABLE: install.php، includes/radio-live.php، cron/radio-live-auth.php، قسم SQL في README.
ROUTING REASON: منطق أمني صغير لكنه بوابة كل شيء.
```

```
TASK-ID: M3-ENGINE
TIER: T3 deep
EXECUTOR: Claude
CONTEXT: radio-server/radio.liq كاملاً · نتائج M0 · includes/radio-control.php (RADIO_READ_COMMANDS، radioLiveOnAir)
GOAL: التحقق بالرمز مع إبقاء كلمات BUTT، والكتم لكل مدخل، وطابور visual بقاعدة الصوت (القسم 6.3).
SUCCESS CRITERIA: `liquidsoap --check /srv/radio/radio.liq` ينجح على السيرفر، وبنود القبول 1–7 ناجحة في M10.
CONSTRAINTS: بنية الأولوية الحالية (مباشر ← طلبات ← أغاني) لا تتغيّر. المنفذان 8005/8006 وmountpoint لا يتغيّران (BUTT). normalize=false يبقى. تعليقات بنفس أسلوب الملف.
OUT OF SCOPE: ترميز الإخراج، المنفذ 8010.
DELIVERABLE: radio.liq + تحديث radio-server/README.md (المدخلات، الكتم، WebSocket في Nginx وApache).
ROUTING REASON: خطأ هنا يُسكت الراديو كله، ولا محرّك محلي للاختبار.
```

```
TASK-ID: M4-BROADCAST-JS
TIER: T3 deep
EXECUTOR: Claude
CONTEXT: القسم 6.4 · webcast SPECS · نتائج M0
GOAL: وحدة assets/js/radio-broadcast.js: الأجهزة، مؤشر المستوى، MediaRecorder، webcast hello، إعادة الاتصال بتراجع أُسّي، Wake Lock، ووضع «مصدر خارجي» يطفئ المعالجة.
SUCCESS CRITERIA: `node --check` نظيف. من كروم على الكمبيوتر إلى السيرفر: الصوت على /stream خلال 3 ثوانٍ من الضغط. قطع الشبكة 10 ثوانٍ ثم عودتها: يعود على الهواء تلقائياً. حالة «فشل» دائماً فيها زر «أعد المحاولة».
CONSTRAINTS: JavaScript خام بلا مكتبات. لا نصوص واجهة داخل الوحدة: تُطلق أحداثاً والصفحات تعرض النص.
OUT OF SCOPE: تصميم الصفحات.
DELIVERABLE: assets/js/radio-broadcast.js
ROUTING REASON: الجزء الأكثر هشاشة بين المتصفحات.
```

```
TASK-ID: M5-GUEST-PAGE
TIER: T2 standard
EXECUTOR: Claude
CONTEXT: القسم 5.2 · includes/radio-live.php · radio-broadcast.js
GOAL: guest.php بزر واحد، ونغمات، وإعلانات لقارئ الشاشة، ورسائل خطأ بشرية.
SUCCESS CRITERIA: `php -l` و`node --check` نظيفان. الرابط الصالح يعطي 200 بالاسم، والملغى يعطي صفحة «لم يعد صالحاً» بلا تسرّب. اختبار يدوي: كروم أندرويد مع TalkBack يدخل ويسمع كل إعلان.
CONSTRAINTS: RTL، و`e()`، و`assetVersion()`، ولا style مضمَّن. noindex. الصفحة لا تستعمل header/footer الموقع (صفحة مستقلة خفيفة) — **انحراف مقصود** عن نمط صفحات الزوار: شريط الأخبار والإعلانات تشتّت ضيفاً على الهواء، وقد تشتّت كفيفاً مع قارئ الشاشة.
OUT OF SCOPE: السماع المتبادل.
DELIVERABLE: guest.php، assets/css/guest.css، assets/js/guest.js
ROUTING REASON: واجهة قياسية فوق وحدات جاهزة.
```

```
TASK-ID: M6-CONTROL-ROOM
TIER: T2 standard
EXECUTOR: Claude
CONTEXT: القسم 5.1، 5.3 · admin/radio-settings.php (نمط الصفحة) · admin/ajax/save-radio.php (نمط JSON) · ذاكرة: القائمة الجانبية مكرّرة في ~9 ملفات
GOAL: admin/radio-live.php وadmin/ajax/radio-live.php: الحالة كل ثانيتين، البث الذاتي، الكتم، الطرد، روابط الضيوف، لوحة «انشر».
SUCCESS CRITERIA: `php -l` نظيف. بلا جلسة: 403 JSON. بلا CSRF: 403. slot=3 أو نص: رفض. يدوياً على السيرفر: كتم وطرد وإلغاء رابط تنعكس خلال ثانيتين.
CONSTRAINTS: القاعدة 3. CSS في radio-admin.css فقط. رابط القائمة يُضاف في كل صفحات admin/.
OUT OF SCOPE: العرض المرئي (M7).
DELIVERABLE: الملفان + assets/js/radio-live-admin.js + CSS + تعديل القوائم.
ROUTING REASON: واجهة إدارة قياسية.
```

```
TASK-ID: M7-VISUALS
TIER: T2 standard
EXECUTOR: Claude
CONTEXT: القسم 5.4 · includes/functions.php (uploadImage) · includes/radio-control.php (uploadTrack، audioDuration) · api/nowplaying.php (نمط الكاش) · radio.php
GOAL: مكتبة عرض (رفع، حذف)، «اعرض الآن / أوقف» مع دفع صوت الفيديو إلى visual، و`api/live-state.php`، ولوحة عرض في radio.php بتأخير قابل للضبط.
SUCCESS CRITERIA: `php -l` و`node --check` نظيفان. `curl api/live-state.php` يرجع JSON صالحاً حتى والمحرّك متوقف. رفع ملف .php بامتداد .mp4 مرفوض. صورة معروضة تظهر في radio.php بعد مقدار التأخير.
CONSTRAINTS: القاعدة 4 للصور. MIME حقيقي للفيديو. الفيديو في الصفحات صامت دائماً. لا خطأ HTTP من النقطة العامة.
OUT OF SCOPE: تحرير الفيديو، التحويل بين الصيغ.
DELIVERABLE: توسيع includes/radio-live.php، قسم العرض في غرفة التحكم، api/live-state.php، تعديلات radio.php/radio.js/radio.css، uploads/visuals/.gitkeep.
ROUTING REASON: قياسي، مع اعتماد على M3 لجانب الصوت.
```

```
TASK-ID: M8-STUDIO-SCREEN
TIER: T2 standard
EXECUTOR: Claude + المالك (OBS)
CONTEXT: القسم 5.5 · api/live-state.php · getRadioConfig()
GOAL: studio.php (أفقي/عمودي) بالشعار، و«مباشر»، ومن يتكلم، والمرئي، وشريط الأخبار، مع صوت /stream. ودليل OBS لفيسبوك وتيك توك.
SUCCESS CRITERIA: `php -l` نظيف. يُعرض بلا شريط تمرير عند 1920×1080 و1080×1920. OBS Browser Source يلتقط الصوت ("Control audio via OBS"). بث تجريبي خاص على فيسبوك يُظهر كل ذلك.
CONSTRAINTS: noindex. لا تفاعل مطلوب في الصفحة، فـ OBS لا يضغط أزراراً (تشغيل الصوت تلقائياً مسموح في OBS).
OUT OF SCOPE: ترميز فيديو على السيرفر.
DELIVERABLE: studio.php، assets/css/studio.css، assets/js/studio.js، docs/guides/obs-facebook-tiktok.md
ROUTING REASON: صفحة عرض فوق نقطة جاهزة.
```

```
TASK-ID: M9-SHARE
TIER: T1 mechanical
EXECUTOR: Claude
CONTEXT: includes/header.php (كتلة og) · radio.php (زر shareBtn الموجود)
GOAL: إكمال وسوم Open Graph وTwitter، وصورة المحطة في radio.php.
SUCCESS CRITERIA: `curl -s http://localhost/radio/radio.php | grep -c 'og:'` ≥ 5. على السيرفر: Facebook Sharing Debugger يعرض الصورة والعنوان.
CONSTRAINTS: `e()` لكل قيمة. og:image رابط مطلق.
OUT OF SCOPE: لوحة «انشر» (ضمن M6).
DELIVERABLE: includes/header.php، radio.php
ROUTING REASON: تعديل وسوم فقط.
```

```
TASK-ID: M10-DEPLOY
TIER: T2 standard
EXECUTOR: المالك، بأوامر جاهزة من Claude — لا نشر بمبادرة من Claude
CONTEXT: PROJECT_MAP.md [DEPLOYMENT] · deploy.sh · radio-server/README.md
GOAL: نشر كل ما سبق على radio.ktra-pro.tech.
SUCCESS CRITERIA: القسم 9 كاملاً ✔ + بوابة CLAUDE.md (php -l + curl: الصفحات 200 وconfig 403).
CONSTRAINTS: نسخة احتياطية من /srv/radio/radio.liq قبل الاستبدال. `liquidsoap --check` قبل `systemctl restart`. أمر SQL قبل نشر الكود.
OUT OF SCOPE: ترقية Liquidsoap (إلا إن فرضها M0).
DELIVERABLE: قائمة أوامر مرقّمة + نتائج القبول في هذا الملف.
ROUTING REASON: تغيير على سيرفر حيّ يستمع إليه الناس.
```

---

## 11. المراجع وما أُخذ منها

- **AzuraCast Web DJ:** يبث من المتصفح إلى harbor عبر webcast/WebSocket، بكلمة سر ثابتة لكل مذيع. **أخذنا** طريقة النقل. **رفضنا** كلمات السر الثابتة للضيوف، واستبدلناها بروابط مؤقتة قابلة للإلغاء. **وتجاوزناه** بالكتم لكل صوت وإمكانية الوصول لقارئ الشاشة.
- **StreamYard / Riverside:** الضيف يدخل برابط فقط. **أخذنا** تجربة المستخدم. **رفضنا** خادم WebRTC للاستوديو، لأنه ثقيل على سيرفر بنواة واحدة، والسماع المتبادل حُلّ بمكالمة جانبية.
- **OBS Browser Source:** الطريقة القياسية لتحويل صفحة ويب إلى فيديو مباشر. **أخذناها** كما هي.

**المصادر:** [Liquidsoap harbor 2.2.5](https://www.liquidsoap.info/doc-2.2.5/harbor.html) · [webcast.js SPECS](https://github.com/webcast/webcast.js/blob/main/SPECS.md) · [AzuraCast Liquidsoap](https://www.azuracast.com/docs/developers/liquidsoap/) · [AzuraCast #4751](https://github.com/AzuraCast/AzuraCast/issues/4751) · [Facebook: حدّ مدة البث](https://www.facebook.com/help/www/1534561009906955) · [Videolinq: حدّ فيسبوك](https://www.videolinq.com/post/facebook-live-stream-limit) · [EventLive: الحقوق في البث](https://www.eventlive.pro/blog/copyright-issues-with-facebook-or-youtube-live-solution) · [BIGVU: مفتاح تيك توك](https://bigvu.tv/blog/how-to-get-a-tiktok-stream-key/) · [Streamloop: RTMP تيك توك](https://streamloop.app/how-to/get-tiktok-stream-key)

---

## 12. [ORPHANS & PENDING]

- قبل M2: حفظ تعديلات `includes/radio-control.php` غير المحفوظة في commit مستقل. ✔
- **M10 (النشر والقبول):** معلّق. الكود مكتوب ومفحوص محلياً، والمحرّك مفحوص على السيرفر بـ `--check`. التنفيذ على السيرفر ينتظر الإذن (القسم 13).

## 13. نتائج M0 (2026-09-23، على السيرفر الحيّ، أوامر قراءة فقط)

| الفحص | النتيجة |
|---|---|
| `liquidsoap --version` | 2.2.4 |
| `input.harbor` → `auth` | `({address, password, user}) -> bool` ويُلغي `password` — لذلك كلمة BUTT تُفحص داخل `auth` |
| `process.test` | `(?timeout, ?env, ?inherit_env, string) -> bool`. `env` غير الفارغ يلغي وراثة البيئة ⇒ مسار `/usr/bin/php` كامل |
| `interactive.float` | `(string, float) -> () -> float` |
| `amplify` | `({float}, source)`. تمرير `override = null` خطأ نوع في 2.2.4، فأُزيل |
| `source.is_ready` | `(source) -> bool` |
| أوامر السوكيت الحيّة | `live1.stop` / `live2.stop` / `live1.status` موجودة. طابور `request.queue` يعطي `<id>.push` و`<id>.flush_and_skip` |
| مفكّك ffmpeg | يقبل `audio/webm` و`audio/ogg` |
| WebSocket على 8005 و8006 | `101 Switching Protocols` + `Sec-WebSocket-Protocol: webcast` ⇒ **الخطة البديلة غير لازمة** |
| `radio.liq` الجديد | `liquidsoap --check` ← exit 0 على 2.2.4 |
| مستخدم `liquidsoap` | ليس في مجموعة `www-data`، فيحتاج `usermod -aG www-data liquidsoap` (9.2 في README) |

**انحراف عن القسم 6.3:** صوت الفيديو يُختار بـ `switch` + `fallback` مع `output.dummy` يسحب طابور الفيديو دائماً، **لا** بـ `add` بأوزان. السبب: `add` يُسقط حدود المقاطع، فيتعطّل `/radio.remaining` (شارة الزمن المتبقّي) ويتغيّر معنى `/radio.skip`. ومكسب إضافي: الأغنية تتوقف أثناء الفيديو المسموع وتُكمل من حيث وقفت، بدل أن تستمر صامتة تحته.
