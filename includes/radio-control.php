<?php
/**
 * التحكم بمحرّك الراديو (Liquidsoap) من الموقع.
 *
 * Liquidsoap يفتح سوكيت ملفّي (Unix socket) يستقبل عليه أوامر نصية بسيطة.
 * ليس منفذ شبكة، فلا يمكن لأحد من الإنترنت الوصول إليه — الصلاحية 0660
 * والمجموعة `liquidsoap`، ومستخدم PHP (www-data) عضو فيها.
 *
 * بروتوكول Liquidsoap: تبعث سطر أمر، ويردّ بأسطر تنتهي بسطر "END".
 */

// مسارات يمكن تجاوزها من config.local.php لأنها تختلف من سيرفر لآخر
if (!defined('RADIO_SOCKET'))    define('RADIO_SOCKET', '/srv/radio/liquidsoap.sock');
if (!defined('RADIO_MUSIC_DIR')) define('RADIO_MUSIC_DIR', '/srv/radio/music');

/** مجلد ملفات .m3u المشتقّة من البلاي ليست في قاعدة البيانات */
if (!defined('RADIO_PLAYLIST_DIR')) define('RADIO_PLAYLIST_DIR', '/srv/radio/playlists');

/**
 * مجلد فرعي داخل مجلد الأغاني تُنسخ إليه ملفات Google Drive دورياً.
 * Liquidsoap يمسح المجلدات الفرعية تلقائياً فتدخل الدورة بلا إعداد إضافي.
 * محتواه مُدار من الدرايف بالكامل — أي حذف من هنا يعود بالمزامنة التالية.
 */
if (!defined('RADIO_CLOUD_SUBDIR')) define('RADIO_CLOUD_SUBDIR', 'cloud');

/** الامتدادات المسموح رفعها، مقرونة بأنواع MIME الحقيقية المقبولة */
const RADIO_AUDIO_TYPES = [
    'audio/mpeg'  => 'mp3',
    'audio/mp3'   => 'mp3',
    'audio/x-mpeg'=> 'mp3',
    'audio/mp4'   => 'm4a',
    'audio/x-m4a' => 'm4a',
    'audio/ogg'   => 'ogg',
    'audio/wav'   => 'wav',
    'audio/x-wav' => 'wav',
];

/**
 * ينفّذ أمراً على محرّك الراديو ويرجّع ردّه، أو null إن تعذّر الاتصال.
 * لا يرمي استثناءً أبداً — الواجهة تعرض رسالة لطيفة بدل أن تنهار الصفحة.
 */
function radioCommand(string $command): ?string {
    if (!file_exists(RADIO_SOCKET)) return null;

    $sock = @stream_socket_client('unix://' . RADIO_SOCKET, $errno, $errstr, 3);
    if (!$sock) return null;

    stream_set_timeout($sock, 3);
    fwrite($sock, $command . "\n");

    $lines = [];
    while (($line = fgets($sock)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === 'END') break;
        $lines[] = $line;
        // حارس ضد ردّ ضخم غير متوقّع يعلّق الصفحة
        if (count($lines) > 500) break;
    }

    fwrite($sock, "quit\n");
    fclose($sock);

    return implode("\n", $lines);
}

/**
 * أوامر القراءة الوحيدة المسموح تمريرها من نقطة عامة بلا تسجيل دخول (نقطة
 * "الآن يُشغَّل"). قائمة حرفية ثابتة لا نمطاً ولا بادئة عمداً: أي إضافة
 * مستقبلية تُكتب هنا صراحةً، فيستحيل بنيوياً أن يُبنى أمر من مدخل مستخدم أو
 * من طلب HTTP ويمرّ عبر radioReadCommand() لأن in_array الصارم يقارن نصاً
 * كاملاً لا جزءاً منه.
 */
const RADIO_READ_COMMANDS = [
    '/radio.metadata',
    '/radio.remaining',
    'var.get active_playlist',
    'input.harbor.status',
];

/**
 * ينفّذ أمراً فقط إن كان عضواً حرفياً في RADIO_READ_COMMANDS، وإلا يرفضه
 * فوراً بإرجاع null دون لمس السوكيت. هذا هو الحاجز الوحيد بين نقطة عامة
 * والتحكّم الكامل بمحرّك الراديو.
 */
