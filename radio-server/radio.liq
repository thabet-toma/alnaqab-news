# راديو النقب — إعداد Liquidsoap
#
# وظيفته: يشغّل الأغاني على مدار الساعة، ولما يتصل المذيع بالمايك
# يتحوّل لصوته فوراً، ولما يفصل يرجع للأغاني لحاله.
# وكمان يستقبل أوامر من لوحة تحكم الموقع (شغّل هذا المقطع الآن / المجدولة).
#
# المسار على السيرفر: /srv/radio/radio.liq
# افحص الملف قبل التشغيل:  liquidsoap --check /srv/radio/radio.liq

# ===== كلمات السر =====
# ⬇️ غيّر الاثنتين. الأولى نفسها الموجودة في icecast.xml (<source-password>)
icecast_pass = "غيّر_كلمة_سر_icecast"
live_pass    = "غيّر_كلمة_سر_المذيع"

# ===== قناة التحكم =====
# لوحة التحكم بالموقع تبعث أوامرها عبر هذا السوكيت (requests.push وغيره).
# سوكيت ملفّي لا منفذ شبكة، فلا ينفتح على الإنترنت إطلاقاً. الصلاحية 0660
# ليتمكّن مستخدم PHP (www-data، وهو عضو بمجموعة liquidsoap) من الكتابة فيه.
settings.server.socket := true
settings.server.socket.path := "/srv/radio/liquidsoap.sock"
settings.server.socket.permissions := 0o660

# ===== الأغاني (التشغيل التلقائي) =====
# reload_mode="watch" يلتقط أي ملف جديد ترفعه لوحة التحكم فوراً بدون إعادة تشغيل
music = playlist(mode = "randomize", reload_mode = "watch", "/srv/radio/music")

# mksafe يضمن ألا يتوقف البث لو صار المجلد فارغاً أو تعذّر قراءة ملف
music = mksafe(music)

# ===== طابور الطلبات =====
# لوحة التحكم تدفع فيه مقاطع لتشتغل فوراً عبر الأمر: requests.push <مسار>
# وهو نفسه مصدر المقاطع المجدولة زمنياً (cron/radio-scheduler.php).
requests = request.queue(id = "requests")

# ===== مدخل المذيع المباشر =====
# برنامج المذيع (BUTT) يتصل على هذا المنفذ بالـ mountpoint اسمه /live
live = input.harbor("live", port = 8005, password = live_pass)

# ===== الأولوية =====
# المذيع المباشر يعلو على كل شي، ثم الطلبات/المجدول، ثم الأغاني العادية.
# track_sensitive = false : التحويل فوري ولا ينتظر انتهاء المقطع الحالي
radio = fallback(track_sensitive = false, [live, requests, music])

# ===== الإخراج إلى Icecast =====
# ⬇️ تأكد أن المنفذ هنا هو نفسه <port> في icecast.xml
output.icecast(
  %mp3(bitrate = 128),
  host     = "localhost",
  port     = 8000,
  password = icecast_pass,
  mount    = "/radio",
  radio
)

# ---------------------------------------------------------------
# تحسينات اختيارية — أضفها بعد ما يشتغل الأساس، وافحص بعد كل إضافة
# بـ liquidsoap --check قبل ما تعيد تشغيل الخدمة:
#
# 1) تلاشٍ ناعم بين الأغاني — ضعه على الأغاني فقط، لا على البث المدمج:
#      music = crossfade(duration = 2.0, music)
#
# 2) تجاهل الصمت القصير من المذيع حتى لا تقطع وصلته عند تلعثم الشبكة:
#      live = blank.strip(max_blank = 5.0, live)
# ---------------------------------------------------------------
