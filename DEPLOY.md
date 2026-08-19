# دليل الرفع على سيرفر (Production)

هذا الموقع: PHP + MySQL. والراديو (تشغيل تلقائي + مايك مباشر) يُركَّب على
نفس السيرفر عبر Icecast + Liquidsoap — بدون أي اشتراك خارجي.

الترتيب: أنهِ هذا الملف أولاً، ثم [radio-server/README.md](radio-server/README.md).

---

## 1. ما تطلبه من شركة الاستضافة

انسخ هذا وابعثه لهم:

```text
Hello,

I need a small VPS to host a PHP website. Please set up:

- OS: Ubuntu 24.04 LTS (or 22.04 LTS)
- Plan: 1 vCPU, 2 GB RAM, 25 GB SSD (smallest plan is fine)
- Web server: Apache 2.4
- PHP 8.2+ with extensions: pdo_mysql, mbstring, fileinfo, json
- Database: MySQL 8 or MariaDB 10.6+
- Enable Apache modules: rewrite, headers, expires, deflate
- Set "AllowOverride All" for the site directory (my .htaccess must work)
- Free SSL certificate (Let's Encrypt / certbot)
- Firewall: allow ports 22, 80, 443

Please send me:
1. Server IP address
2. SSH username + password (or SSH key)
3. MySQL root password
4. Confirmation that Apache, PHP and MySQL are running

Thank you.
```

> **`AllowOverride All` هو أهم بند.** بدونه يُتجاهل ملف `.htaccess` بالكامل،
> فيصبح مجلد `includes/` (وفيه كلمة سر قاعدة البيانات) مكشوفاً لأي زائر.

---

## 2. توجيه الدومين

من لوحة تحكم الدومين، أضف سجلّين:

| Type | Name | Value |
|------|------|-------|
| A | `@` | عنوان IP السيرفر |
| A | `www` | عنوان IP السيرفر |

انتظر من 10 دقائق حتى ساعتين حتى ينتشر التغيير.

---

## 3. خطوات الرفع (على السيرفر عبر SSH)

استبدل `example.com` بدومينك في كل الأوامر.

### أ. تثبيت المتطلبات

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-mbstring \
                    php-xml libapache2-mod-php git
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2
```

### ب. جلب الكود

```bash
sudo mkdir -p /var/www/alnaqab
sudo chown -R $USER:$USER /var/www/alnaqab
git clone https://github.com/thabet-toma/alnaqab-news.git /var/www/alnaqab
```

### ج. إنشاء قاعدة البيانات

```bash
sudo mysql
```

ثم داخل MySQL (غيّر كلمة السر):

```sql
CREATE DATABASE naqab_news CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'naqab'@'localhost' IDENTIFIED BY 'ضع_كلمة_سر_قوية_هنا';
GRANT ALL PRIVILEGES ON naqab_news.* TO 'naqab'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### د. ملف الإعدادات

```bash
cd /var/www/alnaqab
cp includes/config.example.php includes/config.php
nano includes/config.php
```

عدّل هذه السطور فقط:

| السطر | القيمة |
|---|---|
| `APP_ENV` | `production` |
| `DB_USER` | `naqab` |
| `DB_PASS` | كلمة السر التي وضعتها فوق |
| `SITE_URL` | `https://example.com` — **بدون `/` في النهاية** |

احفظ بـ `Ctrl+O` ثم `Enter`، واخرج بـ `Ctrl+X`.

> `config.php` غير مرفوع على Git، فلا تُفقد إعداداتك عند أي تحديث لاحق.

### هـ. الصلاحيات

```bash
sudo chown -R www-data:www-data /var/www/alnaqab
sudo find /var/www/alnaqab -type d -exec chmod 755 {} \;
sudo find /var/www/alnaqab -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/alnaqab/uploads
```

### و. إعداد Apache

```bash
sudo nano /etc/apache2/sites-available/alnaqab.conf
```

الصق هذا (غيّر الدومين):

```apache
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
    DocumentRoot /var/www/alnaqab

    <Directory /var/www/alnaqab>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/alnaqab-error.log
    CustomLog ${APACHE_LOG_DIR}/alnaqab-access.log combined
</VirtualHost>
```

ثم فعّله:

```bash
sudo a2ensite alnaqab
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### ز. شهادة SSL (https)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d example.com -d www.example.com
```

اختر إعادة التوجيه التلقائي إلى HTTPS عندما يسألك.

### ح. الجدار الناري

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

### ط. التثبيت من المتصفح

افتح `https://example.com/install.php`، أنشئ الجداول، ثم أنشئ حساب المدير.

### ي. ⚠️ احذف ملف التثبيت فوراً

```bash
sudo rm /var/www/alnaqab/install.php
```

**لا تتجاوز هذه الخطوة.** ملف التثبيت يُنشئ حسابات مدير، وتركه على موقع
شغّال يعني أن أي شخص قد يحاول استغلاله للوصول إلى لوحة التحكم.

### ك. الراديو

الآن انتقل إلى [radio-server/README.md](radio-server/README.md) لتركيب
Icecast + Liquidsoap على نفس السيرفر. رابط البث سيصبح
`https://example.com/stream` وتضعه من لوحة التحكم ← إعدادات الراديو.

---

## 4. فحص بعد الرفع

نفّذ هذه وتأكد من النتائج:

```bash
curl -o /dev/null -s -w "%{http_code}\n" https://example.com/
curl -o /dev/null -s -w "%{http_code}\n" https://example.com/includes/config.php
curl -o /dev/null -s -w "%{http_code}\n" https://example.com/install.php
```

| الرابط | المتوقع |
|---|---|
| الصفحة الرئيسية | `200` |
| `includes/config.php` | `403` — لو رجع `200` فالـ `.htaccess` معطّل، **أوقف الموقع وراجع `AllowOverride All`** |
| `install.php` | `404` — لأنك حذفته |

---

## 5. التحديث لاحقاً

```bash
cd /var/www/alnaqab
sudo -u www-data git pull
sudo chown -R www-data:www-data /var/www/alnaqab
sudo chmod -R 775 /var/www/alnaqab/uploads
```

---

## 6. ملاحظات أمنية دائمة

- **لا تضع كلمات سر حقيقية في أي ملف مرفوع على Git.** كل الأسرار في
  `includes/config.php` وهو مستثنى من Git.
- الريبو **عام**، فأي شخص يقرأ الكود — وهذا مقبول ما دامت الأسرار خارجه.
- غيّر كلمة سر حساب المدير إلى كلمة قوية، ولا تستخدم `admin/admin`.
- خذ نسخة احتياطية من قاعدة البيانات دورياً:
  ```bash
  mysqldump -u naqab -p naqab_news > backup-$(date +%F).sql
  ```