function radioReadCommand(string $command): ?string {
    if (!in_array($command, RADIO_READ_COMMANDS, true)) return null;
    return radioCommand($command);
}

/** هل محرّك الراديو شغّال ويستجيب؟ */
function radioEngineUp(): bool {
    return radioCommand('uptime') !== null;
}

/**
 * يشغّل مقطعاً فوراً — يدفعه في طابور الطلبات فيقطع الأغنية الحالية.
 * لا يقطع المذيع المباشر لأن أولويته أعلى في fallback.
 */
function radioPlayNow(string $absolutePath): bool {
    // نمنع تمرير مسار خارج مجلد الأغاني (حماية من حقن مسارات)
    $real = realpath($absolutePath);
    $base = realpath(RADIO_MUSIC_DIR);
    if ($real === false || $base === false || !str_starts_with($real, $base . '/')) {
        return false;
    }

    $res = radioCommand('requests.push ' . $real);
    // الردّ عند النجاح هو رقم الطلب (RID)
    return $res !== null && trim($res) !== '' && ctype_digit(trim(explode("\n", $res)[0]));
}

/**
 * يتخطّى المقطع الشغّال حالياً.
 * نستخدم أمر المخرج نفسه لأنه يتخطّى أياً كان المصدر الفعّال
 * (طلب مدفوع أو أغنية عادية)، بخلاف music.skip الذي يخصّ الأغاني فقط.
 */
function radioSkip(): bool {
    return radioCommand('/radio.skip') !== null;
}

