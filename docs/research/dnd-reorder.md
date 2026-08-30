# السحب والإفلات لإعادة الترتيب — JS خام، لمس + ماوس، داخل `dir="rtl"`

> بحث تذكرة [#4](https://github.com/thabet-toma/alnaqab-news/issues/4) — أغسطس 2026
> فرع: `research/dnd-reorder`

---

## التوصية في سطر واحد

**استعمل Pointer Events وحدها** (`pointerdown` / `pointermove` / `pointerup` / `pointercancel`)
مع `setPointerCapture()`، ومقبض سحب `.drag-handle` عليه `touch-action: none`،
وضغطة مطوّلة **250ms مع سماحية 8px** لبدء السحب من أي مكان في الصف،
و**زرّي سهم لأعلى/أسفل** في كل صف كبديل إلزامي لا اختياري،
والحفظ **بزرّ «حفظ الترتيب» صريح** لا عند كل إفلات.

السبب المختصر: HTML5 DnD لا يُطلق أحداثه من لمسة إصبع على أندرويد أصلاً،
وPointer Events مسار واحد يغطّي الماوس والقلم واللمس معاً وهي
[Baseline «متاحة على نطاق واسع» منذ يوليو 2020](https://developer.mozilla.org/en-US/docs/Web/API/Pointer_events).

---

## 1. HTML5 Drag and Drop API أم Pointer Events؟

### HTML5 DnD — مستبعدة

| نقطة | الواقع |
|---|---|
| اللمس | **Chrome for Android وFirefox for Android وSamsung Internet لا تُطلق `DragEvent` من لمسة إصبع إطلاقاً.** iOS/iPadOS 15+ تُطلقها من قلم أو لوحة لمس خارجية، لا من الإصبع |
| الحل الشائع | polyfill مثل [`dragdroptouch`](https://github.com/drag-drop-touch-js/dragdroptouch) أو [`mobile-drag-drop`](https://github.com/timruffles/mobile-drag-drop) — **وهذا يعني تبعية خارجية، وهي ممنوعة في هذا المستودع** |
| التصميم البصري | صورة السحب (`setDragImage`) شبه غير قابلة للتنسيق، ومظهرها يختلف بين المتصفحات |
| الضغطة المطوّلة | مستحيلة: توقيت بدء السحب يملكه المتصفح لا الكود |

الخلاصة: اختيار HTML5 DnD يعني إمّا مكتبة خارجية ممنوعة، وإمّا لوحة تحكّم
لا تعمل على الجوال — وهذه لوحة يُدار منها الراديو من الجوال فعلياً.

المرجع المعياري: [WHATWG — 6.11 Drag and drop](https://html.spec.whatwg.org/dev/dnd.html).

### Pointer Events — التوصية

| نقطة | الواقع |
|---|---|
| التغطية | [Baseline «widely available» منذ يوليو 2020](https://developer.mozilla.org/en-US/docs/Web/API/Pointer_events) — و[`setPointerCapture`](https://developer.mozilla.org/en-US/docs/Web/API/Element/setPointerCapture) كذلك |
| مسار واحد | `event.pointerType` يقول `"mouse"` أو `"touch"` أو `"pen"` — فرقٌ واحد في السطر، لا مساران منفصلان |
| التقاط المؤشّر | `setPointerCapture(e.pointerId)` يوجّه كل الأحداث اللاحقة للعنصر نفسه حتى لو خرج الإصبع منه، ويُحرَّر تلقائياً عند `pointerup` |
| نجاة الإيماءة | إن قرّر المتصفح أن الإيماءة تمرير، يُطلق `pointercancel` — نقطة تراجُع نظيفة لدينا |

**الثمن الصادق:** نكتب كل شيء بأيدينا — الشبح (ghost)، الفراغ (placeholder)،
حساب موضع الإدراج، منع النقرة بعد السحب، بديل لوحة المفاتيح. حوالي 130 سطر JS
و40 سطر CSS. هذا الثمن مقبول هنا لأن البديل هو تبعية ممنوعة.

**تحفّظ:** Pointer Events وحدها **لا تكفي** لمنع تمرير الصفحة أثناء السحب باللمس.
يبقى ضرورياً مستمع `touchmove` واحد غير سلبي — التفصيل في القسم التالي.

---

## 2. الضغطة المطوّلة: كم مدّتها، وكيف نميّزها عن التمرير

### الأرقام المستعملة فعلياً في المكتبات الناضجة

| المكتبة | المدّة | السماحية |
|---|---|---|
| [dnd-kit](https://docs.dndkit.com/api-documentation/sensors/touch) — `TouchSensor` | **250ms** (المثال الرسمي) | 5px |
| [`@hello-pangea/dnd`](https://github.com/hello-pangea/dnd/blob/main/docs/sensors/touch.md) (خلَف react-beautiful-dnd) | **120ms** (والنقاش في [#1464](https://github.com/atlassian/react-beautiful-dnd/issues/1464) يذكر 150ms) | أي `touchmove` قبل انتهاء المؤقّت يُلغي السحب المعلّق |
| [SortableJS](https://github.com/SortableJS/Sortable) | `delay: 0` افتراضياً، و`delayOnTouchOnly: false`، و`touchStartThreshold: 0` — تُضبط يدوياً (الشائع 150–200ms مع `delayOnTouchOnly: true`) | `touchStartThreshold` |
| أندرويد الأصلي (`ViewConfiguration`) | 500ms | — |

**اختيارنا: 250ms و8px.** 120ms قصيرة جداً على قائمة داخل صفحة قابلة للتمرير
(كل محاولة تمرير تتحوّل سحباً)، و500ms تبدو بطيئة. 250ms هو ما استقرّت عليه
dnd-kit وهو التوازن المعقول. 8px بدل 5px لأن اللمس أقلّ دقّة من الماوس ويد
المستخدم ترتجف قليلاً بين الضغط والرفع.

**بالماوس لا تأخير أصلاً** — عتبة مسافة 5px فقط. التأخير على الماوس يجعل
الواجهة تبدو معطّلة. (هذا بالضبط معنى `delayOnTouchOnly` في SortableJS.)

### التمييز بين «ضغطة للسحب» و«تمرير الصفحة»

القاعدة عمليّاً:

1. عند `pointerdown` نسجّل `startX/startY` ونشغّل مؤقّت 250ms — **ولا نمنع شيئاً بعد**.
2. لو تحرّك الإصبع أكثر من 8px قبل انتهاء المؤقّت → نيّته التمرير: نُلغي المؤقّت ونخرج تماماً.
3. لو انتهى المؤقّت والإصبع ثابت → نبدأ السحب، ومن هذه اللحظة نمنع التمرير.

### دور `touch-action` — ولماذا لا تكفي وحدها

[MDN — `touch-action`](https://developer.mozilla.org/en-US/docs/Web/CSS/touch-action) تقول ثلاثة أشياء حاسمة:

- `touch-action: none` تُعطّل معالجة المتصفّح لكل إيماءات التمرير والتكبير على العنصر.
- **«بعد أن تبدأ الإيماءة، تغيير `touch-action` لا يؤثّر في سلوكها».**
  أي: لا يمكن ضبط `touch-action: none` من JS عند بدء السحب — القرار يجب أن
  يكون مكتوباً في CSS **قبل** أن يلمس الإصبع الشاشة.
- إن استولى المتصفّح على الإيماءة، يصل التطبيقَ حدث `pointercancel`.

هذا يخلق تعارضاً حقيقياً لا مخرج نظيف منه:

| الاختيار على الصف | السحب باللمس | تمرير الصفحة بالإصبع فوق القائمة |
|---|---|---|
| `touch-action: none` | مضمون | **معطّل** — قائمة طويلة تصير سجناً |
| `touch-action: pan-y` | يحتاج `preventDefault` | يعمل |

**الحلّ العملي المزدوج — وهو ما توصي به هذه الورقة:**

- **مقبض سحب** (`.drag-handle`، الأيقونة ⠿) عليه `touch-action: none` وحده →
  السحب منه **مضمون بلا أي سباق مع المتصفح**، وبلا حاجة لضغطة مطوّلة أصلاً.
- **بقيّة الصف** عليها `touch-action: pan-y` → التمرير يعمل طبيعياً، والضغطة
  المطوّلة (250ms) تفتح السحب من أي مكان — وهذا هو الشعور الذي طلبته التذكرة
  («أضل ضاغط عالمقطع وأغيّر مكانه»).

### `preventDefault` والمستمعات السلبية

منذ Chrome 56 صار `touchstart` و`touchmove` **سلبيَّين افتراضياً** على
`window` و`document` و`body`، وأي `preventDefault()` فيهما يُتجاهَل مع تحذير
`[Intervention] Unable to preventDefault inside passive event listener`
([Chrome for Developers — scrolling intervention](https://developer.chrome.com/blog/scrolling-intervention)).

لذلك المستمع الوحيد الذي يمنع التمرير يجب أن يُسجَّل صراحةً:

```js
list.addEventListener('touchmove', (e) => { if (active) e.preventDefault(); },
                      { passive: false });
```

ملاحظتان:

- هو مسجَّل على `list` لا على `document`، ويمنع **فقط** بعد تفعيل السحب —
  فلا يكلّف الأداء شيئاً في الحالة العادية.
- نعم، هذا يعني خلط Pointer Events مع مستمع `touch` واحد. هذا ما تفعله
  المكتبات الناضجة نفسها، ولا مفرّ منه ما دمنا نريد التمرير **و** السحب على
  العنصر ذاته.

**ما لم أتحقّق منه:** لم أختبر بنفسي هل يفوز `preventDefault` على قرار
التمرير في **كل** متصفح جوّال في أول `touchmove` بعد الضغطة المطوّلة.
السلوك المتوقّع أن يفوز (لأن الإصبع كان ثابتاً 250ms، فلم تبدأ إيماءة تمرير
بعد)، لكنه سباقٌ لا ضمانة نصّية له في المواصفة. لهذا الكود يعالج
`pointercancel` بتراجع صامت يعيد الترتيب كما كان، **ولهذا المقبض موجود كمخرج
مضمون**. اختبر يدوياً على Chrome/Android وSafari/iOS قبل الاعتماد على مسار
الضغطة المطوّلة وحده.

### مصائد إضافية على اللمس

- **iOS:** الضغط المطوّل يفتح قائمة الاستدعاء والعدسة المكبّرة.
  `-webkit-touch-callout: none` لا تكفي وحدها منذ iOS 15
  ([WebKit bug 231161](https://bugs.webkit.org/show_bug.cgi?id=231161)) —
  أضِف `-webkit-user-select: none` و`user-select: none` معها.
- **أندرويد:** الضغط المطوّل يفتح قائمة السياق → `contextmenu` مع `preventDefault`
  أثناء السحب.
- **بعد السحب** يُطلق المتصفح `click` — يجب ابتلاعها في طور الالتقاط، وإلا فُتح
  الحقل أو أُرسل النموذج الذي تحت الصف.

---

## 3. RTL — ماذا ينكسر بالضبط

### الخبر الجيد أوّلاً

**قائمتنا رأسية، وكل حسابات الترتيب على المحور Y — وهذا محصّن ضد RTL تماماً.**
`clientY` و`getBoundingClientRect().top/bottom` إحداثيات **نافذة فيزيائية**،
لا تتأثّر بـ `direction` إطلاقاً. وكذلك `insertBefore` يعمل على **ترتيب DOM**
لا على الترتيب البصري، فهو محايد.

هذه أهمّ نتيجة عملية في هذا القسم: **لا تجعل القائمة أفقية.** معظم أخطاء RTL
في هذا الباب لا وجود لها أصلاً في قائمة رأسية.

### ما ينكسر فعلاً

**١. `getBoundingClientRect()` فيزيائي لا منطقي.**
في RTL، `rect.left` هو **نهاية** العنصر بصرياً و`rect.right` هو **بدايته**.
أي كود يفترض «اليسار = البداية» ينقلب. هذا سبب خطأ Angular CDK
[`f3bb0c7`](https://github.com/angular/components/commit/f3bb0c7): كان
`getItemIndex()` يقرأ ذاكرة المواضع المرتّبة دائماً حسب top/left، فيُصدر
**فهرساً خاطئاً في القائمة الأفقية داخل RTL**. الإصلاح كان عكس المصفوفة عندما
`orientation === 'horizontal' && dir === 'rtl'`. ومن العائلة نفسها:
[fluentui#7230](https://github.com/microsoft/fluentui/issues/7230) — مؤشّر
الإفلات يعلق دائماً على الفهرس صفر في RTL.

لو احتجنا يوماً قائمة أفقية، الاختبار يُقلب:

```js
// LTR: أدرج قبل العنصر إذا كان المؤشّر يسار منتصفه
// RTL: أدرج قبله إذا كان المؤشّر يمين منتصفه — الإشارة تنعكس
const rtl = getComputedStyle(list).direction === 'rtl';
const mid = r.left + r.width / 2;
if (rtl ? clientX > mid : clientX < mid) { list.insertBefore(slot, other); }
```

**٢. الخطأ الأشهر: خلط قياس فيزيائي بخاصية CSS منطقية.**
لو قِسنا `rect.left` ثم كتبناه في `inset-inline-start`، فالمتصفح في RTL يقيس
من الحافة **اليمنى** → الشبح يقفز بعرض الشاشة كاملاً. هذا حرفياً عَرَض
[angular/components#29604](https://github.com/angular/components/issues/29604).

> **القاعدة:** الشبح `position: fixed` المقاس من `getBoundingClientRect()`
> يُوضَع بـ `left` و`top` **الفيزيائيتين**. الخصائص المنطقية
> (`inset-inline-start`) صحيحة للتخطيط، وخاطئة هنا.
>
> والعكس صحيح لمؤشّر الإفلات وللحشو: `border-left` و`padding-left` تُكتبان
> `border-inline-start` و`padding-inline-start` كي تنعكسا مع الاتجاه.
> وفي قائمة رأسية استعمل `border-block` (أعلى/أسفل) — محايدة تماماً.

**٣. `scrollLeft` في حاوية RTL سالب.**
[MDN](https://developer.mozilla.org/en-US/docs/Web/API/Element/scrollLeft):
في عنصر `direction: rtl` تكون `scrollLeft` صفراً عند أقصى اليمين ثم
**تتناقص إلى قيم سالبة** كلما مرّرنا نحو نهاية المحتوى. تاريخياً اختلفت
المتصفحات على ثلاث مدارس (Blink موجب، Gecko/WebKit سالب، IE معكوس — انظر
[jquery.rtl-scroll-type](https://github.com/othree/jquery.rtl-scroll-type))،
وMDN اليوم توثّق السالب. وهذا يخصّنا مباشرةً لأن جدول `radio-library.php`
يعيش داخل `.table-wrap`.

> **العلاج الأنظف: لا تلمس `scrollLeft` ولا `offsetLeft` أصلاً.**
> `clientX/clientY` مستقلّتان عن التمرير بطبيعتهما، و`position: fixed` كذلك.
> كل الكود أدناه يستعمل هاتين فقط — فيسقط هذا الصنف كلّه من الأخطاء.

**٤. `transform: translateX(+10px)` تحرّك يميناً دائماً** مهما كان `direction`.
أي حركة أفقية انسيابية (FLIP) تحتاج قلب الإشارة في RTL. `translateY` محايدة.

**٥. `elementFromPoint(clientX, clientY)` آمنة في RTL** (إحداثيات نافذة)،
لكنها ستلتقط الشبح نفسه ما لم يحمل `pointer-events: none`.

**٦. نصّ الشبح.** الشبح يُلحق بـ `document.body`، وهو هنا داخل
`<html dir="rtl">` فيرث الاتجاه. ثبِّت `ghost.dir = 'rtl'` صراحةً حتى لا
ينكسر النصّ العربي لو نُقل يوماً إلى حاوية أخرى.

---

## 4. الحفظ: زرّ «حفظ الترتيب» — لا حفظ عند كل إفلات

### القرار ولماذا

| المعيار | حفظ عند كل إفلات | زرّ حفظ صريح ✅ |
|---|---|---|
| عدد الطلبات | ترتيب 10 مقاطع = 10 كتابات كاملة | كتابة واحدة |
| السباقات | طلبان متأخّران على شبكة جوّال قد يصلان مقلوبَين فيُحفظ ترتيب وسيط | لا سباق |
| السحب بالخطأ | يُحفظ فوراً — واللوحة **بلا تراجع** | يبقى محلياً حتى يقرّر المستخدم |
| نمط المشروع | — | `radio-library.php` كلّه نماذج صريحة، و`radio.js` نموذج AJAX بزرّ حفظ |

مع تحسينَين إلزاميَّين: الزرّ **معطّل حتى يتغيّر الترتيب** («لديك ترتيب غير
محفوظ»)، و`beforeunload` يحذّر عند مغادرة الصفحة بترتيب غير محفوظ.

### شكل الطلب — مطابق لنمط `admin/ajax/save-radio.php`

```
POST admin/ajax/save-order.php
Content-Type: application/json

{ "csrf_token": "…", "entity": "categories", "order": [12, 7, 45, 3] }
```

الردّ: `{"success": true}` أو `{"success": false, "error": "…"}` — بلا استثناء،
وبلا 500 يكسر الواجهة.

جانب المتصفّح (نفس نمط `radio.js`):

```js
const res = await fetch('ajax/save-order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ csrf_token: csrfTokenStr, entity: 'categories', order: pending })
});
const data = await res.json();
showToast(data.success ? 'تم حفظ الترتيب' : (data.error || 'حدث خطأ'),
          data.success ? 'success' : 'error');
```

> `csrfTokenStr` هو النمط القائم في `admin/articles.php`
> (`const csrfTokenStr = '<?php echo csrfToken(); ?>';`).
> انتبه: `csrfField()` يُخرج `name="_csrf"` للنماذج، بينما نقاط `admin/ajax/`
> تقرأ `csrf_token` من جسم JSON — وهذا مقصود، اتبع الثاني هنا.

جانب الخادم — النقاط الحاسمة (المقتطف فُحص بـ `php -l`):

```php
// اسم الجدول لا يُمرَّر كـ ? — قائمة بيضاء مكتوبة في الكود، لا شيء منها من الطلب
$TABLES = ['categories' => 'categories', 'ads' => 'ads'];
$table  = $TABLES[$input['entity'] ?? ''] ?? null;
if ($table === null) { echo json_encode(['success'=>false,'error'=>'نوع غير معروف']); exit; }

$order = array_values(array_unique(array_filter(
    array_map('intval', (array) ($input['order'] ?? []))
)));

// نرفض أي ترتيب ناقص أو زائد: وإلا بقي صفٌّ غائب عن الطلب بترتيبه القديم
$existing = array_map('intval', $db->query("SELECT id FROM $table")->fetchAll(PDO::FETCH_COLUMN));
$a = $order; sort($a); $b = $existing; sort($b);
if ($a !== $b) { echo json_encode(['success'=>false,'error'=>'تغيّرت القائمة — أعد تحميل الصفحة']); exit; }

$db->beginTransaction();
$stmt = $db->prepare("UPDATE $table SET sort_order = ? WHERE id = ?");
foreach ($order as $i => $id) { $stmt->execute([$i + 1, $id]); }
$db->commit();
```

الترتيب يبدأ من 1 لا من 0، لأن `sort_order INT DEFAULT 0` — فيبقى الصفر
معناه «لم يُرتَّب بعد» وتظهر العناصر الجديدة أولاً بلا التباس.

### تنبيه على المخطّط — اقرأه قبل البناء

- `categories` و`ads` **فيهما `sort_order INT DEFAULT 0`** أصلاً
  (`install.php`)، و`getCategories()` و`getActiveAds()` ترتّبان به فعلاً
  (`includes/functions.php`). هاتان جاهزتان للسحب اليوم بلا أي تغيير في المخطّط.
- **`radio_tracks` ليس فيه `sort_order`.** إضافته تستوجب — بحسب القاعدة رقم 6
  في `CLAUDE.md` — الكتابة في `install.php` **و** توثيق
  `ALTER TABLE radio_tracks ADD COLUMN sort_order INT DEFAULT 0;` للسيرفر
  الشغّال، لأن `CREATE TABLE IF NOT EXISTS` لن يعدّل جدولاً موجوداً.
- **الأهمّ:** تشغيل المقاطع اليوم **عشوائي** من `radio.liq`، و`radio-library.php`
  يعرضها `ORDER BY uploaded_at DESC`. أي ترتيب يدوي للمقاطع **لن يُسمع في البثّ**
  ما لم يتغيّر `radio.liq` أيضاً. إن كان الهدف الترتيب المسموع فمكانه
  `radio-schedule.php` لا المكتبة. لذلك ابدأ التطبيق من `categories` أو `ads`:
  فائدة فورية، وصفر تغيير في المخطّط.

---

## 5. بديل الوصولية — إلزامي لا تحسين

### المتطلّب المعياري

[WCAG 2.2 — معيار النجاح 2.5.7 Dragging Movements، **المستوى AA**](https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html):

> «كل وظيفة تستعمل حركة سحب يمكن إنجازها بمؤشّر واحد بلا سحب، إلا إن كان
> السحب جوهرياً أو كانت الوظيفة من صنع وكيل المستخدم ولم يعدّلها المؤلّف.»

ووثيقة الفهم تسمّي حلّنا بالحرف:

> «قائمة قابلة للترتيب قد توفّر، بعد النقر على عنصر، أزراراً مجاورة لنقله
> لأعلى أو لأسفل بمجرّد نقرة.»

فالسحب هنا ليس جوهرياً، والبديل ليس رفاهية. (وسم `accessibility` موجود في
المستودع أصلاً — تذكرة التنفيذ الناتجة تستحقّه.)

### التنفيذ

- `<button type="button">` حقيقي لكل من ▲ و▼ — لا `<div>` بمستمع نقر —
  ليأتي التركيز وتفعيل المسافة/Enter مجّاناً.
- `aria-label` كامل ومفهوم منفرداً: `نقل «نشرة الأخبار» لأعلى`.
  السهم نفسه في `aria-hidden="true"`.
- **أعِد التركيز إلى الزرّ بعد النقل** — نقل عقدة DOM تحوي العنصر المركَّز
  يُفقد التركيز في معظم المتصفّحات، فيسقط قارئ الشاشة إلى `<body>`.
- منطقة `aria-live="polite"` تُعلن النتيجة:
  `«نشرة الأخبار» أصبح في المرتبة 3 من 8`. بدونها لا يعرف المستخدم أن شيئاً حدث.
- الزرّ ▲ في العنصر الأول و▼ في الأخير يكونان `disabled`.

### لوحة المفاتيح

الأزرار نفسها **هي** حلّ لوحة المفاتيح — لا حاجة لاختصارات إضافية.
إن أردت زيادة، استعمل `Alt+ArrowUp/Down` على الصف المركَّز، **لا** الأسهم
المجرّدة (تتعارض مع تمرير الصفحة ومع أوضاع تصفّح قارئات الشاشة).

**ملاحظة RTL:** سهما أعلى/أسفل محايدان اتجاهياً — استعملهما دائماً في القائمة
الرأسية. لا تستعمل يمين/يسار: في RTL يعني `ArrowLeft` «نحو النهاية» لا «للخلف»،
وهذا انقلاب إشارة آخر بلا داعٍ.

### ما لا يجوز استعماله

`aria-grabbed` و`aria-dropeffect` **مهجورتان منذ ARIA 1.1** ولم تنفّذهما
أي تقنية مساعدة تنفيذاً كاملاً
([MDN](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-grabbed)،
[W3C ACTION-1672](https://www.w3.org/WAI/ARIA/track/actions/1672)).
لا تضفهما ظنّاً أنهما «الطريقة الصحيحة».

---

## نموذج الكود

### HTML — لاحظ `<ul>` لا `<table>`

```html
<ul class="sortable-list" id="sortableList">
    <li class="sortable-row" data-id="7">
        <span class="drag-handle" aria-hidden="true">&#10281;</span>
        <span class="row-title">نشرة الأخبار</span>
        <button type="button" class="btn-move" data-move="up"
                aria-label="نقل «نشرة الأخبار» لأعلى">&#9650;</button>
        <button type="button" class="btn-move" data-move="down"
                aria-label="نقل «نشرة الأخبار» لأسفل">&#9660;</button>
    </li>
    <!-- … -->
</ul>
<p class="sr-only" aria-live="polite" id="sortStatus"></p>
<button type="button" id="saveOrder" class="btn btn-primary" disabled>حفظ الترتيب</button>
```

> **لماذا `<ul>` وليس الجدول الحالي؟** استنساخ `<tr>` وإلحاقه بـ `<body>`
> يفقده سياق الجدول فينهار عرض خلاياه، والشبح يخرج مشوّهاً. لو أصررت على
> الجدول فعليك تثبيت عرض كل `<td>` في الشبح يدوياً أو لفّه في `<table>` وهمي.
> تحويل القائمة القابلة للترتيب إلى `ul/li` أبسط وأسلم.

### CSS — `assets/css/radio-admin.css`

```css
/* ===== إعادة الترتيب بالسحب ===== */

.sortable-row {
    /* التمرير الرأسي يبقى ممكناً؛ الضغطة المطوّلة هي ما يفتح السحب */
    touch-action: pan-y;
    -webkit-user-select: none;
            user-select: none;
    -webkit-touch-callout: none;   /* قائمة الاستدعاء على iOS */
}

.sortable-row .drag-handle {
    /* على المقبض وحده: السحب مضمون بلا مزاحمة التمرير.
       يجب أن تكون في CSS — تغييرها من JS بعد بدء الإيماءة لا أثر له. */
    touch-action: none;
    cursor: grab;
    padding-inline: 8px;           /* منطقية: تنعكس مع الاتجاه */
}

body.is-sorting { cursor: grabbing; }
.sortable-row.is-dragging { opacity: .35; }

.sortable-ghost {
    position: fixed;               /* left/top فيزيائيتان — مثل getBoundingClientRect */
    z-index: 1000;
    pointer-events: none;          /* وإلا التقط elementFromPoint الشبحَ نفسه */
    box-shadow: 0 8px 24px rgba(0, 0, 0, .45);
    opacity: .92;
}

.sortable-slot {
    /* رأسي: بلا يمين/يسار، فلا شيء ينقلب في RTL */
    border-block: 2px dashed #2a9d8f;
    background: rgba(42, 157, 143, .08);
    list-style: none;
}

.sr-only {
    position: absolute; width: 1px; height: 1px;
    padding: 0; margin: -1px; overflow: hidden;
    clip-path: inset(50%); white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
    .sortable-row { transition: none; }
}
```

### JavaScript — `assets/js/admin.js` (فُحص بـ `node --check`)

```js
/* إعادة ترتيب قائمة بالسحب — ماوس + لمس — داخل dir="rtl" */
(function () {
    'use strict';

    const LONG_PRESS_MS = 250;   // dnd-kit TouchSensor
    const TOUCH_SLOP    = 8;     // px تُلغي الضغطة المطوّلة (نيّة تمرير)
    const MOUSE_SLOP    = 5;     // px قبل اعتبار حركة الماوس سحباً

    const list   = document.getElementById('sortableList');
    const status = document.getElementById('sortStatus');
    if (!list || !window.PointerEvent) return;   // بلا Pointer Events تبقى الأسهم وحدها

    let timer = null, active = false, suppressClick = false;
    let row = null, ghost = null, slot = null;
    let pid = null, startX = 0, startY = 0, grabY = 0, before = [];

    const rows = () => Array.from(list.querySelectorAll('.sortable-row'));
    const ids  = () => rows().map(r => r.dataset.id);

    function emit() {
        list.dispatchEvent(new CustomEvent('order:changed', { detail: { order: ids() } }));
    }

    function begin(clientY) {
        active = true;
        before = ids();
        try { list.setPointerCapture(pid); } catch (err) { /* لا بأس */ }

        const r = row.getBoundingClientRect();
        grabY = clientY - r.top;

        slot = document.createElement('li');
        slot.className = 'sortable-slot';
        slot.style.height = r.height + 'px';

        ghost = row.cloneNode(true);
        ghost.classList.add('sortable-ghost');
        ghost.classList.remove('sortable-row');
        ghost.dir = 'rtl';                       // الشبح خارج تدفّق القائمة: نثبّت الاتجاه
        ghost.style.width  = r.width  + 'px';
        ghost.style.height = r.height + 'px';
        // ⚠ left/top الفيزيائيتان — لا inset-inline-start:
        //   القياس من getBoundingClientRect فيزيائي، وخلطه بخاصية منطقية
        //   يقفز بالشبح بعرض الشاشة في RTL (angular/components#29604)
        ghost.style.left = r.left + 'px';
        ghost.style.top  = r.top  + 'px';
        document.body.appendChild(ghost);

        row.after(slot);
        row.classList.add('is-dragging');
        document.body.classList.add('is-sorting');
    }

    function moveTo(clientY) {
        ghost.style.top = (clientY - grabY) + 'px';
        // ── قائمة رأسية: المقارنة على Y وحده، فـ direction لا يمسّها ──
        for (const other of rows()) {
            if (other === row) continue;
            const r = other.getBoundingClientRect();
            if (clientY < r.top + r.height / 2) { list.insertBefore(slot, other); return; }
        }
        list.appendChild(slot);
    }

    function finish(commit) {
        clearTimeout(timer); timer = null;
        if (!active) { row = null; pid = null; return; }

        slot.replaceWith(row);
        ghost.remove();
        row.classList.remove('is-dragging');
        document.body.classList.remove('is-sorting');

        if (!commit) {
            // pointercancel: المتصفح خطف الإيماءة — نرجع الترتيب كما كان بصمت
            before.forEach(id =>
                list.appendChild(list.querySelector('.sortable-row[data-id="' + id + '"]')));
        } else if (ids().join() !== before.join()) {
            emit();
        }

        suppressClick = true;                    // النقرة التي تلي الإفلات
        setTimeout(() => { suppressClick = false; }, 0);
        active = false; row = null; ghost = null; slot = null; pid = null;
    }

    list.addEventListener('pointerdown', (e) => {
        if (e.button !== 0 || active) return;    // الزر الأيسر / اللمسة الأولى فقط
        if (e.target.closest('input, textarea, select, button, a')) return;
        const r = e.target.closest('.sortable-row');
        if (!r) return;

        row = r; pid = e.pointerId; startX = e.clientX; startY = e.clientY;

        if (e.pointerType === 'mouse') return;                           // ماوس: عتبة مسافة، بلا تأخير
        if (e.target.closest('.drag-handle')) { begin(startY); return; } // مقبض: فوري
        timer = setTimeout(() => { if (row && !active) begin(startY); }, LONG_PRESS_MS);
    });

    list.addEventListener('pointermove', (e) => {
        if (!row || e.pointerId !== pid) return;
        if (active) { moveTo(e.clientY); return; }

        const moved = Math.hypot(e.clientX - startX, e.clientY - startY);
        if (e.pointerType === 'mouse') {
            if (moved > MOUSE_SLOP) { begin(startY); moveTo(e.clientY); }
        } else if (moved > TOUCH_SLOP) {
            clearTimeout(timer); timer = null; row = null; pid = null;   // نيّته التمرير
        }
    });

    list.addEventListener('pointerup',     () => finish(true));
    list.addEventListener('pointercancel', () => finish(false));

    // غير سلبي إجبارياً: منذ Chrome 56 يُتجاهَل preventDefault في المستمع السلبي
    list.addEventListener('touchmove', (e) => { if (active) e.preventDefault(); },
                          { passive: false });
    // الضغطة المطوّلة تفتح قائمة السياق على أندرويد
    list.addEventListener('contextmenu', (e) => { if (active || timer) e.preventDefault(); });
    // نبتلع النقرة التي يُطلقها المتصفح بعد السحب
    list.addEventListener('click', (e) => {
        if (suppressClick) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    // ===== بديل الوصولية: أسهم أعلى/أسفل (WCAG 2.2 — 2.5.7) =====
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-move');
        if (!btn) return;
        const r   = btn.closest('.sortable-row');
        const sib = btn.dataset.move === 'up' ? r.previousElementSibling : r.nextElementSibling;
        if (!sib || !sib.classList.contains('sortable-row')) return;

        if (btn.dataset.move === 'up') { list.insertBefore(r, sib); }
        else                           { list.insertBefore(sib, r); }

        btn.focus();   // نقل العقدة يُفقد التركيز — نعيده كي لا يسقط قارئ الشاشة
        const all = rows();
        if (status) {
            status.textContent = '«' + r.querySelector('.row-title').textContent +
                '» أصبح في المرتبة ' + (all.indexOf(r) + 1) + ' من ' + all.length;
        }
        emit();
    });
})();
```

### الربط بزرّ الحفظ

```js
let pending = null;
const saveBtn = document.getElementById('saveOrder');

list.addEventListener('order:changed', (e) => {
    pending = e.detail.order;
    saveBtn.disabled = false;
});

window.addEventListener('beforeunload', (e) => { if (pending) e.preventDefault(); });
```

---

## قائمة الاختبار قبل الدمج

- [ ] `node --check assets/js/admin.js`
- [ ] ماوس: السحب يبدأ بعد 5px، والنقر على حقل الاسم داخل الصف لا يبدأ سحباً
- [ ] لمس/أندرويد: التمرير بالإصبع فوق القائمة يعمل قبل الضغطة المطوّلة
- [ ] لمس: الضغط 250ms ثابتاً يبدأ السحب، والتحريك بعده **لا** يمرّر الصفحة
- [ ] iOS: لا تظهر العدسة ولا قائمة الاستدعاء أثناء الضغط المطوّل
- [ ] `pointercancel` (تبديل تطبيق أثناء السحب) يعيد الترتيب كما كان بلا خطأ
- [ ] لوحة المفاتيح: Tab إلى ▲/▼، والتركيز يبقى على الزرّ بعد النقل
- [ ] قارئ شاشة: `aria-live` يعلن المرتبة الجديدة
- [ ] الشبح يظهر تحت الإصبع تماماً — لا إزاحة بعرض الشاشة (فحص RTL)
- [ ] الحفظ: طلب واحد، `csrf_token` في جسم JSON، والردّ JSON دائماً
- [ ] `includes/config.php` ما زال يرجع `403` (بوابة `CLAUDE.md`)

---

## المصادر

**المعايير والتوثيق**

- [MDN — Pointer events](https://developer.mozilla.org/en-US/docs/Web/API/Pointer_events) — Baseline منذ يوليو 2020
- [MDN — `Element.setPointerCapture()`](https://developer.mozilla.org/en-US/docs/Web/API/Element/setPointerCapture)
- [MDN — `touch-action`](https://developer.mozilla.org/en-US/docs/Web/CSS/touch-action)
- [MDN — `Element.scrollLeft`](https://developer.mozilla.org/en-US/docs/Web/API/Element/scrollLeft) — السالب في RTL
- [MDN — `aria-grabbed`](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-grabbed) — مهجورة
- [W3C — Pointer Events](https://www.w3.org/TR/pointerevents/)
- [WHATWG HTML — 6.11 Drag and drop](https://html.spec.whatwg.org/dev/dnd.html)
- [WCAG 2.2 — Understanding SC 2.5.7 Dragging Movements](https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html)
- [W3C ACTION-1672 — إهجار `aria-grabbed`/`aria-dropeffect`](https://www.w3.org/WAI/ARIA/track/actions/1672)
- [Chrome for Developers — Making touch scrolling fast by default](https://developer.chrome.com/blog/scrolling-intervention)
- [Chrome for Developers — Pointing the way forward](https://developer.chrome.com/blog/pointer-events)
- [caniuse — Pointer events](https://caniuse.com/pointer)

**مكتبات كمرجع (لا كتبعية)**

- [dnd-kit — Touch sensor](https://docs.dndkit.com/api-documentation/sensors/touch) — `delay: 250, tolerance: 5`
- [`@hello-pangea/dnd` — Touch sensor](https://github.com/hello-pangea/dnd/blob/main/docs/sensors/touch.md) — إلغاء الضغطة عند `touchmove`
- [react-beautiful-dnd#1464](https://github.com/atlassian/react-beautiful-dnd/issues/1464) — قيمة `timeForLongPress`
- [SortableJS](https://github.com/SortableJS/Sortable) — `delay` · `delayOnTouchOnly` · `touchStartThreshold`
- [dragdroptouch](https://github.com/drag-drop-touch-js/dragdroptouch) و[mobile-drag-drop](https://github.com/timruffles/mobile-drag-drop) — polyfills اللمس لـ HTML5 DnD

**أخطاء RTL موثّقة**

- [angular/components@f3bb0c7](https://github.com/angular/components/commit/f3bb0c7) — فهرس خاطئ في قائمة أفقية داخل RTL
- [angular/components#29604](https://github.com/angular/components/issues/29604) — إزاحة العنصر المسحوب بعرض الشاشة في RTL
- [microsoft/fluentui#7230](https://github.com/microsoft/fluentui/issues/7230) — مؤشّر الإفلات عالق على الفهرس صفر في RTL
- [jquery.rtl-scroll-type](https://github.com/othree/jquery.rtl-scroll-type) — مدارس `scrollLeft` الثلاث
- [WebKit bug 231161](https://bugs.webkit.org/show_bug.cgi?id=231161) — العدسة على iOS 15 رغم `user-select: none`
