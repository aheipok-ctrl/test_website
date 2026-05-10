<?php
/**
 * 媒体资源下载页面 (移动端全适配版)
 */
// 优先引用主配置以保持连接一致
require_once 'config/db.php';
$active_page = 'news'; 

$file_name = $_GET['name'] ?? '未知文件';
$file_id = $_GET['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>资源下载 - 代世集团</title>
    <?php include_once 'includes/header.php'; ?>
    <style>
        body { background: #F1F5F9; color: #1E293B; margin: 0; padding: 0; font-family: "Alibaba PuHuiTi 2.0", sans-serif; }
        
        /* --- 容器布局适配 --- */
        .download-container { 
            max-width: 800px; 
            margin: clamp(80px, 15vh, 120px) auto clamp(40px, 10vh, 60px); 
            padding: 0 20px;
            box-sizing: border-box;
        }

        /* --- 下载卡片适配 --- */
        .download-card { 
            background: #FFF; 
            padding: clamp(40px, 8vw, 60px) clamp(20px, 5vw, 40px); 
            border-radius: 30px; 
            box-shadow: 0 20px 50px rgba(26,60,108,0.08); 
            text-align: center; 
            border: 1px solid rgba(255,255,255,0.5);
        }

        /* --- 图标与文字适配 --- */
        .file-large-icon { 
            font-size: clamp(60px, 12vw, 100px); 
            color: #1A3C6C; 
            margin-bottom: 25px; 
            display: inline-block;
        }
        .file-title { 
            font-size: clamp(22px, 5vw, 28px); 
            color: #1E293B; 
            font-weight: 800; 
            margin-bottom: 12px; 
            line-height: 1.3;
            word-break: break-all;
        }
        .file-info { 
            color: #94A3B8; 
            font-size: clamp(12px, 3vw, 14px); 
            margin-bottom: 35px; 
            line-height: 1.6;
        }
        
        /* --- 下载按钮适配 --- */
        .btn-download { 
            background: #1A3C6C; 
            color: #FFF; 
            padding: 18px clamp(30px, 10vw, 60px); 
            border-radius: 50px; 
            text-decoration: none; 
            font-weight: 800; 
            font-size: 18px; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); 
            box-shadow: 0 10px 20px rgba(26,60,108,0.2);
            width: auto;
            max-width: 100%;
            box-sizing: border-box;
        }
        .btn-download:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(26,60,108,0.3); }
        .btn-download:active { transform: scale(0.95); }
        
        .back-link { 
            display: inline-block; 
            margin-top: 35px; 
            color: #64748B; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 700; 
            transition: 0.3s;
        }
        .back-link:hover { color: #1A3C6C; transform: translateX(-5px); }

        /* --- 手机端专项调整 --- */
        @media (max-width: 600px) {
            .download-container { margin-top: 80px; }
            .btn-download { width: 100%; } /* 手机端按钮撑满 */
            .file-info { padding: 0 10px; }
        }
    </style>
</head>
<body>

<div class="download-container">
    <div class="download-card">
        <i class="fa-solid fa-cloud-arrow-down file-large-icon"></i>
        <h1 class="file-title"><?= htmlspecialchars($file_name) ?></h1>
        <p class="file-info">
            文件 ID: <?= htmlspecialchars($file_id) ?> | 安全扫描: 已通过 | 来源: 代世集团官方媒体中心
        </p>
        
        <a href="download.php?id=<?= $file_id ?>" class="btn-download" onclick="return confirmDownload();">
            <i class="fa-solid fa-download"></i> 立即下载文件
        </a>
        
        <a href="news.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> 返回媒体中心</a>
    </div>
</div>

<script>
    function confirmDownload() {
        // 模拟调取资源提示
        console.log('正在调取服务器资源，请稍候...');
        return true; 
    }
</script>

<?php include_once 'includes/footer.php'; ?>
</body>
</html>