/** اسم المقطع الشغّال حالياً كما يراه Icecast (فارغ عند التعذّر) */
function radioCurrentTitle(): string {
    $url = defined('ICECAST_STATUS_URL')
        ? ICECAST_STATUS_URL
        : 'http://127.0.0.1:8010/status-json.xsl';

    $ctx  = stream_context_create(['http' => ['timeout' => 2]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return '';

    $data = json_decode($body, true);
    $src  = $data['icestats']['source'] ?? [];
    if (isset($src['listenurl'])) $src = [$src];

    foreach ($src as $s) {
        $title = trim((string)($s['title'] ?? ''));
        if ($title !== '') return html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * هل يوجد مذيع متصل بالمايك الآن؟
 * Liquidsoap يردّ "no source client connected" عند عدم الاتصال، ولأن هذا النص
 * يحتوي عبارة الاتصال داخله نفحص النفي صراحةً بدل البحث عن العبارة.
 */
function radioLiveOnAir(): bool {
    $res = radioReadCommand('input.harbor.status');
    if ($res === null) return false;
    $res = trim($res);
    return $res !== '' && !str_starts_with($res, 'no ');
}

/**
 * يقرأ مدة ملف صوتي بالثواني عبر ffprobe، أو null إن تعذّر.
 * نستخدم ffprobe لأنه مثبّت أصلاً كجزء من ffmpeg الذي يعتمده Liquidsoap.
 */
function audioDuration(string $path): ?int {
    if (!is_readable($path) || !function_exists('shell_exec')) return null;

    $cmd = 'ffprobe -v error -show_entries format=duration -of csv=p=0 '
         . escapeshellarg($path) . ' 2>/dev/null';
    $out = shell_exec($cmd);

    if ($out === null || trim((string)$out) === '') return null;
    $secs = (float) trim($out);
    return $secs > 0 ? (int) round($secs) : null;
}

/** يصيغ المدة كـ م:ث للعرض */
function formatDuration(?int $seconds): string {
    if ($seconds === null || $seconds <= 0) return '—';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $s)
        : sprintf('%d:%02d', $m, $s);
}

/** يصيغ حجم الملف للعرض */
function formatSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' م.ب';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' ك.ب';
    return $bytes . ' بايت';
}

/**
 * يزامن المكتبة مع القرص: يسجّل أي ملف موجود وغير مسجّل بعد، ويعلّم
 * `status = 'missing'` لأي سجلّ اختفى ملفّه من القرص بدل حذفه. يمسح مستويين:
 *   - جذر مجلد الأغاني        → مصدره 'local'  (مرفوع من اللوحة أو SFTP)
 *   - المجلد الفرعي cloud/    → مصدره 'cloud'  (منسوخ من Google Drive)
 *
 * مزامنة Google Drive إلى cloud/ تفشل متقطّعاً فيختفي الملف ثم يعود بعد
 * دقائق؛ لو حذفنا السجلّ عند كل اختفاء لضاعت عضويته في أي بلاي ليست بلا
 * رجعة. لذلك: نعلّمه `missing` عند الاختفاء، ونعيده `ok` تلقائياً لو ظهر
 * على القرص مجدداً.
 *
 * يُخزَّن `filename` كمسار نسبي من مجلد الأغاني، فيميّز بين المصدرين
 * ويبقى صالحاً لبناء المسار الكامل بنفس الطريقة للاثنين.
 *
 * يرجّع عدد الملفات الجديدة التي أُضيفت.
 */
function syncTracksFromDisk(): int {
    if (!is_dir(RADIO_MUSIC_DIR)) return 0;

    $db    = db();
    $known = [];
    foreach ($db->query('SELECT filename, status FROM radio_tracks')->fetchAll() as $row) {
        $known[$row['filename']] = $row['status'];
    }

    $exts  = array_unique(array_values(RADIO_AUDIO_TYPES));
    $base  = rtrim(RADIO_MUSIC_DIR, '/');
    $added = 0;
    $seen  = [];

    // [مسار المجلد على القرص => المصدر ولاحقة المسار النسبي]
    $scanDirs = [
        $base              => ['local', ''],
        $base . '/' . RADIO_CLOUD_SUBDIR => ['cloud', RADIO_CLOUD_SUBDIR . '/'],
    ];

    $insert = $db->prepare(
        'INSERT INTO radio_tracks (filename, title, source, duration, filesize)
         VALUES (?, ?, ?, ?, ?)'
    );
    $restore = $db->prepare("UPDATE radio_tracks SET status = 'ok' WHERE filename = ?");
    $markMissing = $db->prepare("UPDATE radio_tracks SET status = 'missing' WHERE filename = ?");

    foreach ($scanDirs as $dir => [$source, $prefix]) {
        if (!is_dir($dir)) continue;

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (!is_file($path)) continue;
            if (!in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $exts, true)) continue;

            $relative = $prefix . $entry;
            $seen[$relative] = true;

            if (isset($known[$relative])) {
                // كان معلَّماً مفقوداً وظهر مجدداً — نعيده صالحاً
                if ($known[$relative] === 'missing') {
                    $restore->execute([$relative]);
                }
                continue;
            }

            $insert->execute([
                $relative,
                pathinfo($entry, PATHINFO_FILENAME),
                $source,
                audioDuration($path),
                filesize($path) ?: 0,
            ]);
            $added++;
        }
    }

    // ملفات اختفت من القرص — نعلّم سجلّاتها مفقودة بدل حذفها كي تبقى عضويتها
    // في البلاي ليست، وتعود صالحة تلقائياً لو رجع الملف بمزامنة لاحقة
    foreach ($known as $filename => $status) {
        if (!isset($seen[$filename]) && $status !== 'missing') {
            $markMissing->execute([$filename]);
        }
    }

    return $added;
}

/**
 * يرفع ملفاً صوتياً إلى مجلد الأغاني ويسجّله في قاعدة البيانات.
 * يرجّع مصفوفة [نجاح, رسالة].
 */
