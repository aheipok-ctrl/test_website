<?php
/**
 * 代世集团官网 - 全量新闻列表页 (手机端全适配版 + 智能默认封面)
 * 功能：自动读取 news_articles 数据，支持分类筛选
 */
session_start();
require_once __DIR__ . '/config/db.php';

// 获取筛选分类
$cat_filter = $_GET['cat'] ?? 'ALL';

// 构建查询语句
if ($cat_filter === 'ALL') {
    $stmt = $pdo->prepare("SELECT * FROM news_articles WHERE status = 'active' ORDER BY publish_date DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM news_articles WHERE status = 'active' AND category = ? ORDER BY publish_date DESC");
    $stmt->execute([$cat_filter]);
}
$news_items = $stmt->fetchAll();

// --- 辅助逻辑：根据标题生成默认智能图标 ---
function getNewsDefaultIcon($title) {
    if (mb_strpos($title, '合作') !== false || mb_strpos($title, '签约') !== false) return 'fa-handshake';
    if (mb_strpos($title, '技术') !== false || mb_strpos($title, '研发') !== false || mb_strpos($title, '系统') !== false) return 'fa-microchip';
    if (mb_strpos($title, '招聘') !== false || mb_strpos($title, '人才') !== false) return 'fa-user-plus';
    if (mb_strpos($title, '公告') !== false || mb_strpos($title, '通知') !== false) return 'fa-bullhorn';
    if (mb_strpos($title, '活动') !== false || mb_strpos($title, '团建') !== false) return 'fa-people-group';
    if (mb_strpos($title, '获奖') !== false || mb_strpos($title, '荣誉') !== false) return 'fa-trophy';
    return 'fa-newspaper'; // 默认图标
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新闻中心 - 代世集团</title>
    <?php include_once 'includes/header.php'; ?>
    <style>
        /* --- 基础布局适配 --- */
        .news-container { 
            max-width: 1200px; 
            margin: 100px auto 60px; 
            padding: 0 20px; 
        }

        /* --- 标题区域适配 --- */
        .news-header { text-align: center; margin-bottom: 40px; }
        .news-header h1 { 
            color: #1A3C6C; 
            font-size: clamp(28px, 5vw, 42px); 
            font-weight: 900;
            margin-bottom: 10px; 
        }
        .news-header p { color: #94A3B8; font-size: 14px; letter-spacing: 1px; }

        /* --- 分类筛选栏适配 --- */
        .filter-bar { 
            display: flex; 
            justify-content: center; 
            gap: 12px; 
            margin-bottom: 40px; 
            /* 手机端横向滚动逻辑 */
            overflow-x: auto;
            padding: 10px 5px;
            -webkit-overflow-scrolling: touch;
        }
        /* 隐藏滚动条但保持滚动功能 */
        .filter-bar::-webkit-scrollbar { display: none; }
        
        .filter-btn { 
            padding: 10px 22px; 
            border-radius: 50px; 
            border: 1px solid #E2E8F0; 
            text-decoration: none; 
            color: #64748B; 
            font-weight: 700; 
            font-size: 14px;
            transition: 0.3s; 
            white-space: nowrap; /* 防止按钮文字换行 */
            flex-shrink: 0;
        }
        .filter-btn.active { background: #1A3C6C; color: #FFF; border-color: #1A3C6C; box-shadow: 0 4px 12px rgba(26,60,108,0.2); }
        
        /* --- 新闻网格适配 --- */
        .news-grid { 
            display: grid; 
            /* 核心：min(100%, 350px) 确保在屏幕小于350px时也不会溢出 */
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 350px), 1fr)); 
            gap: 25px; 
        }
        
        .news-item { 
            background: #FFF; 
            border-radius: 20px; 
            overflow: hidden; 
            border: 1px solid #F1F5F9; 
            transition: 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); 
            text-decoration: none; 
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .news-item:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); }
        
        .news-cover-box { width: 100%; aspect-ratio: 16 / 9; overflow: hidden; background: #F8FAFC; position: relative; }
        .news-cover { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .news-item:hover .news-cover { transform: scale(1.05); }

        /* --- 新增：智能占位图标样式 --- */
        .news-placeholder {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, #1A3C6C 0%, #2A5298 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.9); gap: 15px;
        }
        .news-placeholder i { font-size: 54px; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2)); transition: 0.5s; }
        .news-item:hover .news-placeholder i { transform: scale(1.1); }
        .news-placeholder span { font-size: 11px; font-weight: 800; letter-spacing: 2px; opacity: 0.6; text-transform: uppercase; }

        .news-body { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
        .news-tag { 
            align-self: flex-start;
            padding: 4px 12px; 
            background: #F0F7FF; 
            color: #1A3C6C; 
            font-size: 11px; 
            font-weight: 800; 
            border-radius: 6px; 
            margin-bottom: 12px; 
        }
        .news-title { 
            font-size: 18px; 
            font-weight: 800; 
            color: #1E293B; 
            margin-bottom: 15px; 
            line-height: 1.5;
            /* 限制标题行数，保持整齐 */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-meta { color: #94A3B8; font-size: 13px; margin-top: auto; border-top: 1px solid #F1F5F9; padding-top: 15px; }

        /* --- 手机端专项调整 --- */
        @media (max-width: 768px) {
            .news-container { margin-top: 80px; padding: 0 15px; }
            .filter-bar { justify-content: flex-start; padding-left: 15px; margin-left: -15px; width: calc(100% + 30px); }
            .news-body { padding: 20px; }
            .news-title { font-size: 17px; }
        }
    </style>
</head>
<body>

<div class="news-container">
    <div class="news-header">
        <h1>新闻中心</h1>
        <p>LATEST NEWS & CORPORATE DYNAMICS</p>
    </div>

    <div class="filter-bar">
        <a href="?cat=ALL" class="filter-btn <?= $cat_filter == 'ALL' ? 'active' : '' ?>">全部新闻</a>
        <a href="?cat=CORP" class="filter-btn <?= $cat_filter == 'CORP' ? 'active' : '' ?>">集团要闻</a>
        <a href="?cat=BIZ" class="filter-btn <?= $cat_filter == 'BIZ' ? 'active' : '' ?>">业务动态</a>
        <a href="?cat=TECH" class="filter-btn <?= $cat_filter == 'TECH' ? 'active' : '' ?>">科技创新</a>
    </div>

    <div class="news-grid">
        <?php if (count($news_items) > 0): ?>
            <?php foreach($news_items as $item): ?>
                <a href="news_detail.php?id=<?= $item['id'] ?>" class="news-item">
                    <div class="news-cover-box">
                        <?php if(!empty($item['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($item['cover_image']) ?>" class="news-cover" alt="Cover">
                        <?php else: ?>
                            <div class="news-placeholder">
                                <i class="fa-solid <?= getNewsDefaultIcon($item['title']) ?>"></i>
                                <span>Daishi Group News</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="news-body">
                        <span class="news-tag"><?= htmlspecialchars($item['category']) ?></span>
                        <div class="news-title"><?= htmlspecialchars($item['title']) ?></div>
                        <div class="news-meta">
                            <i class="fa-regular fa-calendar-check"></i> <?= date('Y-m-d', strtotime($item['publish_date'])) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 100px 20px; color: #94A3B8;">
                <i class="fa-solid fa-newspaper" style="font-size: 56px; margin-bottom: 20px; opacity: 0.2;"></i>
                <p>该分类下暂无已发布的新闻动态</p>
                <a href="?cat=ALL" style="color:#1A3C6C; text-decoration:none; font-weight:bold; margin-top:10px; display:inline-block;">查看全部新闻</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
</body>
</html>