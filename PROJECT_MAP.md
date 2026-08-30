# PROJECT_MAP.md — موقع النقب الإخباري

## [TECH_STACK]
| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | PHP (Vanilla + PDO) | 8.3+ |
| Database | MySQL / MariaDB | 10.x+ |
| Frontend | HTML5 + Vanilla CSS + Vanilla JS | — |
| Fonts | Google Fonts (Tajawal, Lalezar) | — |
| Server | Apache + LiteSpeed (Hostinger) | — |

## [SYSTEM_FLOW]
Visitor: Homepage → Article/Category/Search/Radio
Admin: Login → Dashboard → CRUD Articles/Categories/Ads/Radio/Settings

## [ARCHITECTURE]
M1 Infrastructure: config, db, auth, functions, install, htaccess — DONE
M2 Admin Panel: 12 files — DONE
M3 Frontend: 8 files — DONE
M4 Radio: 4 files — DONE

## [DEPLOYMENT — radio.ktra-pro.tech]
| Item | Value |
|------|-------|
| URL | https://radio.ktra-pro.tech (HTTP → HTTPS 301) |
| Web root | `/var/www/radio.ktra-pro.tech` |
| Source | `/root/radio` — انشر بـ `./deploy.sh` |
| Web server | Nginx + PHP 8.3-FPM (قواعد `.htaccess` مترجمة في `/etc/nginx/sites-available/radio.ktra-pro.tech`) |
| Database | MySQL 8.4 في Docker (`naqab-mysql`) على `127.0.0.1:3307` |
| PHP ini | `/etc/php/8.3/fpm/conf.d/99-naqab.ini` (رفع 8M، توقيت Asia/Hebron) |
| SSL | Let's Encrypt — تجديد تلقائي عبر snap timer |
| Secrets | `includes/config.local.php` و `.db-credentials` — خارج git |

`install.php` غير منشور على السيرفر (يُستثنى في `deploy.sh`).

## [RADIO SERVER — Icecast + Liquidsoap]
| Item | Value |
|------|-------|
| Icecast | يستمع محليًا على `127.0.0.1:8010` (المنفذ 8000 كان محجوزًا لتطبيق gunicorn آخر على نفس السيرفر) |
| Liquidsoap | خدمة systemd `liquidsoap.service`، يقرأ `/srv/radio/radio.liq`، الأغاني في `/srv/radio/music` |
| مدخل المذيع (BUTT) | منفذ `8005` — مفتوح بقاعدة `ufw allow 8005/tcp` (الجدار نفسه inactive حاليًا) |
| كلمات السر | `/root/.icecast-credentials` (chmod 600، خارج git) |
| ربط الدومين | nginx (لا Apache) — `location /stream` في `/etc/nginx/sites-available/radio.ktra-pro.tech` يعمل proxy_pass إلى `127.0.0.1:8010/radio` |
| رابط البث العام | `https://radio.ktra-pro.tech/stream` — محفوظ في `radio_config.stream_url` بقاعدة البيانات |
| المكتبة | `/srv/radio/music` — 5 ملفات محلية + 3 في `cloud/` (مقيس 2026-08-30) |
| الموارد المقيسة | نواة واحدة · رام 3915 م.ب · ⚠️ **السواب ممتلئ 2046/2047، المتاح 974 م.ب** |
| إصدارات مقيسة | Liquidsoap 2.2.4 · ffmpeg 6.1.1 · PHP 8.3.6 · `ufw` معطّل |

> ملاحظة: `radio-server/README.md` بالريبو مكتوب لسيرفر Apache على منفذ 8000 —
> هذا السيرفر Nginx والمنفذ الفعلي 8010. اعتمد على هذا الجدول لا على الملف عند أي صيانة مستقبلية.