function uploadTrack(array $file, string $title = ''): array {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return [false, 'الملف أكبر من الحد المسموح (64 ميغابايت)'];
        }
        return [false, 'لم يصل أي ملف'];
    }

    // نفحص النوع الحقيقي للمحتوى لا ما يدّعيه المتصفح ولا امتداد الاسم
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
        }
    }
    if ($mime === null || !isset(RADIO_AUDIO_TYPES[$mime])) {
        return [false, 'نوع الملف غير مدعوم — المسموح: MP3, M4A, OGG, WAV'];
    }

    $ext = RADIO_AUDIO_TYPES[$mime];

    // اسم آمن مشتق من العنوان، مع لاحقة فريدة تمنع الاستبدال بالخطأ
    $base = $title !== '' ? $title : pathinfo($file['name'], PATHINFO_FILENAME);
    $safe = preg_replace('/[^\p{Arabic}\p{L}\p{N}\-_ ]/u', '', $base);
    $safe = trim(preg_replace('/\s+/u', ' ', (string) $safe));
    if ($safe === '') $safe = 'مقطع';
    $safe = mb_substr($safe, 0, 60);

    $filename = $safe . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
    $dest     = rtrim(RADIO_MUSIC_DIR, '/') . '/' . $filename;

    if (!is_dir(RADIO_MUSIC_DIR) || !is_writable(RADIO_MUSIC_DIR)) {
        return [false, 'مجلد الأغاني غير قابل للكتابة — راجع الصلاحيات على السيرفر'];
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, 'فشل حفظ الملف على السيرفر'];
    }

    // Liquidsoap يقرأ الملف كمستخدم آخر، فلا بد أن تكون المجموعة قادرة على القراءة
    @chmod($dest, 0664);

    $stmt = db()->prepare(
        'INSERT INTO radio_tracks (filename, title, duration, filesize) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $filename,
        $title !== '' ? $title : $safe,
        audioDuration($dest),
        filesize($dest) ?: 0,
    ]);

    return [true, 'تم رفع المقطع بنجاح'];
}

/**
 * يكتب محتوى نصياً إلى ملف باسم معيّن داخل مجلد البلاي ليست بشكل ذرّي:
 * يكتب في ملف مؤقّت بنفس المجلد ثم ينقله بـ rename() فوق الملف النهائي،
 * لأن أي كتابة مباشرة قد يقرأها المحرّك في اللحظة نفسها فيرى ملفاً نصف مكتوب.
 *
 * ترجّع false بهدوء دون رمي استثناء إن تعذّرت الكتابة (مجلد غير موجود على
 * بيئة التطوير مثلاً).
 */
