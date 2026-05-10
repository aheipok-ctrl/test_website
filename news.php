<?php
/**
 * 代世集团官网 - 新闻和媒体首页 (智能图标增强版 + 公开素材预览)
 * 集成：无缝滚动轨道 + 媒体素材下载鉴权弹窗 + 封面图缺失自动补全 + 点击预览
 */
session_start();

// 1. 指向数据库配置
require_once __DIR__ . '/config/db.php';
$active_page = 'news';

// 2. 获取数据 (增加 try-catch 保护)
try {
    // 获取最新 10 条新闻
    $news_list = $pdo->query("SELECT * FROM news_articles WHERE status = 'active' ORDER BY publish_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // 【修改点1】：修改 SQL，直接过滤掉内部文件，仅拉取公开素材
    $media_list = $pdo->query("SELECT * FROM media_assets WHERE visibility != 'private' OR visibility IS NULL ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取 Banner 配置
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $news_list = []; $media_list = []; $settings = [];
}

$hero_video = $settings['news_hero_video'] ?? '';
$hero_image = $settings['news_hero_image'] ?? '';

// --- 辅助逻辑：根据标题生成默认图标 ---
function getNewsDefaultIcon($title) {
    if (mb_strpos($title, '合作') !== false || mb_strpos($title, '签约') !== false) return 'fa-handshake';
    if (mb_strpos($title, '技术') !== false || mb_strpos($title, '研发') !== false || mb_strpos($title, '系统') !== false) return 'fa-microchip';
    if (mb_strpos($title, '招聘') !== false || mb_strpos($title, '人才') !== false) return 'fa-user-plus';
    if (mb_strpos($title, '公告') !== false || mb_strpos($title, '通知') !== false) return 'fa-bullhorn';
    if (mb_strpos($title, '活动') !== false || mb_strpos($title, '团建') !== false) return 'fa-people-group';
    if (mb_strpos($title, '获奖') !== false || mb_strpos($title, '荣誉') !== false) return 'fa-trophy';
    return 'fa-newspaper'; // 默认图标
}

// 3. 引用统一页头
include_once __DIR__ . '/includes/header.php';
?>

<style>
    body { background: #FFF; margin: 0; padding: 0; overflow-x: hidden; font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif; }
    
    /* --- Hero 区域 --- */
    .news-hero { height: 50vh; min-height: 350px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #1A3C6C; }
    .bg-media { position: absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:1; opacity: 0.6; }
    .hero-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.3); z-index:2; }
    .hero-text { position:relative; z-index:3; text-align:center; color:#FFF; padding:0 20px; }
    .hero-text h1 { font-size: clamp(32px, 5vw, 48px); letter-spacing: 10px; font-weight: 900; margin:0; }

    /* --- 版块标题与更多入口 --- */
    .section-title { 
        text-align: center; margin: 80px auto 40px; padding: 0 20px; 
        max-width: 1200px; display: flex; align-items: flex-end; justify-content: space-between; 
    }
    .section-title h2 { color: #1A3C6C; font-size: clamp(24px, 4vw, 32px); font-weight: 900; margin: 0; }
    
    .btn-more-link {
        color: #64748B; font-size: 14px; font-weight: 800; text-decoration: none; 
        display: flex; align-items: center; gap: 6px; padding: 6px 12px; 
        border-radius: 50px; transition: 0.3s; background: #F8FAFC;
    }
    .btn-more-link:hover { background: #1A3C6C; color: #FFF; }
    
    /* 移动端标题居中处理 */
    @media (max-width: 600px) {
        .section-title { flex-direction: column; align-items: center; gap: 15px; }
    }

    /* --- 滚动轨道布局 --- */
    .track-wrapper { overflow: hidden; width: 100%; padding: 20px 0; position: relative; }
    .track-scroll { display: flex; gap: 30px; justify-content: flex-start; width: 100%; padding-left: 20px; -webkit-overflow-scrolling: touch; }
    
    @media (min-width: 1024px) {
        .track-scroll { justify-content: center; padding-left: 0; }
        .animate-scroll { width: max-content; justify-content: flex-start; animation: scrollTrack 60s linear infinite; }
        .animate-scroll:hover { animation-play-state: paused; }
    }
    @media (max-width: 1023px) {
        .track-wrapper { overflow-x: auto; scrollbar-width: none; }
        .track-wrapper::-webkit-scrollbar { display: none; }
        .animate-scroll { width: max-content; animation: none; }
    }
    @keyframes scrollTrack { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

    /* --- 新闻卡片 --- */
    .news-card { width: 380px; max-width: 85vw; background: #FFF; border-radius: 20px; border: 1px solid #F1F5F9; overflow: hidden; flex-shrink: 0; text-decoration: none; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.02); }
    .news-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
    
    .news-img-box { width: 100%; aspect-ratio: 16/9; overflow: hidden; background: #F8FAFC; position: relative; }
    .news-img-box img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }

    /* --- 智能占位图标样式 --- */
    .news-placeholder {
        width: 100%; height: 100%;
        background: linear-gradient(135deg, #1A3C6C 0%, #2A5298 100%);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        color: rgba(255,255,255,0.9); gap: 15px;
    }
    .news-placeholder i { font-size: 54px; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2)); }
    .news-placeholder span { font-size: 11px; font-weight: 800; letter-spacing: 2px; opacity: 0.6; text-transform: uppercase; }

    .news-card-body { padding: 25px 20px; white-space: normal; }
    .news-card-title { font-size: 17px; font-weight: 800; color: #1E293B; margin-bottom: 12px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .news-card-meta { color: #94A3B8; font-size: 13px; }

    /* --- 媒体素材卡片 --- */
    .media-box { width: 280px; max-width: 70vw; aspect-ratio: 3/2; border-radius: 15px; overflow: hidden; flex-shrink: 0; background: #F1F5F9; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
    .media-box img, .media-box video { width: 100%; height: 100%; object-fit: cover; }
    .media-play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #FFF; font-size: 40px; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,0.3); opacity: 0.8; transition: 0.3s; pointer-events: none; }
    
    .btn-download-overlay {
        position: absolute; right: 15px; bottom: 15px; width: 42px; height: 42px;
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(5px); color: #1A3C6C;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 18px; z-index: 10; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0; transform: translateY(10px); transition: 0.3s ease; cursor: pointer;
    }
    .media-box:hover .btn-download-overlay { opacity: 1; transform: translateY(0); }
    @media (max-width: 1023px) { .btn-download-overlay { opacity: 1; transform: translateY(0); } }

    /* --- 鉴权弹窗 --- */
    .auth-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px);
        z-index: 9999; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: 0.3s;
    }
    .auth-modal {
        background: #FFF; width: 90%; max-width: 420px; border-radius: 24px;
        padding: 35px; box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        transform: translateY(30px); transition: 0.3s ease; position: relative;
    }
    .auth-modal.active { transform: translateY(0); }
    .close-modal { position: absolute; top: 20px; right: 20px; font-size: 22px; color: #94A3B8; cursor: pointer; }
    .auth-title { margin: 0 0 10px; font-size: 22px; color: #1E293B; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    .auth-desc { color: #64748B; font-size: 14px; margin-bottom: 25px; line-height: 1.6; }
    .auth-input { width: 100%; padding: 14px 18px; border: 2px solid #F1F5F9; border-radius: 12px; font-size: 15px; box-sizing: border-box; outline: none; transition: 0.3s; background: #F8FAFC; margin-bottom: 15px; }
    .auth-input:focus { border-color: #1A3C6C; background: #FFF; box-shadow: 0 0 0 4px rgba(26,60,108,0.05); }
    .btn-auth-submit { width: 100%; padding: 16px; background: #1A3C6C; color: #FFF; border: none; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.2s; margin-top: 10px; }

    /* --- 【新增】：预览弹窗 --- */
    .preview-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(5px);
        z-index: 10000; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: 0.3s;
    }
    .preview-content {
        position: relative; max-width: 90vw; max-height: 90vh;
        display: flex; align-items: center; justify-content: center;
    }
    .preview-content img, .preview-content video {
        max-width: 100%; max-height: 90vh; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); object-fit: contain;
    }
    .close-preview {
        position: absolute; top: -40px; right: 0; font-size: 30px; color: #FFF; cursor: pointer; transition: 0.3s; z-index: 10001;
    }
    .close-preview:hover { color: #EF4444; }
</style>

<section class="news-hero">
    <?php if ($hero_video): ?>
        <video class="bg-media" autoplay loop muted playsinline><source src="<?= htmlspecialchars($hero_video) ?>" type="video/mp4"></video>
    <?php elseif ($hero_image): ?>
        <img src="<?= htmlspecialchars($hero_image) ?>" class="bg-media">
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>新闻和媒体</h1>
        <p style="font-size: 16px; opacity: 0.8; letter-spacing: 2px; margin-top:20px; font-weight:300;">LATEST NEWS & CORPORATE DYNAMICS</p>
    </div>
</section>

<div class="section-title">
    <h2>最新动态 / NEWS</h2>
    <a href="news_all.php" class="btn-more-link">更多新闻动态 <i class="fa-solid fa-arrow-right-long"></i></a>
</div>
<div class="track-wrapper">
    <div class="track-scroll <?php echo (count($news_list) > 3) ? 'animate-scroll' : ''; ?>">
        <?php 
        $news_display = (count($news_list) > 3) ? array_merge($news_list, $news_list) : $news_list;
        foreach($news_display as $n): 
        ?>
        <a href="news_detail.php?id=<?= $n['id'] ?>" class="news-card">
            <div class="news-img-box">
                <?php if(!empty($n['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($n['cover_image']) ?>" alt="Cover">
                <?php else: ?>
                    <div class="news-placeholder">
                        <i class="fa-solid <?= getNewsDefaultIcon($n['title']) ?>"></i>
                        <span>Daishi Group News</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="news-card-body">
                <div class="news-card-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="news-card-meta"><i class="fa-regular fa-clock"></i> <?= date('Y-m-d', strtotime($n['publish_date'])) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="section-title">
    <h2>媒体素材 / MEDIA</h2>
    <a href="media_all.php" class="btn-more-link">更多媒体资源 <i class="fa-solid fa-arrow-right-long"></i></a>
</div>
<div class="track-wrapper" style="margin-bottom: 100px;">
    <div class="track-scroll <?php echo (count($media_list) > 5) ? 'animate-scroll' : ''; ?>" style="animation-duration: 45s;">
        <?php 
        $media_display = (count($media_list) > 5) ? array_merge($media_list, $media_list) : $media_list;
        foreach($media_display as $m): 
            $is_private = isset($m['visibility']) && $m['visibility'] === 'private'; // 虽然已被过滤，保留增加鲁棒性
            $media_type = (strpos($m['file_type'] ?? '', 'image') !== false) ? 'image' : ((strpos($m['file_type'] ?? '', 'video') !== false) ? 'video' : 'doc');
        ?>
        <div class="media-box">
            <?php if($is_private): ?>
                <div style="width:100%; height:100%; background: linear-gradient(135deg, #1A3C6C 0%, #2A5298 100%); display:flex; flex-direction:column; align-items:center; justify-content:center; color:rgba(255,255,255,0.9);">
                    <i class="fa-solid fa-file-shield" style="font-size:40px; margin-bottom:10px; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));"></i>
                    <span style="font-size:11px; font-weight:800; letter-spacing:1px;">内部保密素材</span>
                </div>
            <?php else: ?>
                <div style="width:100%; height:100%; cursor:zoom-in;" onclick="openPreview('<?= htmlspecialchars($m['file_path']) ?>', '<?= $media_type ?>')">
                    <?php if($media_type === 'image'): ?>
                        <img src="<?= htmlspecialchars($m['file_path']) ?>">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-play media-play-icon"></i>
                        <video src="<?= htmlspecialchars($m['file_path']) ?>" muted></video>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if($is_private): ?>
                <div class="btn-download-overlay" onclick="openAuthModal(<?= $m['id'] ?>, '<?= htmlspecialchars($m['file_name']) ?>'); event.stopPropagation();"><i class="fa-solid fa-lock"></i></div>
            <?php else: ?>
                <a href="<?= htmlspecialchars($m['file_path']) ?>" download="<?= htmlspecialchars($m['file_name']) ?>" class="btn-download-overlay" onclick="event.stopPropagation();"><i class="fa-solid fa-download"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="auth-modal-overlay" id="authOverlay">
    <div class="auth-modal" id="authBox">
        <i class="fa-solid fa-xmark close-modal" onclick="closeAuthModal()"></i>
        <h3 class="auth-title"><i class="fa-solid fa-shield-halved" style="color:#1A3C6C"></i> 员工身份验证</h3>
        <p class="auth-desc">文件 <strong id="authFileName"></strong> 属于企业内部资产，请验证您的员工账号。</p>
        <form id="authForm" action="download.php" method="POST">
            <input type="hidden" name="asset_id" id="authAssetId" value="">
            <input type="text" name="emp_account" class="auth-input" placeholder="员工账号" required>
            <input type="password" name="emp_password" class="auth-input" placeholder="登录密码" required>
            <button type="submit" class="btn-auth-submit">验证并下载</button>
        </form>
    </div>
</div>

<div class="preview-overlay" id="previewOverlay">
    <div class="preview-content" onclick="event.stopPropagation()">
        <i class="fa-solid fa-xmark close-preview" onclick="closePreview()"></i>
        <div id="previewContainer"></div>
    </div>
</div>

<script>
    // --- 鉴权弹窗逻辑 ---
    const overlay = document.getElementById('authOverlay');
    const authBox = document.getElementById('authBox');
    function openAuthModal(id, fileName) {
        document.getElementById('authAssetId').value = id;
        document.getElementById('authFileName').innerText = fileName;
        overlay.style.display = 'flex';
        setTimeout(() => { overlay.style.opacity = '1'; authBox.classList.add('active'); }, 10);
    }
    function closeAuthModal() {
        overlay.style.opacity = '0'; authBox.classList.remove('active');
        setTimeout(() => { overlay.style.display = 'none'; }, 300);
    }
    overlay.addEventListener('click', function(e) { if(e.target === overlay) closeAuthModal(); });

    // --- 【新增】：预览弹窗逻辑 ---
    const previewOverlay = document.getElementById('previewOverlay');
    const previewContainer = document.getElementById('previewContainer');
    
    function openPreview(src, type) {
        previewContainer.innerHTML = '';
        if (type === 'image') {
            previewContainer.innerHTML = `<img src="${src}">`;
        } else if (type === 'video') {
            previewContainer.innerHTML = `<video src="${src}" controls autoplay style="outline:none;"></video>`;
        } else {
            // 如果是文档，直接新标签页打开
            window.open(src, '_blank');
            return;
        }
        previewOverlay.style.display = 'flex';
        setTimeout(() => { previewOverlay.style.opacity = '1'; }, 10);
    }
    
    function closePreview() {
        previewOverlay.style.opacity = '0';
        setTimeout(() => { 
            previewOverlay.style.display = 'none'; 
            previewContainer.innerHTML = ''; // 清空内容（自动停止视频播放）
        }, 300);
    }
    previewOverlay.addEventListener('click', function(e) { if(e.target === previewOverlay) closePreview(); });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>