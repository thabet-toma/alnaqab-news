<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$radioConfig = getRadioConfig();
$images = json_decode($radioConfig['images'] ?? '[]', true);
$ticker = json_decode($radioConfig['ticker'] ?? '[]', true);
$ads = json_decode($radioConfig['ads'] ?? '[]', true);
$isAdmin = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($radioConfig['station_name']) ?> - <?= e(SITE_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Lalezar&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(SITE_URL) ?>/assets/css/radio.css">
</head>
<body>
    <div id="stars"></div>

    <nav class="radio-nav">
        <div class="nav-content">
            <a href="<?= e(SITE_URL) ?>" class="site-logo">
                <i class="fas fa-arrow-right"></i> عودة للموقع
            </a>
            <?php if ($isAdmin): ?>
            <button id="adminToggle" class="admin-btn" title="إعدادات الراديو">
                <i class="fas fa-cog"></i>
            </button>
            <?php endif; ?>
        </div>
    </nav>

    <div class="radio-container">
        <!-- Dunes Background -->
        <div class="dunes">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                <path d="M0,100 C30,80 50,100 100,60 L100,100 Z" fill="#0d1b2a" opacity="0.8"></path>
                <path d="M0,100 C40,90 70,60 100,80 L100,100 Z" fill="#16213e" opacity="0.6"></path>
            </svg>
        </div>

        <div class="radio-main">
            <div class="radio-header">
                <div class="on-air-badge">
                    <span class="pulse-dot"></span> مباشر
                </div>
                <div class="listeners-badge">
                    <i class="fas fa-headphones"></i> <span id="listenerCount">--</span>
                </div>
            </div>

            <div class="radio-body">
                <div class="artwork-stage">
                    <div class="image-slider" id="imageSlider">
                        <?php if (empty($images)): ?>
                            <div class="slide active" style="background-image: url('<?= e(SITE_URL) ?>/assets/images/default-radio.jpg')"></div>
                        <?php else: ?>
                            <?php foreach ($images as $index => $img): ?>
                                <div class="slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= e($img) ?>')"></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="equalizer" id="equalizer">
                        <span></span><span></span><span></span><span></span><span></span>
                    </div>
                </div>

                <div class="station-info">
                    <h1 class="station-name"><?= e($radioConfig['station_name']) ?></h1>
                    <p class="tagline"><?= e($radioConfig['tagline']) ?></p>
                    <p class="description"><?= nl2br(e($radioConfig['description'])) ?></p>
                </div>

                <div class="player-controls">
                    <div class="status-text" id="statusText">جاهز للبث</div>
                    
                    <button id="playBtn" class="play-btn">
                        <i class="fas fa-play"></i>
                    </button>

                    <div class="controls-row">
                        <div class="volume-control">
                            <i class="fas fa-volume-up" id="muteIcon"></i>
                            <input type="range" id="volumeSlider" min="0" max="1" step="0.01" value="1">
                        </div>

                        <div class="action-buttons">
                            <button id="sleepTimerBtn" title="مؤقت النوم" class="icon-btn">
                                <i class="fas fa-moon"></i>
                            </button>
                            <button id="shareBtn" title="مشاركة" class="icon-btn">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div id="sleepTimerDisplay" class="sleep-timer-display" style="display: none;">
                        <i class="fas fa-clock"></i> <span id="sleepTimeRemaining"></span>
                        <button id="cancelSleepTimer"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>

            <!-- Ticker -->
            <?php if (!empty($ticker)): ?>
            <div class="ticker-bar">
                <div class="ticker-label">عاجل</div>
                <div class="ticker-wrap">
                    <div class="ticker-move">
                        <?php foreach ($ticker as $msg): ?>
                            <span class="ticker-item"><?= e($msg) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ads -->
            <?php if (!empty($ads)): ?>
            <div class="radio-ads" id="radioAds">
                <?php foreach ($ads as $index => $ad): ?>
                    <a href="<?= e($ad['link']) ?>" target="_blank" class="ad-slide <?= $index === 0 ? 'active' : '' ?>">
                        <img src="<?= e($ad['image']) ?>" alt="Ad">
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Panel -->
    <?php if ($isAdmin): ?>
    <div id="adminPanel" class="admin-panel">
        <div class="admin-header">
            <h3>إعدادات الراديو</h3>
            <button id="closeAdmin"><i class="fas fa-times"></i></button>
        </div>
        <form id="radioSettingsForm">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= e(csrfToken()) ?>">
            <div class="form-group">
                <label>اسم المحطة</label>
                <input type="text" id="stationName" value="<?= e($radioConfig['station_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>الشعار اللفظي</label>
                <input type="text" id="tagline" value="<?= e($radioConfig['tagline']) ?>">
            </div>
            <div class="form-group">
                <label>رابط البث</label>
                <input type="url" id="streamUrl" value="<?= e($radioConfig['stream_url']) ?>" required dir="ltr">
            </div>
            <div class="form-group">
                <label>الوصف</label>
                <textarea id="description" rows="3"><?= e($radioConfig['description']) ?></textarea>
            </div>
            <!-- More settings could go here -->
            <button type="submit" class="save-btn"><i class="fas fa-save"></i> حفظ</button>
        </form>
    </div>
    <div id="adminOverlay" class="admin-overlay"></div>
    <?php endif; ?>

    <!-- Modal for Sleep Timer -->
    <div id="sleepTimerModal" class="modal">
        <div class="modal-content">
            <h3>مؤقت النوم</h3>
            <div class="timer-options">
                <button data-mins="15">15 دقيقة</button>
                <button data-mins="30">30 دقيقة</button>
                <button data-mins="60">60 دقيقة</button>
                <button data-mins="120">ساعتان</button>
            </div>
            <button id="closeSleepModal" class="btn-cancel">إلغاء</button>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <audio id="radioAudio" preload="none"></audio>

    <script>
        const RADIO_CONFIG = {
            streamUrl: <?= json_encode($radioConfig['stream_url']) ?>,
            stationName: <?= json_encode($radioConfig['station_name']) ?>,
            apiUrl: <?= json_encode(SITE_URL . '/admin/ajax/save-radio.php') ?>
        };
    </script>
    <script src="<?= e(SITE_URL) ?>/assets/js/radio.js"></script>
</body>
</html>