## [RADIO ADMIN — مكتبة الصوتيات والجدولة]
| Item | Value |
|------|-------|
| المكتبة | `admin/radio-library.php` — رفع/حذف/إعادة تسمية + «شغّل الآن» + تخطّي |
| الجدولة | `admin/radio-schedule.php` — موعد + أيام أسبوع لكل مقطع |
| طبقة التحكم | `includes/radio-control.php` — تتكلم مع Liquidsoap عبر سوكيت |
| مشغّل المواعيد | `cron/radio-scheduler.php` كل دقيقة عبر `/etc/cron.d/naqab-radio` (بمستخدم www-data) |
| جداول جديدة | `radio_tracks` و `radio_schedule` |
| سوكيت التحكم | `/srv/radio/liquidsoap.sock` — صلاحية 0660 مجموعة `liquidsoap` |
| صلاحيات | `www-data` أُضيف لمجموعة `liquidsoap`؛ مجلد الأغاني `2775` (setgid) |
| حدود الرفع | رُفعت إلى 64M بـ `99-naqab.ini` و 70M بـ `client_max_body_size` |

أوامر Liquidsoap المستخدمة (تختلف أسماؤها حسب `id` المصادر):
`requests.push <مسار>` للتشغيل الفوري، `/radio.skip` للتخطّي،
`input.harbor.status` لمعرفة إن كان المذيع متصلاً (يردّ `no source client connected` عند عدم الاتصال —
انتبه أن العبارة تحوي نصّ الاتصال داخلها فلا تفحصها بـ `str_contains`).

مجلد `cron/` محجوب من nginx، والسكربت يرفض العمل خارج CLI.

## [CLOUD — الصوتيات من Google Drive]
| Item | Value |
|------|-------|
| مصدر الملفات | مجلد `Radio-Naqab` على درايف المستخدم (remote اسمه `gdrive:` بـ rclone) |
| النسخة المحلية | `/srv/radio/music/cloud/` — مرآة للدرايف، يُدار محتواها من الدرايف حصراً |
| المزامنة | `radio-cloud-sync.timer` كل 5 دقائق ← `rclone sync` (اتجاه واحد: الدرايف هو المرجع) |
| السجل | `/var/log/radio-cloud-sync.log` |
| رفع اللوحة | يبقى يكتب بجذر `/srv/radio/music/` — المزامنة لا تمسّه لأنها تطال `cloud/` فقط |

**لماذا مزامنة لا mount؟** مفتاح rclone المشترك محدود الحصّة ويفشل متقطعاً
(شوهد فشل رفع ثم نجاحه بنفس الدقيقة). مع mount كان كل تشغيل أول لمقطع
يحتاج تحميلاً حياً، وأي فشل = صمت على الهواء. بالمزامنة يقرأ Liquidsoap من
قرص محلي دائماً، وفشل المزامنة يُعوَّض بالدورة التالية بلا أثر على البث.

`playlist()` يمسح المجلدات الفرعية تلقائياً (مُختبَر)، فلم يحتج `radio.liq`
أي تعديل ليشمل ملفات الدرايف.

> ⚠️ **خطر مؤجّل:** الـ remote يستخدم `client_id` المشترك الخاص بـ rclone، وGoogle
> ستوقفه خلال 2026. لازم إنشاء client_id خاص وإلا تتوقف المزامنة:
> https://rclone.org/drive/#making-your-own-client-id

## [FIXES APPLIED]
- `radio.php` — كان يستدعي `e()` على مصفوفة فيسقط بخطأ 500؛ صار يقبل شكلي بيانات الصور.
- `includes/auth.php` — `verifyCsrf()` كانت تتجاهل الوسيط المُمرّر، فيفشل حذف المقالات و`save-radio.php` دائمًا بـ 403.
- `includes/config.php` — `UPLOADS_URL` كانت تنتج `//` في كل الروابط؛ أُزيلت الشرطة الزائدة.
- `radio.php` + `assets/js/radio.js` — مسارات صور غير موجودة (`assets/images/`) صُحّحت إلى `assets/img/`.
- `install.php` — بيانات صور الراديو الأولية كانت بشكل `[{"src":…}]` مخالف لما تحفظه لوحة التحكم.
- `includes/config.php` + `includes/db.php` — دعم `DB_PORT` وتحميل `config.local.php`.

## [SCHEMA — ترقية البلاي ليست]
لا يوجد نظام migrations؛ الجداول موجودة أصلاً على السيرفر الحيّ فـ
`CREATE TABLE IF NOT EXISTS` في `install.php` لن يعدّلها. الصق هذه الكتلة على
السيرفر يدوياً. `radio_playlists` يُنشأ أولاً لأن الثلاثة الباقية تشير إليه
بمفاتيح أجنبية.

