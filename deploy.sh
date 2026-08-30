#!/usr/bin/env bash
# نشر موقع النقب الإخباري إلى radio.ktra-pro.tech
# الاستخدام: ./deploy.sh
set -euo pipefail

SRC="/root/radio/"
DEST="/var/www/radio.ktra-pro.tech/"

echo "▸ مزامنة الملفات..."
rsync -a --delete \
    --exclude '.git' \
    --exclude '.claude' \
    --exclude '.db-credentials' \
    --exclude 'deploy.sh' \
    --exclude 'install.php' \
    --exclude 'uploads/' \
    "$SRC" "$DEST"

echo "▸ ضبط الصلاحيات..."
chown -R www-data:www-data "$DEST"
find "$DEST" -type d -exec chmod 755 {} \;
find "$DEST" -type f -exec chmod 644 {} \;
chmod 640 "$DEST/includes/config.local.php"
chmod -R 775 "$DEST/uploads"

echo "▸ فحص صياغة PHP..."
find "$DEST" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null

echo "▸ إعادة تحميل الخدمات..."
systemctl reload php8.3-fpm
nginx -t >/dev/null && systemctl reload nginx

echo "✅ تم النشر — https://radio.ktra-pro.tech"
