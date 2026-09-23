# تركيب الراديو على سيرفرك

**لا تسجّل بأي موقع.** الراديو بيصير على نفس الـ VPS يلي بيشغّل الموقع.
تكلفة زيادة: صفر.

- **البرنامجان:** `Icecast` يوزّع الصوت على المستمعين، و`Liquidsoap` يشغّل
  الأغاني ويستقبل المايك.
- **الموارد:** حوالي 250 ميغا رام. على السيرفر الحالي (رام 3.9 غيغا) السواب ممتلئ فعلاً — راجع جدول «الموارد المقيسة» في `PROJECT_MAP.md` قبل إضافة أي حمل.
- **الوقت:** ~20 دقيقة.

نفّذ الخطوات بعد ما يخلص رفع الموقع ([DEPLOY.md](../DEPLOY.md)).
استبدل `example.com` بدومينك.

---

## 1. التثبيت

```bash
sudo apt update
sudo apt install -y icecast2 liquidsoap
```

أثناء تثبيت `icecast2` رح يسألك "Configure Icecast2?" → اختر **Yes**، وحط
كلمات سر (أي شي، رح نغيّرها بالخطوة الجاية).

---

## 2. إعداد Icecast

```bash
sudo nano /etc/icecast2/icecast.xml
```

غيّر هذه الوسوم:

```xml
<source-password>كلمة_سر_icecast</source-password>
<relay-password>كلمة_سر_icecast</relay-password>
<admin-password>كلمة_سر_الأدمن</admin-password>

<hostname>example.com</hostname>
```

وتأكد أن Icecast يستمع على المنفذ المحلي فقط (الوصول للناس بيمرق عبر
Apache بالخطوة 6، فما في داعي نفتحه للإنترنت):

```xml
<listen-socket>
    <port>8000</port>
    <bind-address>127.0.0.1</bind-address>
</listen-socket>
```

فعّله وشغّله:

```bash
sudo sed -i 's/ENABLE=false/ENABLE=true/' /etc/default/icecast2
sudo systemctl enable --now icecast2
sudo systemctl status icecast2 --no-pager
```

---

## 3. المجلدات وملف الإعداد

```bash
sudo mkdir -p /srv/radio/music /srv/radio/playlists /var/log/liquidsoap
sudo cp /var/www/alnaqab/radio-server/radio.liq /srv/radio/radio.liq
sudo nano /srv/radio/radio.liq
```

`/srv/radio/playlists` هو مجلد ملفات `.m3u` يلي بتكتبها لوحة التحكم من قاعدة
البيانات. **Liquidsoap ما بيقرأ مجلد الأغاني مباشرة** — بيقرأ
`/srv/radio/playlists/default.m3u`، يعني بلاي ليست افتراضية بلا هذا الملف =
بث صامت. لازم مستخدم PHP يقدر يكتب فيه:

```bash
sudo chown liquidsoap:liquidsoap /srv/radio/playlists
sudo chmod 2775 /srv/radio/playlists     # setgid: أي ملف جديد بيرث المجموعة
```

(`www-data` عضو بمجموعة `liquidsoap` من إعداد السوكيت بالخطوة 4.)

غيّر الأسطر الثلاثة:

| المتغير | القيمة |
|---|---|
| `icecast_pass` | نفس `<source-password>` من الخطوة 2 |
| `live_pass` | كلمة سر المذيع الأول |
| `live2_pass` | كلمة سر المذيع الثاني — **لازم تختلف عن الأولى**، هي يلي بتميّز مين على الهواء |

**افحص الملف قبل ما تكمّل** — هذا الأمر بيكشف أي خطأ صيغة فوراً بدل ما
تكتشفه بعد ما تفشل الخدمة:

```bash
liquidsoap --check /srv/radio/radio.liq
```

لازم يخلص بدون أي رسالة خطأ. لو اشتكى من سطر معيّن، احذف السطر (كل
الأسطر الاختيارية معلّمة بآخر الملف) وأعد الفحص.

---

## 4. تشغيل Liquidsoap كخدمة

```bash
sudo useradd -r -s /usr/sbin/nologin liquidsoap 2>/dev/null || true
sudo chown -R liquidsoap:liquidsoap /srv/radio /var/log/liquidsoap

sudo cp /var/www/alnaqab/radio-server/liquidsoap.service \
        /etc/systemd/system/liquidsoap.service
sudo systemctl daemon-reload
sudo systemctl enable --now liquidsoap
sudo systemctl status liquidsoap --no-pager
```

لو ظهر خطأ، اقرأ السبب:

```bash
sudo journalctl -u liquidsoap -n 40 --no-pager
```

---

## 5. ارفع الأغاني

