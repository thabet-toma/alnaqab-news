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
    $res = radioCommand('input.harbor.status');
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
 * يزامن المكتبة مع القرص: يسجّل أي ملف موجود وغير مسجّل بعد، ويشطب سجلّ
 * أي ملف اختفى. يمسح مستويين:
 *   - جذر مجلد الأغاني        → مصدره 'local'  (مرفوع من اللوحة أو SFTP)
 *   - المجلد الفرعي cloud/    → مصدره 'cloud'  (منسوخ من Google Drive)
 *
 * يُخزَّن `filename` كمسار نسبي من مجلد الأغاني، فيميّز بين المصدرين
 * ويبقى صالحاً لبناء المسار الكامل بنفس الطريقة للاثنين.
 *
 * يرجّع عدد الملفات الجديدة التي أُضيفت.
 */
function syncTracksFromDisk(): int {
    if (!is_dir(RADIO_MUSIC_DIR)) return 0;

    $db    = db();
    $known = array_flip(
        $db->query('SELECT filename FROM radio_tracks')->fetchAll(PDO::FETCH_COLUMN)
    );

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

    foreach ($scanDirs as $dir => [$source, $prefix]) {
        if (!is_dir($dir)) continue;

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (!is_file($path)) continue;
            if (!in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $exts, true)) continue;

            $relative = $prefix . $entry;
            $seen[$relative] = true;
            if (isset($known[$relative])) continue;

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

    // ملفات اختفت من القرص — نزيل سجلّاتها كي لا تظهر بالمكتبة ولا بالجدولة
    foreach (array_keys($known) as $filename) {
        if (!isset($seen[$filename])) {
            $db->prepare('DELETE FROM radio_tracks WHERE filename = ?')->execute([$filename]);
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
