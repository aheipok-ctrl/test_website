<?php
/**
 * 代世集团 - 新闻详情页 (极简容错版)
 * 修复：精准匹配 news_articles 表，并在查不到数据时展示高级 404 页面
 */
session_start();

// 1. 引入数据库配置
require_once __DIR__ . '/config/db.php';
$active_page = 'news'; 

// 2. 获取新闻 ID 并查询数据
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = false;

if ($id > 0) {
    try {
        // 尝试增加浏览量 (如果表里有 views 字段的话)
        try {
            $pdo->prepare("UPDATE news_articles SET views = views + 1 WHERE id = ?")->execute([$id]);
        } catch (Exception $e) { /* 忽略没 views 字段的报错 */ }

        // 查询新闻：只查状态为 active 的，或者没有状态限制的
        $stmt = $pdo->prepare("SELECT * FROM news_articles WHERE id = ?");
        $stmt->execute([$id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果查出来的文章状态明确不是 active，也当作没找到
        if ($article && isset($article['status']) && $article['status'] !== 'active') {
            $article = false;
        }
    } catch (PDOException $e) {
        $article = false;
    }
}

// 3. 引入全局页头
include_once __DIR__ . '/includes/header.php';
?>

<style>
    body { background: #F8FAFC; color: #334155; margin: 0; padding: 0; font-family: "PingFang SC", "Microsoft YaHei", sans-serif; }
    
    /* 顶部占位，防止被固定导航栏遮挡 */
    .header-spacer { height: 80px; background: #FFF; }
    
    /* 核心阅读区/提示区容器 */
    .article-container { 
        max-width: 900px; 
        margin: 40px auto 100px; 
        background: #FFF; 
        padding: clamp(40px, 6vw, 80px); 
        border-radius: 24px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.03); 
        box-sizing: border-box;
        min-height: 50vh;
    }

    /* 正常文章样式 */
    .article-header { border-bottom: 1px solid #F1F5F9; padding-bottom: 30px; margin-bottom: 40px; text-align: center; }
    .article-title { font-size: clamp(24px, 4vw, 36px); color: #1A3C6C; font-weight: 900; margin: 0 0 20px; line-height: 1.4; }
    
    .article-meta { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 20px; font-size: 14px; color: #94A3B8; }
    .meta-item { display: flex; align-items: center; gap: 6px; }
    .meta-tag { background: #F1F7FF; color: #1A3C6C; padding: 4px 12px; border-radius: 6px; font-weight: bold; }

    .article-content { font-size: 16px; line-height: 2.2; color: #475569; text-align: justify; }
    .article-content p { margin-bottom: 25px; }
    .article-content img { max-width: 100%; height: auto; border-radius: 12px; margin: 30px 0; display: block; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .article-content h2, .article-content h3 { color: #1E293B; margin-top: 50px; margin-bottom: 20px; font-weight: 900; border-left: 4px solid #1A3C6C; padding-left: 15px; }
    
    /* --- 截图中的极简报错样式 --- */
    .not-found-wrapper { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center; 
        text-align: center; 
        height: 100%; 
        padding: 60px 20px;
    }
    .not-found-wrapper h2 { 
        color: #334155; 
        font-size: clamp(20px, 3vw, 28px); 
        font-weight: 800; 
        margin: 0 0 15px; 
        letter-spacing: 1px;
    }
    .not-found-wrapper p { 
        color: #94A3B8; 
        font-size: clamp(14px, 2vw, 16px); 
        margin: 0 0 40px; 
        font-weight: 300;
    }
    
    /* 返回按钮统一样式 */
    .btn-back { 
        display: inline-block; 
        padding: 14px 40px; 
        background: #F8FAFC; 
        color: #475569; 
        text-decoration: none; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 15px; 
        transition: 0.3s;
        border: 1px solid #E2E8F0;
    }
    .btn-back:hover { 
        background: #1A3C6C; 
        color: #FFF; 
        border-color: #1A3C6C; 
        box-shadow: 0 10px 20px rgba(26,60,108,0.15); 
        transform: translateY(-2px);
    }

    /* 移动端适配 */
    @media (max-width: 768px) {
        .article-container { margin: 20px; border-radius: 16px; padding: 30px 20px; }
        .article-header { text-align: left; }
        .article-meta { justify-content: flex-start; }
    }
</style>

<div class="header-spacer"></div>

<main class="article-container">
    <?php if ($article): ?>
        
        <header class="article-header">
            <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
            <div class="article-meta">
                <?php $pub_time = $article['publish_date'] ?? $article['created_at'] ?? ''; ?>
                <?php if($pub_time): ?>
                    <div class="meta-item"><i class="fa-regular fa-clock"></i> <?= date('Y-m-d H:i', strtotime($pub_time)) ?></div>
                <?php endif; ?>
                
                <?php if(!empty($article['author'])): ?>
                    <div class="meta-item"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($article['author']) ?></div>
                <?php endif; ?>
                
                <?php if(!empty($article['category'])): ?>
                    <div class="meta-tag"><?= htmlspecialchars($article['category']) ?></div>
                <?php endif; ?>
            </div>
        </header>

        <article class="article-content">
            <?= $article['content'] ?? $article['body'] ?? '<p style="text-align:center; color:#94a3b8;">暂无详细内容</p>' ?>
        </article>

        <div style="margin-top: 60px; text-align: center; border-top: 1px solid #F1F5F9; padding-top: 40px;">
            <a href="news.php" class="btn-back">返回新闻中心</a>
        </div>

    <?php else: ?>
        
        <div class="not-found-wrapper">
            <h2>抱歉，您访问的新闻不存在或已下线</h2>
            <p>该内容可能已被管理员移除或链接有误。</p>
            <a href="news.php" class="btn-back">返回新闻中心</a>
        </div>
        
    <?php endif; ?>
</main>

<?php 
// 4. 引入全局页脚
include_once __DIR__ . '/includes/footer.php'; 
?>