من جهازك (استبدل `USER` و `IP`):

```bash
scp *.mp3 USER@IP:/tmp/
```

ثم على السيرفر:

```bash
sudo mv /tmp/*.mp3 /srv/radio/music/
sudo chown liquidsoap:liquidsoap /srv/radio/music/*
```

Liquidsoap بيلتقط الملفات الجديدة لحاله خلال دقيقة — بدون إعادة تشغيل.

---

## 6. ربط البث بالدومين عبر HTTPS

**هذه الخطوة إلزامية.** الموقع بيشتغل على HTTPS، ولو كان رابط البث `http`
فالمتصفح بيحجبه (Mixed Content) وما بيطلع صوت أبداً.

```bash
sudo a2enmod proxy proxy_http
sudo nano /etc/apache2/sites-available/alnaqab-le-ssl.conf
```

> لو ما لقيت ملف `alnaqab-le-ssl.conf` فمعناها certbot ما اشتغل بعد —
> ارجع لخطوة SSL في [DEPLOY.md](../DEPLOY.md).

أضف هذه السطور **جوّا** وسم `<VirtualHost *:443>`:

```apache
    ProxyPreserveHost Off
    ProxyPass        /stream  http://127.0.0.1:8000/radio
    ProxyPassReverse /stream  http://127.0.0.1:8000/radio
```

ثم:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

صار رابط بثك: **`https://example.com/stream`**

---

## 7. اربطه بالموقع

`https://example.com/admin/` ← **إعدادات الراديو** ← خانة **رابط البث** ←
الصق `https://example.com/stream` ← **حفظ**.

افتح `https://example.com/radio.php` — المفروض الصوت يشتغل تلقائياً.

---

## 8. المايك المباشر

