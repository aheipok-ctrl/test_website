<?php
/**
 * 代世集团 - 核心业务版图 (全动态拉取版)
 * 逻辑：保持原有交互布局，动态循环拉取后台数据库中的所有业务项目
 * 排序：读取后台设置的 sort_order 字段
 */
session_start();

// 1. 核心连接
require_once __DIR__ . '/config/db.php';
$active_page = 'business'; 

// 2. 获取配置数据与动态业务项目
try {
    // 获取全局设置（如 Hero 视频/图片）
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 【核心修改】获取业务项，优先按 sort_order 升序排列，次要按 id 排列
    $stmt = $pdo->query("SELECT * FROM business_units ORDER BY sort_order ASC, id ASC");
    $all_businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
    $all_businesses = [];
}

/**
 * 路径处理函数
 */
function fix_biz_link($url) {
    if (empty($url)) return '#';
    // 去除领先斜杠，确保在子目录下也能正确跳转
    return htmlspecialchars(ltrim($url, '/'));
}

/**
 * 辅助函数：根据后台数据渲染媒体素材
 */
function render_dynamic_media($url, $icon = 'fa-briefcase') {
    if (empty($url)) {
        return "<div class='biz-icon-box' style='background:#F0F7FF; color:#1A3C6C;'><i class='fa-solid {$icon}'></i></div>";
    }
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm'])) {
        return "<div class='biz-media-box'><video autoplay loop muted playsinline><source src='{$url}' type='video/{$ext}'></video></div>";
    } else {
        return "<div class='biz-media-box'><img src='{$url}' alt='业务配图'></div>";
    }
}

// 3. 引用统一页头
include_once __DIR__ . '/includes/header.php';
?>

<style>
    body { background: #F8FAFC; color: #334155; margin: 0; padding: 0; overflow-x: hidden; font-family: "Alibaba PuHuiTi 2.0", sans-serif; }
    
    .hero-biz { 
        position: relative; height: 50vh; min-height: 400px; 
        display: flex; align-items: center; justify-content: center; 
        text-align: center; overflow: hidden; background: #1A3C6C; 
    }
    .bg-media { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; opacity: 0.6; }
    .hero-content { position: relative; z-index: 3; color: #FFF; padding: 0 20px; }
    
    .biz-container { max-width: 1200px; margin: -40px auto 80px; padding: 0 20px; position: relative; z-index: 10; }
    
    .biz-section { 
        display: flex; align-items: center; gap: 60px; margin-bottom: 40px; 
        background: #FFF; padding: 40px; border-radius: 24px; border: 1px solid #E2E8F0; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-decoration: none; color: inherit; 
        transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
    }
    .biz-section:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(26,60,108,0.1); border-color: #1A3C6C; }
    
    /* 桌面端奇偶交错布局：根据循环后的物理顺序自动反转 */
    @media (min-width: 992px) { 
        .biz-section:nth-child(even) { flex-direction: row-reverse; } 
    }
    
    .biz-icon-box, .biz-media-box { flex: 0 0 350px; height: 350px; border-radius: 20px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
    .biz-icon-box { font-size: 80px; }
    .biz-media-box video, .biz-media-box img { width: 100%; height: 100%; object-fit: cover; }
    
    .biz-content { flex: 1; }
    .biz-content h2 { font-size: clamp(24px, 4vw, 34px); color: #1A3C6C; margin: 0 0 15px; font-weight: 800; display: flex; justify-content: space-between; align-items: center; }
    .biz-content p { font-size: 16px; color: #64748B; line-height: 1.8; margin-bottom: 25px; text-align: justify; }
    
    .feature-tags { display: flex; flex-wrap: wrap; gap: 8px; }
    .feature-tags span { display: inline-block; background: #F1F5F9; border: 1px solid #E2E8F0; color: #475569; padding: 6px 15px; border-radius: 50px; font-size: 13px; font-weight: 700; }

    @media (max-width: 991px) {
        .biz-section { flex-direction: column; gap: 30px; padding: 25px; }
        .biz-icon-box, .biz-media-box { flex: none; width: 100%; height: 280px; }
        .biz-container { margin-top: -30px; }
    }
</style>

<section class="hero-biz">
    <?php if (!empty($settings['biz_hero_video'])): ?>
        <video class="bg-media" autoplay loop muted playsinline><source src="<?= htmlspecialchars($settings['biz_hero_video']) ?>" type="video/mp4"></video>
    <?php elseif (!empty($settings['biz_hero_image'])): ?>
        <img src="<?= htmlspecialchars($settings['biz_hero_image']) ?>" class="bg-media">
    <?php endif; ?>
    <div class="hero-content">
        <h1 style="font-size:clamp(28px, 6vw, 54px); margin:0; font-weight:900; letter-spacing:2px; text-shadow: 0 4px 10px rgba(0,0,0,0.3);">核心业务版图</h1>
        <p style="margin-top:15px; font-size:clamp(14px, 3vw, 18px); opacity: 0.9;">重塑商业美学 · 驱动产业升级</p>
    </div>
</section>

<div class="biz-container">
    
    <?php if (!empty($all_businesses)): ?>
        <?php foreach ($all_businesses as $biz): ?>
            <a href="<?= fix_biz_link($biz['link_url']) ?>" class="biz-section">
                <?= render_dynamic_media($biz['media_path'] ?? '', $biz['icon_class'] ?? 'fa-briefcase') ?>
                
                <div class="biz-content">
                    <h2>
                        <?= htmlspecialchars($biz['name']) ?> 
                        <i class="fa-solid fa-arrow-right" style="font-size: 0.6em;"></i>
                    </h2>
                    
                    <p><?= nl2br(htmlspecialchars($biz['brief'])) ?></p>
                    
                    <div class="feature-tags">
                        <?php 
                        // 逻辑：如果存在 tags 字段则展示，否则展示默认标签
                        if (!empty($biz['tags'])) {
                            $tags = explode(',', $biz['tags']);
                            foreach ($tags as $tag) {
                                echo "<span>" . htmlspecialchars(trim($tag)) . "</span>";
                            }
                        } else {
                            // 默认 fallback 标签（可根据业务名判断或留空）
                            echo "<span>代世集团旗下</span><span>核心成员</span>";
                        }
                        ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 100px 0; color: #94a3b8;">
            <i class="fa-solid fa-folder-open" style="font-size: 48px; margin-bottom: 20px; display: block;"></i>
            暂无业务版图数据
        </div>
    <?php endif; ?>
    
</div>

<?php 
include_once __DIR__ . '/includes/footer.php'; 
?>