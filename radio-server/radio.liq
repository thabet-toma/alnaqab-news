# راديو النقب — إعداد Liquidsoap
#
# وظيفته: يشغّل الأغاني على مدار الساعة، ولما يتصل المذيع بالمايك
# يتحوّل لصوته فوراً، ولما يفصل يرجع للأغاني لحاله.
#
# المسار على السيرفر: /srv/radio/radio.liq
# افحص الملف قبل التشغيل:  liquidsoap --check /srv/radio/radio.liq
#
# الملف مكتوب بأقل عدد ممكن من الخيارات عن قصد — كل سطر إضافي
# احتمال فشل إضافي. التحسينات الاختيارية مذكورة بآخر الملف.

# ===== كلمات السر =====
# ⬇️ غيّر الاثنتين. الأولى نفسها الموجودة في icecast.xml (<source-password>)
icecast_pass = "غيّر_كلمة_سر_icecast"
live_pass    = "غيّر_كلمة_سر_المذيع"

# ===== الأغاني (التشغيل التلقائي) =====
# أي ملف صوتي تضعه في هذا المجلد يدخل الدورة
music = playlist(mode = "randomize", "/srv/radio/music")

# mksafe يضمن ألا يتوقف البث لو صار المجلد فارغاً أو تعذّر قراءة ملف
music = mksafe(music)

# ===== مدخل المذيع المباشر =====
# برنامج المذيع (BUTT) يتصل على هذا المنفذ بالـ mountpoint اسمه /live
live = input.harbor("live", port = 8005, password = live_pass)

# ===== التبديل بين المذيع والأغاني =====
# track_sensitive = false : التحويل للمذيع فوري ولا ينتظر انتهاء الأغنية
radio = fallback(track_sensitive = false, [live, music])

# ===== الإخراج إلى Icecast =====
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
#
# 3) التقاط الملفات الجديدة تلقائياً بدون إعادة تشغيل:
#      music = playlist(mode = "randomize", reload_mode = "watch",
#                       "/srv/radio/music")
#    (لو رفضها إصدارك، أعد تشغيل الخدمة بعد إضافة أغاني:
#     sudo systemctl restart liquidsoap)
# ---------------------------------------------------------------