على جهاز المذيع، نزّل [BUTT](https://danielnoethen.de/butt/) واضبط:

في مذيعان، كل واحد بمنفذه وكلمة سره:

| الخانة | المذيع الأول | المذيع الثاني |
|---|---|---|
| Type | **Icecast** | **Icecast** |
| Address | `example.com` | `example.com` |
| Port | **8005** | **8006** |
| Password | `live_pass` من الخطوة 3 | `live2_pass` من الخطوة 3 |
| Mountpoint | `/live` | `/live2` |
| Format | MP3 | MP3 |

افتح المنفذين بالجدار الناري:

```bash
sudo ufw allow 8005/tcp
sudo ufw allow 8006/tcp
```

**الجدار لازم يكون مفعّلاً أصلاً** (`sudo ufw status`) — منفذ المايك مكشوف
للإنترنت وما بيحميه غير كلمة السر، فتركه بلا جدار فجوة قائمة لحالها.

اضغط زر التسجيل في BUTT → صوتك بيقطع الأغاني فوراً. سكّر → الأغاني بترجع
لحالها. لو اتصل الاثنان معاً بينخلط صوتهما وبيسمع الناس الاثنين، وفصل واحد
ما بيقطع التاني.

---

## 9. الاستوديو المباشر (البث من المتصفح + روابط الضيوف + العرض المرئي)

من لوحة التحكم ← **الاستوديو المباشر**: المذيع يبثّ من المتصفح، يُنشئ رابطاً
للضيف يفتحه ويتكلم، يكتم أو يطرد أي صوت، ويعرض صوراً وفيديو. BUTT يبقى يعمل
كما في الخطوة 8 — كلمتا السر الثابتتان تُقبلان دائماً حتى لو تعطّل الموقع.

**كيف يعمل الدخول:** المتصفح يتصل بنفس المنفذين 8005/8006 عبر WebSocket (مروراً
بـ Nginx/Apache على HTTPS)، ويرسل رمزاً مؤقتاً بدل كلمة السر. Liquidsoap يسأل
`cron/radio-live-auth.php` عن كل رمز. الرمز يُلغى بالطرد أو من اللوحة.

### 9.1 الجدولان (مرة واحدة)

```sql
-- نفس ما في install.php — IF NOT EXISTS، إضافي فقط
CREATE TABLE IF NOT EXISTS radio_live_tokens ( ...انظر install.php... );
CREATE TABLE IF NOT EXISTS radio_visuals ( ...انظر install.php... );
```

انسخ الجملتين كاملتين من `install.php` (كتلة `CREATE TABLE`) ونفّذهما على
قاعدة البيانات الحيّة. حالة العرض الجارية تُحفظ في جدول `settings` الموجود
(`radio_visual_now`، `radio_visual_delay`، `radio_live_slot1/2`) فلا تحتاج جدولاً.

### 9.2 صلاحية القراءة لبوّاب الدخول

سكربت التحقق يعمل بمستخدم `liquidsoap` ويحتاج قراءة `includes/config.local.php`
(صلاحيته 640 لمجموعة `www-data`):

```bash
sudo usermod -aG www-data liquidsoap
```

مسار السكربت مكتوب في `radio.liq` (`live_gate`) — عدّله إن لم يكن الموقع في
`/var/www/radio.ktra-pro.tech`.

### 9.3 تمرير WebSocket

**Nginx** — داخل `server { ... }` الخاص بـ 443:

```nginx
location = /live-in/1 {
    proxy_pass http://127.0.0.1:8005/live;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 3600s;
    proxy_buffering off;
}
location = /live-in/2 {
    proxy_pass http://127.0.0.1:8006/live2;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 3600s;
    proxy_buffering off;
}
```

**Apache** — `sudo a2enmod proxy_wstunnel` ثم داخل `<VirtualHost *:443>`:

```apache
    ProxyPass /live-in/1 ws://127.0.0.1:8005/live
    ProxyPass /live-in/2 ws://127.0.0.1:8006/live2
```

### 9.4 حجم رفع الفيديو

الفيديو حتى 64 ميغابايت. ارفع حدود PHP (`upload_max_filesize = 64M` و
`post_max_size = 70M`) و`client_max_body_size 70m;` في Nginx.

### 9.5 النشر والفحص

```bash
sudo cp /srv/radio/radio.liq /srv/radio/radio.liq.bak     # نسخة للرجوع
sudo cp radio-server/radio.liq /srv/radio/radio.liq       # ثم أعد كلمات السر الثلاث من النسخة
liquidsoap --check /srv/radio/radio.liq
sudo systemctl restart liquidsoap
```

بعد التشغيل:

```bash
# الأوامر الجديدة موجودة؟ المتوقع: live1_gain و live2_gain وأوامر visual.*
echo help | socat - UNIX-CONNECT:/srv/radio/liquidsoap.sock | grep -E "gain|visual"
# WebSocket يمرّ؟ المتوقع: 101 Switching Protocols
curl -si -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" \
     -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" -H "Sec-WebSocket-Protocol: webcast" \
     https://example.com/live-in/2 | head -1
```

ثم اختبار القبول المكتوب في آخر `radio.liq` (البنود 1–6).

---

## فحص سريع

```bash
# البث شغّال؟
curl -sI https://example.com/stream | head -3     # المتوقع: 200 و audio/mpeg

# الخدمتان تعملان؟
systemctl is-active icecast2 liquidsoap           # المتوقع: active active

# ماذا يُبَث الآن؟
curl -s http://127.0.0.1:8000/status-json.xsl | head -20
```

## حل المشاكل

| العَرَض | السبب الغالب |
|---|---|
| `/stream` يرجّع 404 | سطور `ProxyPass` انحطّت بملف الـ 80 بدل الـ 443، أو Apache ما عمل reload |
| `/stream` يرجّع 502 | Liquidsoap واقف — `journalctl -u liquidsoap -n 40` |
| صوت ما بيطلع والصفحة تقول "جاري الاتصال" | رابط البث `http` مش `https` |
| BUTT ما بيتصل | المنفذ (8005 للأول، 8006 للتاني) مسكّر بالجدار الناري، أو نسيت `/` قبل اسم الـ mountpoint |
| الأغاني ما بتشتغل | المجلد `/srv/radio/music` فاضي، أو الملكية مش `liquidsoap` |
| بث صامت رغم وجود مقاطع | `/srv/radio/playlists/default.m3u` مش موجود — ما في بلاي ليست افتراضية باللوحة، أو `www-data` ما بيقدر يكتب بمجلد `playlists` |
| اللوحة بتقول «تعذّر التبديل» | نفس السبب أعلاه، أو `music.uri` ما اشتغل لأن معرّف مصدر الأغاني بـ `radio.liq` مش `music` |
| البلاي ليست ما بتتبدّل بالموعد | مهمة الـ cron مش مثبّتة — اللوحة بتحذّر بشريط أحمر بصفحة الجدولة |
| الضيف أو المذيع من المتصفح: «رُفض الاتصال» | `liquidsoap` مش بمجموعة `www-data` (9.2)، أو مسار `live_gate` غلط — `journalctl -u liquidsoap -n 40` |
| المتصفح يظل «جارٍ الاتصال» | مواقع `/live-in/` ناقصة بإعداد Nginx/Apache (9.3) |
| الكتم ما بيشتغل | `radio.liq` القديم لسا منشور — ما فيه `live1_gain` |
| الفيديو بيظهر بلا صوت | المحرّك ما بيقدر يقرأ `uploads/visuals` — لازم الملفات 644 والمجلدات قابلة للدخول |
