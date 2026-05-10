<?php
/**
 * 代世集团官网首页 - 业务联动全量版
 * 修复：1. 移除 can() 函数报错；2. 核心业务模块同步后台 link_url 指向。
 */
session_start();
require_once __DIR__ . '/config/db.php';

// 1. 获取全站基础设置 (用于 Hero 区域)
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { $settings = []; }

$hero_title = $settings['hero_title'] ?? '代 世 集 团';
$hero_subtitle = $settings['hero_subtitle'] ?? '智 领 未 来 · 重 塑 美 学';
$hero_video = $settings['hero_video'] ?? '';
$hero_image = $settings['hero_image'] ?? '';

// 2. 获取集团业务数据 (用于核心业务板块)
try {
    $biz_list = $pdo->query("SELECT * FROM business_units ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $biz_list = []; }
$biz_count = count($biz_list);

// 3. 获取发展历程数据
try {
    $history_list = $pdo->query("SELECT * FROM development_history ORDER BY year DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $history_list = []; }

include_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alibaba-puhuiti@2.0.0/index.min.css">

<style>
    body, html { margin: 0; padding: 0; background: #FFF; color: #1A1A1A; overflow-x: hidden; scroll-behavior: smooth; font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif; }
    
    /* Hero 区域：支持视频背景 */
    .hero-section { position: relative; width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; overflow: hidden; background: #000; }
    .bg-media { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; }
    .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.5) 100%); z-index: 2; }
    .hero-content { position: relative; z-index: 3; width: 100%; padding: 0 20px; }
    .hero-content h1 { font-size: clamp(32px, 8vw, 110px); font-weight: 900; color: #FFF; margin: 0; letter-spacing: 0.1em; text-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .hero-content .divider { width: 60px; height: 3px; background: #FFF; margin: 25px auto; border-radius: 2px; }
    .hero-content p { font-size: clamp(12px, 3vw, 20px); color: rgba(255,255,255,0.95); font-weight: 600; letter-spacing: 5px; text-transform: uppercase; }

    /* 通用板块布局 */
    .section { padding: 100px 5%; text-align: center; max-width: 1400px; margin: 0 auto; box-sizing: border-box; }
    .section-title { font-size: 36px; font-weight: 900; color: #000; margin-bottom: 60px; position: relative; letter-spacing: 2px; }
    .section-title::after { content: ""; position: absolute; bottom: -15px; left: 50%; transform: translateX(-50%); width: 30px; height: 4px; background: #000; }

    /* 业务板块：自适应网格 */
    .biz-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; justify-content: center; }
    
    /* 针对不同数量业务的对称类 */
    .biz-count-4 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
    @media (min-width: 1200px) { .biz-count-4 { grid-template-columns: repeat(4, 1fr); } }

    .biz-card { 
        background: #FFF; border-radius: 32px; padding: 60px 40px; 
        border: 1px solid #F1F5F9; transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
        text-decoration: none; display: flex; flex-direction: column; align-items: center;
        height: 100%; box-sizing: border-box;
    }
    .biz-card:hover { transform: translateY(-12px); box-shadow: 0 30px 60px rgba(0,0,0,0.08); border-color: #000; }
    .biz-icon { font-size: 48px; color: #1A1A1A; margin-bottom: 30px; }
    .biz-card h3 { font-size: 24px; margin-bottom: 18px; color: #000; font-weight: 900; }
    .biz-card p { font-size: 15px; color: #64748B; line-height: 1.8; margin: 0; text-align: center; }

    /* 发展历程：横向拖拽 */
    .timeline-container { width: 100%; overflow-x: auto; padding-bottom: 40px; cursor: grab; scrollbar-width: none; }
    .timeline-container::-webkit-scrollbar { display: none; }
    .timeline-wrapper { display: flex; padding: 20px 5%; min-width: max-content; gap: 0; position: relative; }
    .timeline-wrapper::before { content: ""; position: absolute; top: 60px; left: 0; right: 0; height: 2px; background: #E2E8F0; z-index: 1; }
    .timeline-item { width: 320px; position: relative; padding-top: 80px; text-align: left; }
    .timeline-dot { position: absolute; top: 54px; left: 0; width: 12px; height: 12px; background: #FFF; border: 3px solid #000; border-radius: 50%; z-index: 2; }
    .timeline-year { font-size: 30px; font-weight: 900; color: #000; margin-bottom: 12px; display: block; }
    .timeline-content { background: #F8FAFC; padding: 25px; border-radius: 20px; border: 1px solid #F1F5F9; margin-right: 30px; }
</style>

<section class="hero-section">
    <?php if (!empty($hero_video)): ?>
        <video class="bg-media" autoplay loop muted playsinline poster="<?= $hero_image ?>">
            <source src="<?= htmlspecialchars($hero_video) ?>" type="video/mp4">
        </video>
    <?php elseif (!empty($hero_image)): ?>
        <img src="<?= htmlspecialchars($hero_image) ?>" class="bg-media" alt="Background">
    <?php endif; ?>
    <div class="overlay"></div>
    <div class="hero-content">
        <h1><?= htmlspecialchars($hero_title) ?></h1>
        <div class="divider"></div>
        <p><?= htmlspecialchars($hero_subtitle) ?></p>
    </div>
</section>

<section class="section" id="business">
    <h2 class="section-title">核心业务</h2>
    <div class="biz-grid <?= 'biz-count-' . $biz_count ?>">
        <?php if (empty($biz_list)): ?>
            <div style="grid-column: 1/-1; color: #94A3B8; padding: 50px;">业务数据同步中...</div>
        <?php else: ?>
            <?php foreach ($biz_list as $biz): 
                // 获取后台设置的跳转链接
                $target_url = !empty($biz['link_url']) ? htmlspecialchars($biz['link_url']) : 'javascript:void(0);';
            ?>
                <a href="<?= $target_url ?>" class="biz-card">
                    <div class="biz-icon">
                        <i class="fa-solid <?= htmlspecialchars($biz['icon_class'] ?? 'fa-cube') ?>"></i>
                    </div>
                    <h3><?= htmlspecialchars($biz['name']) ?></h3>
                    <p><?= htmlspecialchars($biz['brief']) ?></p>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="section" style="background: #FAFBFC; max-width: 100%;">
    <h2 class="section-title">发展历程</h2>
    <div class="timeline-container" id="timeline">
        <div class="timeline-wrapper">
            <?php if (empty($history_list)): ?>
                <div style="width: 100vw; text-align: center; color: #94A3B8; padding: 50px;">历程数据正在完善中...</div>
            <?php else: ?>
                <?php foreach ($history_list as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <span class="timeline-year"><?= htmlspecialchars($item['year']) ?></span>
                    <div class="timeline-content">
                        <h4 style="margin:0 0 10px;"><?= htmlspecialchars($item['title']) ?></h4>
                        <p style="margin:0; font-size:14px; color:#64748B;"><?= htmlspecialchars($item['content']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    // 时间轴拖拽逻辑
    const slider = document.getElementById('timeline');
    let isDown = false, startX, scrollLeft;
    slider.addEventListener('mousedown', (e) => { isDown = true; slider.style.cursor = 'grabbing'; startX = e.pageX - slider.offsetLeft; scrollLeft = slider.scrollLeft; });
    slider.addEventListener('mouseleave', () => { isDown = false; slider.style.cursor = 'grab'; });
    slider.addEventListener('mouseup', () => { isDown = false; slider.style.cursor = 'grab'; });
    slider.addEventListener('mousemove', (e) => { if (!isDown) return; e.preventDefault(); const x = e.pageX - slider.offsetLeft; const walk = (x - startX) * 2; slider.scrollLeft = scrollLeft - walk; });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>