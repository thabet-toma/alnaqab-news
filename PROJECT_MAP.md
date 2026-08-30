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

## [ORPHANS & PENDING]
- None. All tasks completed successfully.