function writeM3uFileAtomic(string $dir, string $name, string $content): bool {
    $final = $dir . '/' . $name;
    $tmp   = $dir . '/.' . $name . '-' . bin2hex(random_bytes(4)) . '.tmp';

    if (@file_put_contents($tmp, $content) === false) return false;
    if (!@rename($tmp, $final)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/** يبني محتوى m3u لبلاي ليست معيّنة من قاعدة البيانات، مستبعداً المقاطع المفقودة */
function buildPlaylistM3uContent(int $playlistId): string {
    $stmt = db()->prepare(
        "SELECT t.filename
           FROM radio_playlist_items i
           JOIN radio_tracks t ON t.id = i.track_id
          WHERE i.playlist_id = ? AND t.status = 'ok'
          ORDER BY i.sort_order, i.id"
    );
    $stmt->execute([$playlistId]);
    $filenames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $musicBase = rtrim(RADIO_MUSIC_DIR, '/');
    $lines = ['#EXTM3U'];
    foreach ($filenames as $filename) {
        $lines[] = $musicBase . '/' . $filename;
    }
    return implode("\n", $lines) . "\n";
}

/**
 * يكتب ملف .m3u لبلاي ليست معيّنة، مشتقّاً بالكامل من قاعدة البيانات — الملف
 * نتيجة لا مصدر. يستبعد أي مقطع `status = 'missing'` فلا يحوي الملف إلا
 * مسارات صالحة على القرص.
 *
 * إن كانت هذه البلاي ليست هي الافتراضية الحالية (`radio_config.default_playlist_id`)
 * يُكتب المحتوى نفسه أيضاً إلى default.m3u، وهو ما يقرأه Liquidsoap عند الإقلاع.
 *
 * ترجّع false بهدوء دون رمي استثناء إن تعذّرت الكتابة (مجلد غير موجود على
 * بيئة التطوير مثلاً) — لا نلمس قاعدة البيانات أصلاً في هذه الحالة.
 */
function writePlaylistM3u(int $playlistId): bool {
    $dir = rtrim(RADIO_PLAYLIST_DIR, '/');
    if (!is_dir($dir) || !is_writable($dir)) return false;

    $content = buildPlaylistM3uContent($playlistId);
    if (!writeM3uFileAtomic($dir, $playlistId . '.m3u', $content)) return false;

    $defaultId = (int) (getRadioConfig()['default_playlist_id'] ?? 0);
    if ($defaultId === $playlistId) {
        writeM3uFileAtomic($dir, 'default.m3u', $content);
    }

    return true;
}

/**
 * يكتب default.m3u من محتوى البلاي ليست الافتراضية الحالية. تُستدعى عند
 * تغيير *هوية* الافتراضية (لا عند تغيير محتواها — لذلك تتكفّل writePlaylistM3u).
 * ترجّع false بهدوء إن لم تكن هناك افتراضية معيّنة أو غاب مجلد الوجهة.
 */
function refreshDefaultM3u(): bool {
    $defaultId = (int) (getRadioConfig()['default_playlist_id'] ?? 0);
    if ($defaultId <= 0) return false;

    $dir = rtrim(RADIO_PLAYLIST_DIR, '/');
    if (!is_dir($dir) || !is_writable($dir)) return false;

    return writeM3uFileAtomic($dir, 'default.m3u', buildPlaylistM3uContent($defaultId));
}

/**
 * يبدّل البلاي ليست الفعّالة على المحرّك: يعيّن مسار ملفها على مصدر الموسيقى
 * ثم يفرض الانتقال فوراً بأمر تخطٍّ (التعيين وحده ينتظر نهاية المقطع الحالي).
 * يتحقّق أولاً أن البلاي ليست موجودة ومفعّلة وأن ملفها المشتقّ جاهز على القرص.
 */
function radioSwitchPlaylist(int $playlistId): bool {
    $stmt = db()->prepare('SELECT active FROM radio_playlists WHERE id = ?');
    $stmt->execute([$playlistId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['active'] !== 1) return false;

    $path = rtrim(RADIO_PLAYLIST_DIR, '/') . '/' . $playlistId . '.m3u';
    if (!is_readable($path)) return false;

    // المحرّك يردّ بسطر يبدأ بـ ERROR على أمر مرفوض، ولا يقطع الاتصال — فالردّ
    // غير الفارغ وحده ليس دليل نجاح. بدون هذا الفحص قد يفشل التعيين بصمت ثم
    // نسجّل الحالة الجديدة في المتغيّر، فيرى المشغّل تطابقاً كاذباً ولا يعيد المحاولة.
    if (!radioCommandOk(radioCommand('music.uri ' . $path))) return false;
    if (!radioCommandOk(radioCommand('music.skip')))         return false;

    radioCommand('var.set active_playlist = "' . $playlistId . '"');

    return true;
}

/** هل ردّ المحرّك يدلّ على نجاح؟ null = تعذّر الاتصال، وسطر ERROR = أمر مرفوض */
function radioCommandOk(?string $response): bool {
    if ($response === null) return false;
    return stripos(ltrim($response), 'ERROR') !== 0;
}

/**
 * معرّف البلاي ليست الفعّالة حالياً على المحرّك، أو null إن تعذّر الاتصال أو
 * لم تكن هناك حالة صالحة (محرّك أُعيد تشغيله للتوّ ولم يبدّل أحد البلاي ليست
 * بعد — القيمة الابتدائية "0" ليست معرّف بلاي ليست حقيقياً فتُعامل كـ null).
 */
function radioActivePlaylistId(): ?int {
    $res = radioCommand('var.get active_playlist');
    if ($res === null) return null;

    $clean = trim($res, " \t\n\r\0\x0B\"");
    if ($clean === '' || !ctype_digit($clean)) return null;

    $id = (int) $clean;
    return $id > 0 ? $id : null;
}

/**
 * الحالة الجارية الكاملة للبث — المصدر الوحيد للحقيقة الذي تستهلكه نقطة
 * "الآن يُشغَّل" العامة. ترجّع دائماً كل المفاتيح ولا ترمي استثناءً أبداً؛
 * عند تعذّر الاتصال بالمحرّك ترجّع نفس البنية بقيم فارغة/null.
 *
 * قيد معروف لا حلّ له من البيانات المتاحة: مقطع مكرّر عمداً داخل نفس البلاي
 * ليست (جينجل بين المقاطع مثلاً) يعطي دائماً موضع أول ظهور له، لا موضعه
 * الفعلي الجاري — لا رقم طلب (rid) ولا مؤشر تشغيل يميّز بين التكرارات.
 */
function radioNowPlaying(): array {
    $result = [
        'title'     => '',
        'remaining' => null,
        'position'  => null,
        'total'     => null,
        'playlist'  => '',
        'live'      => false,
    ];

    $result['live'] = radioLiveOnAir();

    // ---- العنوان ومسار الملف من /radio.metadata (الكتلة الأولى = الأحدث) ----
    $path = null;
    $meta = radioReadCommand('/radio.metadata');
    if ($meta !== null) {
        $blocks = preg_split('/\n\s*\n/', trim($meta));
        $fields = [];
        foreach (explode("\n", $blocks[0] ?? '') as $line) {
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $fields[trim($key)] = trim(trim($value), '"');
        }

        $path  = $fields['filename'] ?? $fields['initial_uri'] ?? null;
        $title = trim($fields['title'] ?? '');
        if ($title === '') {
            $title = trim($fields['artist'] ?? '');
        }
        if ($title === '' && $path !== null) {
            $title = pathinfo($path, PATHINFO_FILENAME);
        }
        $result['title'] = $title;
    }

    // ---- الوقت المتبقّي من /radio.remaining ----
    $remaining = radioReadCommand('/radio.remaining');
    if ($remaining !== null) {
        $remaining = trim($remaining);
        if (is_numeric($remaining) && (float) $remaining >= 0) {
            $result['remaining'] = (int) round((float) $remaining);
        }
    }

    // ---- الموقع داخل البلاي ليست الفعّالة، يُبنى في PHP بالكامل ----
    $playlistId = radioActivePlaylistId();
    if ($playlistId === null) return $result;

    // النقطة العامة تستدعي هذه الدالة، وعقدها أن تُرجع بنية كاملة دائماً بلا
    // انهيار. لمس قاعدة البيانات هنا يفتح مسار فشل جديداً — سيرفر لم تُطبَّق
    // عليه ترقية المخطّط بعد يرمي استثناءً على جدول غير موجود — فنبتلعه
    // ونرجّع ما جمعناه من المحرّك بدل أن نكسر صفحة الزائر.
    try {
        $stmt = db()->prepare('SELECT name FROM radio_playlists WHERE id = ?');
        $stmt->execute([$playlistId]);
        $name = $stmt->fetchColumn();
        if ($name === false) return $result;
        $result['playlist'] = $name;

        $itemsStmt = db()->prepare(
            "SELECT i.track_id
               FROM radio_playlist_items i
               JOIN radio_tracks t ON t.id = i.track_id
              WHERE i.playlist_id = ? AND t.status = 'ok'
              ORDER BY i.sort_order, i.id"
        );
        $itemsStmt->execute([$playlistId]);
        // نصرّح بالتحويل لأعداد: المقارنة الصارمة أدناه تفشل صامتةً لو رجّع
        // السائق نصوصاً، فيصير الموقع فارغاً بلا سبب ظاهر
        $trackIds = array_map('intval', $itemsStmt->fetchAll(PDO::FETCH_COLUMN));
        $result['total'] = count($trackIds);

        if ($path === null) return $result;

        // الربط بمسار الملف لا بمطابقة نصّ العنوان: filename عليه قيد تفرّد.
        $base = realpath(RADIO_MUSIC_DIR);
        $real = realpath($path);
        if ($base === false || $real === false || !str_starts_with($real, $base . '/')) {
            return $result;
        }
        $relative = substr($real, strlen($base) + 1);

        $trackStmt = db()->prepare('SELECT id FROM radio_tracks WHERE filename = ?');
        $trackStmt->execute([$relative]);
        $trackId = $trackStmt->fetchColumn();
        if ($trackId === false) return $result;

        $index = array_search((int) $trackId, $trackIds, true);
        if ($index !== false) {
            $result['position'] = $index + 1;
        }
    } catch (Throwable $e) {
        error_log('[radioNowPlaying] تعذّرت قراءة البلاي ليست: ' . $e->getMessage());
    }

    return $result;
}
