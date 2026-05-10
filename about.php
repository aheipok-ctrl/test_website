<?php
/**
 * 代世集团官网 - 关于我们 (动态历程全适配版)
 * 整合：动态拉取 development_history 表数据，新增月份展示
 */
session_start();

// 1. 指向新的 config 目录
require_once __DIR__ . '/config/db.php';
$active_page = 'about'; 

// 2. 增加 try-catch 数据保护，同时拉取配置和发展历程
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 拉取发展历程数据，按年份降序、ID降序排列
    $histories = $pdo->query("SELECT * FROM development_history ORDER BY year DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
    $histories = [];
}

$hero_video = $settings['about_hero_video'] ?? '';
$hero_image = $settings['about_hero_image'] ?? '';

// 3. 引用统一页头
include_once __DIR__ . '/includes/header.php';
?>

<style>
    body { background: #FFF; color: #334155; overflow-x: hidden; margin: 0; padding: 0; font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif; }
    
    /* --- 动态 Hero 背景 --- */
    .about-hero { position: relative; height: 50vh; min-height: 400px; display: flex; align-items: center; justify-content: center; text-align: center; background: #1A3C6C; overflow: hidden; }
    .bg-media { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 1; opacity: 0.6; }
    .hero-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background:linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.6)); z-index:2; }
    .hero-text { position: relative; z-index: 3; color: #FFF; padding: 0 20px; }
    .hero-text h1 { font-size: clamp(28px, 5vw, 52px); font-weight: 900; margin: 0; letter-spacing: 4px; text-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    .hero-text p { font-size: clamp(14px, 2vw, 18px); margin-top: 15px; opacity: 0.9; font-weight: 300; letter-spacing: 1px; }

    .container { max-width: 1200px; margin: 0 auto; padding: clamp(40px, 10vw, 100px) 20px; }
    
    /* --- 双栏布局 --- */
    .content-grid { display: grid; grid-template-columns: 350px 1fr; gap: 80px; align-items: start; margin-bottom: clamp(50px, 8vw, 100px); }

    /* --- 左侧：垂直拖拽时间轴 --- */
    .timeline-wrapper { position: sticky; top: 100px; }
    .section-title { font-size: clamp(22px, 4vw, 28px); color: #1A3C6C; margin-bottom: 25px; font-weight: 800; border-bottom: 2px solid #F1F5F9; padding-bottom: 15px; }
    
    .timeline-scroll-area { 
        max-height: 500px; 
        overflow-y: auto; 
        cursor: grab; 
        padding-right: 15px; 
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch; 
    }
    .timeline-scroll-area::-webkit-scrollbar { display: none; }
    .timeline-scroll-area:active { cursor: grabbing; }

    .timeline { position: relative; padding: 15px 0; }
    .timeline::after { content: ''; position: absolute; width: 2px; background: #E2E8F0; top: 0; bottom: 0; left: 7px; }
    
    .t-item { position: relative; padding-left: 35px; margin-bottom: 35px; }
    .t-item::after { content: ''; position: absolute; width: 10px; height: 10px; left: 0; background: #FFF; border: 3px solid #1A3C6C; top: 6px; border-radius: 50%; z-index: 1; transition: 0.3s; }
    .t-item:hover::after { background: #1A3C6C; transform: scale(1.3); }
    
    .t-year { font-size: 22px; font-weight: 900; color: #1A3C6C; display: flex; align-items: baseline; margin-bottom: 8px; gap: 8px; }
    .t-month { font-size: 14px; font-weight: 700; color: #3B82F6; background: #EFF6FF; padding: 2px 8px; border-radius: 4px; }
    .t-content { background: #FFF; padding: 18px; border-radius: 12px; border: 1px solid #F1F5F9; transition: 0.3s; }
    .t-item:hover .t-content { border-color: #1A3C6C; transform: translateX(5px); box-shadow: 0 4px 15px rgba(26,60,108,0.05); }
    .t-content h4 { margin: 0 0 6px; color: #1E293B; font-size: 15px; }
    .t-content p { margin: 0; font-size: 13px; color: #64748B; line-height: 1.6; }

    /* --- 右侧：文字介绍与愿景 --- */
    .intro-column { padding-top: 5px; }
    .intro-text { font-size: 16px; line-height: 2; color: #475569; text-align: justify; margin-bottom: 40px; }
    .intro-text p { margin-bottom: 20px; text-indent: 2em; }

    .vision-section { text-align: center; padding: clamp(30px, 6vw, 60px); background: #F8FAFC; border-radius: 24px; border: 1px solid #F1F5F9; }
    .vision-section h2 { font-size: 22px; color: #1A3C6C; margin-top: 0; margin-bottom: 15px; font-weight: 800; }
    .vision-section p { font-size: clamp(16px, 2.5vw, 20px); color: #64748B; font-weight: 300; line-height: 1.6; margin: 0; }

    /* --- 核心价值观底部 --- */
    .values-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .value-card { background: #F8FAFC; padding: 25px 20px; border-radius: 16px; border-top: 4px solid #1A3C6C; transition: 0.3s; }
    .value-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.03); }
    .value-card h4 { margin: 0 0 10px; color: #1A3C6C; font-size: 17px; }
    .value-card p { margin: 0; font-size: 13px; color: #64748B; line-height: 1.6; }

    /* --- 移动端响应式核心调整 --- */
    @media (max-width: 991px) {
        .content-grid { grid-template-columns: 1fr; gap: 50px; }
        .timeline-wrapper { position: static; } 
        .timeline-scroll-area { max-height: 400px; }
        .values-grid { grid-template-columns: 1fr 1fr; }
    }
    
    @media (max-width: 600px) {
        .values-grid { grid-template-columns: 1fr; }
        .hero-text h1 { letter-spacing: 2px; }
        .intro-text p { text-indent: 0; } 
    }
</style>

<section class="about-hero">
    <?php if (!empty($hero_video)): ?>
        <video class="bg-media" autoplay loop muted playsinline><source src="<?= htmlspecialchars($hero_video) ?>" type="video/mp4"></video>
    <?php elseif (!empty($hero_image)): ?>
        <img src="<?= htmlspecialchars($hero_image) ?>" class="bg-media">
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>智领未来 · 重塑美学</h1>
        <p>引领产业进化 · 定义品质生活</p>
    </div>
</section>

<div class="container">
    
    <div class="content-grid">
        
        <div class="timeline-wrapper">
            <h2 class="section-title">发展历程</h2>
            <div class="timeline-scroll-area" id="vertical-timeline">
                <div class="timeline">
                    <?php if (empty($histories)): ?>
                        <div style="color: #94A3B8; font-size: 13px; padding-left: 30px;">历程数据正在同步中...</div>
                    <?php else: ?>
                        <?php foreach ($histories as $h): ?>
                            <div class="t-item">
                                <span class="t-year">
                                    <?= htmlspecialchars($h['year']) ?>
                                    <?php if(!empty($h['month'])): ?>
                                        <span class="t-month"><?= htmlspecialchars($h['month']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <div class="t-content">
                                    <h4><?= htmlspecialchars($h['title']) ?></h4>
                                    <p><?= htmlspecialchars($h['content']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align: center; font-size: 11px; color: #CBD5E1; margin-top: 10px;">
                <i class="fa-solid fa-arrows-up-down"></i> 上下拖拽或滚动查看
            </div>
        </div>

        <div class="intro-column">
            <h2 class="section-title">关于我们</h2>
            <div class="intro-text">
                <p>代世集团（DAISHI GROUP）是一家立足中国、辐射全球的多元化产业投资与运营集团。自创立以来，我们始终以“发现并重塑商业美学”为底层逻辑，通过技术赋能与创意驱动，在文化传媒、大健康休闲、高端科技美妆等领域构建了极具竞争力的产业生态。</p>
                <p>作为一家具有前瞻性的企业，代世集团不仅关注财务增长，更致力于在每一个细分领域定义行业标准。我们坚持以“极致创新”为核心动力，通过数字化运营中枢对各业务板块进行全方位深度赋能。</p>
                <p>集团总部作为坚实的战略决策与赋能枢纽，持续推动“代世传媒”、“七号汤泉”、“DK化妆品”等核心事业部在独立精细化运营中，寻找底层逻辑的共鸣与生态协同。我们致力于传递一种先锋、健康、高品质的生活方式。</p>
            </div>

            <div class="vision-section">
                <h2>企业愿景</h2>
                <p>"成为全球领先的、最具创新精神的新型产业生态构建者，为全人类创造更美好的生活美学。"</p>
            </div>
        </div>
        
    </div>

    <div style="margin-bottom: 30px;">
        <h2 class="section-title" style="text-align:center; border:none;">核心价值观</h2>
    </div>
    <div class="values-grid">
        <div class="value-card">
            <h4>极致创新</h4>
            <p>拒绝平庸，在瞬息万变的市场中，用颠覆性的思维重构体验。</p>
        </div>
        <div class="value-card">
            <h4>用户第一</h4>
            <p>将用户诉求置于一切决策的起点，用卓越交付赢得信任。</p>
        </div>
        <div class="value-card">
            <h4>开放协同</h4>
            <p>打破部门壁垒，鼓励跨界融合，打造互利共生的生态系统。</p>
        </div>
        <div class="value-card">
            <h4>社会责任</h4>
            <p>坚守商业道德，积极回馈社会，在追求价值同时践行担当。</p>
        </div>
    </div>

</div>

<script>
    /**
     * 时间轴拖拽逻辑 - 兼容移动端操作
     */
    const vSlider = document.getElementById('vertical-timeline');
    let isDown = false;
    let startY;
    let scrollTop;

    const startAction = (y) => {
        isDown = true;
        vSlider.style.cursor = 'grabbing';
        startY = y - vSlider.offsetTop;
        scrollTop = vSlider.scrollTop;
    };

    const moveAction = (y) => {
        if (!isDown) return;
        const walk = (y - vSlider.offsetTop - startY) * 1.5; 
        vSlider.scrollTop = scrollTop - walk;
    };

    const stopAction = () => {
        isDown = false;
        vSlider.style.cursor = 'grab';
    };

    // 鼠标事件
    vSlider.addEventListener('mousedown', (e) => startAction(e.pageY));
    vSlider.addEventListener('mousemove', (e) => {
        if(isDown) e.preventDefault();
        moveAction(e.pageY);
    });
    window.addEventListener('mouseup', stopAction);

    // 触摸事件（可选，原生滚动在手机上通常更好，此处保留拖拽逻辑辅助）
    vSlider.addEventListener('touchstart', (e) => {
        startAction(e.touches[0].pageY);
    }, {passive: true});
    vSlider.addEventListener('touchmove', (e) => {
        moveAction(e.touches[0].pageY);
    }, {passive: true});
    vSlider.addEventListener('touchend', stopAction);
</script>

<?php 
// 4. 引用统一页脚
include_once __DIR__ . '/includes/footer.php'; 
?>