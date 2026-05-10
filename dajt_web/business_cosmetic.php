<?php
/**
 * 代世集团官网 - DK化妆品业务介绍页 (纯净白金风格 - 路径修正版)
 */
session_start();

// 1. 指向数据库配置 (已修正路径)
require_once dirname(__DIR__) . '/config/db.php';
$active_page = 'business'; // 保持导航栏高亮

// 2. 获取后台配置的素材 (如果后台没传，使用默认占位)
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $settings = [];
}
$dk_asset = $settings['biz_dk_asset'] ?? 'https://images.unsplash.com/photo-1596462502278-27bf85033e5a?auto=format&fit=crop&w=1920&q=80';

// 3. 引入统一下拉页头 (核心修复：指向上一级目录的 includes)
include_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    /* --- 纯净白色主题基础设定 --- */
    body { background: #FFFFFF; color: #333333; margin: 0; padding: 0; font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif; overflow-x: hidden; }
    
    /* --- 首屏 Hero 区域 --- */
    .cosmetic-hero { 
        height: 60vh; min-height: 400px; 
        display: flex; flex-direction: column; align-items: center; justify-content: center; 
        background: #FAFAFA; position: relative; 
        text-align: center; padding: 0 20px;
    }
    .cosmetic-hero h1 { 
        font-size: clamp(36px, 5vw, 54px); font-weight: 300; letter-spacing: 8px; 
        color: #111; margin-bottom: 15px; 
    }
    .cosmetic-hero p { 
        font-size: 14px; letter-spacing: 4px; color: #888; text-transform: uppercase; 
    }
    .hero-line { width: 60px; height: 1px; background: #D4AF37; margin: 30px auto; } /* 香槟金点缀 */

    /* --- 内容容器 --- */
    .container { max-width: 1200px; margin: 0 auto; padding: 80px 20px; }
    .section-title { text-align: center; margin-bottom: 60px; }
    .section-title h2 { font-size: 28px; font-weight: 300; letter-spacing: 4px; color: #222; margin: 0 0 10px; }
    .section-title span { font-size: 12px; color: #999; letter-spacing: 2px; text-transform: uppercase; }

    /* --- 图文介绍模块 --- */
    .intro-module { display: flex; flex-wrap: wrap; align-items: center; gap: 50px; margin-bottom: 100px; }
    .intro-text { flex: 1; min-width: 300px; }
    .intro-text h3 { font-size: 24px; font-weight: 400; margin-bottom: 20px; color: #111; }
    .intro-text p { font-size: 15px; line-height: 2; color: #666; margin-bottom: 15px; text-align: justify; }
    .intro-image { flex: 1; min-width: 300px; position: relative; }
    .intro-image img { width: 100%; border-radius: 4px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
    /* 装饰性背景框 */
    .intro-image::before { content: ""; position: absolute; top: -20px; left: -20px; right: 20px; bottom: 20px; border: 1px solid #EAEAEA; z-index: -1; }

    /* --- 核心产品图片画廊模块 --- */
    .gallery-module { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 100px; }
    .gallery-item { position: relative; overflow: hidden; aspect-ratio: 3/4; cursor: pointer; }
    .gallery-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
    .gallery-item:hover img { transform: scale(1.05); }
    .gallery-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.4s ease; }
    .gallery-item:hover .gallery-overlay { opacity: 1; }
    .gallery-overlay h4 { color: #111; font-weight: 300; letter-spacing: 2px; font-size: 18px; }

    /* --- 视频模块 --- */
    .video-module { margin-bottom: 100px; background: #FAFAFA; padding: 60px 20px; border-radius: 8px; }
    .video-container { max-width: 1000px; margin: 0 auto; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 30px 60px rgba(0,0,0,0.08); background: #000; aspect-ratio: 16/9; }
    .video-container video { width: 100%; height: 100%; object-fit: cover; display: block; }
    
    /* 视频状态提示 (播放中自动隐藏) */
    .play-status { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.9); padding: 5px 15px; border-radius: 50px; font-size: 12px; font-weight: bold; color: #333; letter-spacing: 1px; transition: 0.3s; opacity: 1; pointer-events: none; }
    .is-playing .play-status { opacity: 0; }
</style>

<section class="cosmetic-hero">
    <h1>DK COSMETICS</h1>
    <div class="hero-line"></div>
    <p>唤醒源自肌底的纯粹之美</p>
</section>

<div class="container">

    <div class="intro-module">
        <div class="intro-image">
            <img src="<?= htmlspecialchars($dk_asset) ?>" alt="DK化妆品">
        </div>
        <div class="intro-text">
            <h3>科技与自然的美学融合</h3>
            <p>代世集团旗下 DK 化妆品品牌，致力于探索自然精粹与现代护肤科技的完美平衡。我们坚信，真正的美丽源于健康的肌肤屏障。通过严苛的原料甄选和尖端的萃取工艺，为亚洲女性量身定制高效、温和的护肤方案。</p>
            <p>从研发实验室到生产线，DK 始终秉持“纯净、卓效、可持续”的理念，让每一次护肤都成为一场身心愉悦的奢宠体验。</p>
        </div>
    </div>

    <div class="section-title">
        <h2>CORE SERIES</h2>
        <span>核心甄选系列</span>
    </div>
    <div class="gallery-module">
        <div class="gallery-item">
            <img src="https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=800&q=80" alt="极光精华">
            <div class="gallery-overlay"><h4>凝时极光精华</h4></div>
        </div>
        <div class="gallery-item">
            <img src="https://images.unsplash.com/photo-1599305090598-fe179d501227?auto=format&fit=crop&w=800&q=80" alt="赋活面霜">
            <div class="gallery-overlay"><h4>肌底赋活面霜</h4></div>
        </div>
        <div class="gallery-item">
            <img src="https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=800&q=80" alt="沁透水水">
            <div class="gallery-overlay"><h4>晨露沁透爽肤水</h4></div>
        </div>
    </div>

    <div class="section-title">
        <h2>BRAND VISION</h2>
        <span>品牌视觉大片</span>
    </div>
    <div class="video-module">
        <div class="video-container" id="videoContainer">
            <div class="play-status"><i class="fa-solid fa-play"></i> 滚动播放</div>
            <video id="cosmeticVideo" muted loop playsinline src="https://www.w3schools.com/html/mov_bbb.mp4" poster="https://images.unsplash.com/photo-1616683693504-3ea7e9ad6fec?auto=format&fit=crop&w=1000&q=80"></video>
        </div>
    </div>

</div>

<?php 
// 核心修复：引入上一级目录的 footer
include_once dirname(__DIR__) . '/includes/footer.php'; 
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const video = document.getElementById("cosmeticVideo");
    const videoContainer = document.getElementById("videoContainer");

    if (video && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    video.play().then(() => {
                        videoContainer.classList.add('is-playing');
                    }).catch((e) => {
                        console.log("Autoplay prevented:", e);
                    });
                } else {
                    video.pause();
                    videoContainer.classList.remove('is-playing');
                }
            });
        }, {
            threshold: 0.66
        });

        observer.observe(video);
    }
});
</script>