```sql
CREATE TABLE IF NOT EXISTS radio_playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    is_managed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'تُبنى آلياً ولا تُحرَّر يدوياً',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_playlist_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS radio_playlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playlist_id INT NOT NULL,
    track_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    KEY idx_playlist_order (playlist_id, sort_order),
    KEY fk_item_track (track_id),
    CONSTRAINT fk_item_playlist FOREIGN KEY (playlist_id) REFERENCES radio_playlists (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_track FOREIGN KEY (track_id) REFERENCES radio_tracks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE radio_tracks
    ADD COLUMN status ENUM('ok','missing') NOT NULL DEFAULT 'ok' AFTER source;

ALTER TABLE radio_config
    ADD COLUMN default_playlist_id INT NULL AFTER stream_url,
    ADD CONSTRAINT fk_config_default_playlist FOREIGN KEY (default_playlist_id)
        REFERENCES radio_playlists (id) ON DELETE SET NULL;

ALTER TABLE radio_schedule
    MODIFY COLUMN track_id INT NULL,
    ADD COLUMN playlist_id INT NULL AFTER track_id,
    ADD CONSTRAINT fk_schedule_playlist FOREIGN KEY (playlist_id)
        REFERENCES radio_playlists (id) ON DELETE CASCADE,
    ADD CONSTRAINT chk_schedule_target CHECK ((track_id IS NULL) <> (playlist_id IS NULL));
```

## [نشر ترقية البلاي ليست — الترتيب إلزامي]

الخطوات على السيرفر الحيّ بعد `git pull`. **الترتيب مقصود**: الكود الجديد يقرأ
جداول لا توجد بعد، والمحرّك الجديد يقرأ ملفاً لا تكتبه إلا اللوحة.

1. **المخطّط أولاً.** الصق كتلة `[SCHEMA — ترقية البلاي ليست]` أعلاه. قبلها
   ترجّع `api/nowplaying.php` بنية فارغة بهدوء (مُغلَّفة بـ try/catch) ولا
   تنهار، لكن صفحات البلاي ليست لن تعمل.
2. **مجلد البلاي ليست:**
   ```
   sudo mkdir -p /srv/radio/playlists
   sudo chown liquidsoap:liquidsoap /srv/radio/playlists
   sudo chmod 2775 /srv/radio/playlists
   ```
   بدونه ترجّع `writePlaylistM3u()` قيمة `false` **بصمت** ولا يُكتب أي ملف.
3. **أنشئ بلاي ليست واحدة على الأقل وعيّنها افتراضية من اللوحة.** هذا ما يكتب
   `/srv/radio/playlists/default.m3u`، وهو ما سيقرأه المحرّك عند الإقلاع.
   **قبل هذه الخطوة لا تعِد تشغيل Liquidsoap** — سيجد ملفاً غير موجود.
4. **المحرّك:**
   ```
   sudo cp radio-server/radio.liq /srv/radio/radio.liq
   # أعِد كتابة كلمات السر الثلاث (القيم في المستودع نائبة)
   # وتأكد أن منفذ Icecast هنا = المنفذ الفعلي (8010 على هذا السيرفر)
   liquidsoap --check /srv/radio/radio.liq
   sudo systemctl restart liquidsoap
   ```
   `liquidsoap --check` **إلزامي** — لم يُفحص هذا الملف في أي بيئة تطوير
   (لا Liquidsoap على ويندوز).
5. **المذيع الثاني (المنفذ 8006) مؤجَّل**، ولا يُفتح قبل:
   تشخيص مستهلك الذاكرة (السواب مستهلك بالكامل)، و`sudo ufw enable` مع
   السماح لـ 8005 و8006. الجدار معطّل اليوم ومنفذ المايك الأول مكشوف بكلمة
   سرّ وحيدة — بند يستحقّ الإصلاح بمعزل عن هذه الميزة.
6. **تحقّق:** الأغاني تُسمع بترتيب البلاي ليست لا عشوائياً · شارة صفحة الراديو
   تعرض `4/20` وزمناً يتناقص · موعد تجريبي بعد دقيقتين يبدّل البلاي ليست فعلاً.

## [ORPHANS & PENDING]
- None. All tasks completed successfully.
