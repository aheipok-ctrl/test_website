<?php
/**
 * 自动生成的业务详情页 - 代世集团
 */
require_once '../config/db.php';

$current_page_slug = 'clothing';
$stmt = $pdo->prepare("SELECT * FROM business_units WHERE slug = ? LIMIT 1");
$stmt->execute([$current_page_slug]);
$biz = $stmt->fetch();

if (!$biz) die("内容未找到");

// 引入根目录 includes 文件夹下的标准页头
include_once '../includes/header.php';
?>

<style>
    .biz-hero { position: relative; width: 100%; height: 50vh; min-height: 400px; background: #000; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .biz-hero-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.6; z-index: 1; }
    .biz-hero-content { position: relative; z-index: 3; text-align: center; color: #fff; padding: 0 20px; }
    .biz-hero-content h1 { font-size: clamp(32px, 5vw, 56px); font-weight: 900; margin: 0 0 15px; letter-spacing:2px; }
    .biz-detail-container { max-width: 1200px; margin: 60px auto 100px; padding: 0 20px; line-height: 1.8; }
</style>

<div class="biz-hero">
    <?php if(!empty($biz['media_path'])): ?>
        <?php if(strpos($biz['media_path'], '.mp4') !== false): ?>
            <video src="<?php echo $biz['media_path']; ?>" class="biz-hero-bg" autoplay muted loop playsinline></video>
        <?php else: ?>
            <img src="<?php echo $biz['media_path']; ?>" class="biz-hero-bg">
        <?php endif; ?>
    <?php endif; ?>
    <div class="biz-hero-content">
        <h1><?php echo htmlspecialchars($biz['name']); ?></h1>
        <p style="opacity:0.8; font-size:18px;"><?php echo htmlspecialchars($biz['brief'] ?? ''); ?></p>
    </div>
</div>

<main class="biz-detail-container">
    <?php echo $biz['page_body'] ?? '<p style="text-align:center; color:#94a3b8;">暂无详情内容</p>'; ?>
</main>

<?php 
// 引入根目录 includes 文件夹下的标准页脚
include_once '../includes/footer.php'; 
?>