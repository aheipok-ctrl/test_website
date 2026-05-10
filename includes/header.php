<?php
/**
 * 代世集团官网 - 溢出悬浮导航栏 (极致纯黑·全适配版)
 * 修复：添加手机端交互逻辑，支持汉堡菜单点击展开
 */

// 1. 自动关联数据库配置
$db_path = dirname(__DIR__) . '/config/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
}

// 2. 获取全站配置
if (!isset($settings) || !is_array($settings)) {
    try {
        $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $settings = [];
    }
}

$site_name = $settings['site_name'] ?? '代世集团';
$site_logo = $settings['site_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alibaba-puhuiti@2.0.0/index.min.css">

    <style>
        /* --- 核心：极致黑白视觉体系 --- */
        header { 
            font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif;
            position: fixed; top: 0; left: 0; width: 100%; 
            height: 60px; 
            padding: 0 80px; 
            display: flex; 
            justify-content: space-between; align-items: center; 
            z-index: 1000; box-sizing: border-box;
            background: rgba(255, 255, 255, 0.98); 
            backdrop-filter: blur(15px);
            box-shadow: 0 1px 10px rgba(0,0,0,0.05); 
            transition: all 0.4s ease;
            -webkit-font-smoothing: antialiased;
        }
        
        .logo { position: relative; height: 100%; display: flex; align-items: center; z-index: 1001; }
        
        .logo img { 
            height: 95px; 
            width: auto; 
            object-fit: contain; 
            display: block;
            transform: translateY(15px); 
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.1));
        }
        
        .logo h2 { 
            color: #000000 !important; 
            margin: 0; font-weight: 900; 
            letter-spacing: 2px;
            font-size: 26px;
        }
        
        /* --- 桌面端导航 --- */
        .nav-links { display: flex; gap: 50px; height: 100%; align-items: center; }
        
        .nav-links a { 
            color: #000000 !important;
            text-decoration: none; 
            font-size: 18px; 
            font-weight: 900; 
            letter-spacing: 1.2px; 
            transition: 0.3s; 
            opacity: 0.85; 
            padding: 8px 0;
            position: relative;
        }
        
        .nav-links a:hover { opacity: 1; } 
        
        .nav-links a::after {
            content: ''; position: absolute; bottom: 2px; left: 0;
            width: 0; height: 3px; background: #000000;
            transition: 0.3s; border-radius: 2px;
        }
        .nav-links a:hover::after { width: 100%; }

        /* --- 手机端切换按钮 --- */
        .mobile-menu-toggle { 
            color: #000; font-size: 26px; cursor: pointer; 
            display: none; z-index: 1001; position: relative;
        }
        
        /* --- 适配与手机端菜单样式 --- */
        @media (max-width: 1024px) {
            header { padding: 0 25px; height: 60px; }
            .logo img { height: 65px; transform: translateY(10px); }
            .mobile-menu-toggle { display: block; }

            /* 手机端全屏导航菜单 */
            .nav-links {
                position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
                background: #FFF;
                flex-direction: column;
                justify-content: center;
                gap: 40px;
                transition: transform 0.4s cubic-bezier(0.77,0,0.175,1);
                transform: translateX(100%); /* 默认隐藏在右侧 */
                display: flex; 
                z-index: 1000;
            }

            .nav-links.active {
                transform: translateX(0); /* 激活时滑动进入 */
            }

            .nav-links a {
                font-size: 24px; /* 手机端字号加大 */
                opacity: 1;
            }

            .nav-links a::after { display: none; } /* 手机端隐藏下划线动画 */
        }
    </style>
</head>
<body>
    <header id="mainHeader">
        <div class="logo">
            <a href="/index.php" style="text-decoration: none;">
                <?php if(!empty($site_logo)): ?>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($site_name) ?>">
                <?php else: ?>
                    <h2><?= htmlspecialchars($site_name) ?></h2>
                <?php endif; ?>
            </a>
        </div>
        <nav class="nav-links" id="navLinks">
            <a href="/index.php">首页</a>
            <a href="/business.php">集团业务</a>
            <a href="/about.php">关于代世</a>
            <a href="/news.php">新闻和媒体</a>
            <a href="/careers/index.php">人才招聘</a>
        </nav>
        <i class="fa-solid fa-bars mobile-menu-toggle" id="menuToggle"></i>
    </header>
    <div style="height: 60px;"></div>

    <script>
        /**
         * 手机端导航逻辑
         */
        const menuToggle = document.getElementById('menuToggle');
        const navLinks = document.getElementById('navLinks');
        const toggleIcon = menuToggle;

        menuToggle.addEventListener('click', () => {
            // 切换菜单显示/隐藏
            navLinks.classList.toggle('active');
            
            // 切换图标：菜单打开时显示 X，关闭时显示三杠
            if (navLinks.classList.contains('active')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-xmark');
                document.body.style.overflow = 'hidden'; // 禁止页面滚动
            } else {
                toggleIcon.classList.remove('fa-xmark');
                toggleIcon.classList.add('fa-bars');
                document.body.style.overflow = ''; // 恢复页面滚动
            }
        });

        // 点击导航链接后自动关闭菜单（适用于单页跳转）
        const navItems = navLinks.querySelectorAll('a');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navLinks.classList.remove('active');
                toggleIcon.classList.remove('fa-xmark');
                toggleIcon.classList.add('fa-bars');
                document.body.style.overflow = '';
            });
        });
    </script>
</body>
</html>