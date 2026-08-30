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

## 3. مجلد الأغاني وملف الإعداد

```bash
sudo mkdir -p /srv/radio/music /var/log/liquidsoap
sudo cp /var/www/alnaqab/radio-server/radio.liq /srv/radio/radio.liq
sudo nano /srv/radio/radio.liq
```

غيّر السطرين:

| المتغير | القيمة |
|---|---|
| `icecast_pass` | نفس `<source-password>` من الخطوة 2 |
| `live_pass` | كلمة سر تعطيها للمذيع |

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

| الخانة | القيمة |
|---|---|
| Type | **Icecast** |
| Address | `example.com` |
| Port | **8005** |
| Password | `live_pass` من الخطوة 3 |
| Mountpoint | `/live` |
| Format | MP3 |

افتح منفذ المذيع بالجدار الناري:

```bash
sudo ufw allow 8005/tcp
```

اضغط زر التسجيل في BUTT → صوتك بيقطع الأغاني فوراً. سكّر → الأغاني بترجع
لحالها.

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
| BUTT ما بيتصل | المنفذ 8005 مسكّر بالجدار الناري، أو نسيت `/` قبل `live` |
| الأغاني ما بتشتغل | المجلد `/srv/radio/music` فاضي، أو الملكية مش `liquidsoap` |
