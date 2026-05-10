<?php
/**
 * 代世集团 - 全量素材资源中心 (移动端全适配 + 真实数据库对接 + 内部鉴权隔离 + 默认预览图)
 */
session_start();
require_once __DIR__ . '/config/db.php';

// --- 1. 处理员工鉴权逻辑 ---
$auth_error = '';
if (isset($_POST['auth_submit'])) {
    $acc = trim($_POST['emp_account']);
    $pwd = trim($_POST['emp_password']);
    
    // 验证账号密码（复用后台统一安全验证机制）
    $stmt = $pdo->prepare("SELECT password FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$acc]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && (md5($pwd) === $user['password'] || password_verify($pwd, $user['password']) || $pwd === $user['password'])) {
        $_SESSION['emp_verified'] = true;
        $auto_open_private = true; // 验证成功后，告诉前端自动切入内部文件Tab
    } else {
        $auth_error = '验证失败：员工账号或密码不正确！';
    }
}

// 检查当前是否已取得内部授权
$is_verified = isset($_SESSION['emp_verified']) && $_SESSION['emp_verified'] === true;

// --- 2. 拉取真实媒体数据 ---
$files = [];
try {
    $files = $pdo->query("SELECT * FROM media_assets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>全量素材资源 - 代世集团</title>
    <?php include_once 'includes/header.php'; ?>
    <style>
        body { background: #F8FAFC; color: #1A3C6C; margin: 0; padding: 0; overflow-x: hidden; font-family: "PingFang SC", "Microsoft YaHei", sans-serif; }
        
        /* --- 基础布局适配 --- */
        .container { 
            max-width: 1200px; 
            margin: clamp(80px, 10vw, 120px) auto clamp(40px, 8vw, 60px); 
            padding: 0 20px; 
            box-sizing: border-box;
        }
        
        .header-area { text-align: center; margin-bottom: clamp(30px, 6vw, 50px); }
        .header-area h1 { font-size: clamp(24px, 5vw, 36px); font-weight: 900; letter-spacing: 2px; margin-bottom: 15px; color: #1A3C6C; }
        .header-area p { color: #94A3B8; font-size: clamp(13px, 2vw, 16px); }

        /* --- 1. 搜索与筛选控制栏适配 --- */
        .search-container { 
            background: #FFF; 
            padding: clamp(15px, 3vw, 25px); 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(26,60,108,0.05); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin-bottom: clamp(40px, 8vw, 60px);
        }
        
        .search-input-wrapper { 
            position: relative; 
            flex: 1 1 300px; 
            max-width: 450px;
            min-width: 0; 
        }
        .search-input-wrapper i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #CBD5E1; }
        .search-input-wrapper input { 
            width: 100%; 
            padding: 14px 15px 14px 48px; 
            border-radius: 15px; 
            border: 1px solid #F1F5F9; 
            background: #F8FAFC; 
            outline: none; 
            font-size: 14px; 
            transition: 0.3s; 
            box-sizing: border-box;
        }
        .search-input-wrapper input:focus { background: #FFF; border-color: #1A3C6C; box-shadow: 0 0 0 4px rgba(26,60,108,0.05); }

        .filter-group { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
            justify-content: center;
        }
        .filter-btn { 
            padding: 10px 20px; 
            border-radius: 12px; 
            border: none; 
            background: #F1F5F9; 
            color: #64748B; 
            font-weight: 700; 
            font-size: 13px; 
            cursor: pointer; 
            transition: 0.3s; 
            white-space: nowrap;
        }
        .filter-btn:active { transform: scale(0.95); }
        .filter-btn.active { background: #1A3C6C; color: #FFF; box-shadow: 0 4px 12px rgba(26,60,108,0.2); }
        /* 内部文件专属按钮样式 */
        .filter-btn.private-btn { color: #8B5CF6; background: #F5F3FF; border: 1px solid #EDE9FE; }
        .filter-btn.private-btn.active { background: #8B5CF6; color: #FFF; border-color: #8B5CF6; box-shadow: 0 4px 12px rgba(139,92,246,0.3); }

        /* --- 2. 素材卡片网格适配 --- */
        .media-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 180px), 1fr)); 
            gap: clamp(15px, 3vw, 30px); 
        }
        
        .media-item { 
            background: #FFF; 
            padding: clamp(25px, 5vw, 40px) 15px; 
            border-radius: 20px; 
            border: 1px solid #F1F5F9; 
            text-align: center; 
            text-decoration: none; 
            color: inherit; 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
            display: flex; 
            flex-direction: column; 
            align-items: center;
            height: 100%;
            box-sizing: border-box;
            position: relative;
        }
        .media-item:hover { 
            transform: translateY(-8px); 
            border-color: #1A3C6C; 
            box-shadow: 0 15px 35px rgba(26,60,108,0.08); 
        }
        /* 若为内部文件改变悬浮颜色 */
        .media-item.private-item:hover { border-color: #8B5CF6; box-shadow: 0 15px 35px rgba(139,92,246,0.1); }
        .media-item.private-item i { color: #8B5CF6; }

        .media-item i { font-size: clamp(40px, 8vw, 56px); color: #1A3C6C; margin-bottom: 20px; transition: 0.4s; }
        
        /* 【新增】：内部文件的默认预览图（占位图块）样式 */
        .private-placeholder-img {
            width: 80px; 
            height: 80px; 
            background: linear-gradient(135deg, #F5F3FF 0%, #EDE9FE 100%);
            border-radius: 16px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center;
            margin-bottom: 20px; 
            border: 1px solid #DDD6FE; 
            box-shadow: 0 4px 10px rgba(139,92,246,0.15);
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .private-placeholder-img i { font-size: 28px !important; margin-bottom: 6px !important; color: #8B5CF6 !important; }
        .media-item.private-item:hover .private-placeholder-img { transform: scale(1.08); box-shadow: 0 8px 20px rgba(139,92,246,0.25); }

        .media-item span { 
            font-size: 14px; 
            font-weight: 700; 
            color: #334155; 
            line-height: 1.4; 
            word-break: break-all;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* 空状态显示适配 */
        #no-results { grid-column: 1 / -1; text-align: center; padding: 80px 20px; display: none; }
        #no-results i { font-size: 50px; color: #E2E8F0; margin-bottom: 15px; }
        #no-results p { color: #94A3B8; font-weight: 600; font-size: 14px; }

        /* --- 3. 鉴权专属弹窗样式 --- */
        .auth-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px);
            z-index: 9999; display: none; align-items: center; justify-content: center;
        }
        .auth-modal {
            background: #FFF; width: 90%; max-width: 420px; border-radius: 24px;
            padding: 35px; box-shadow: 0 25px 60px rgba(0,0,0,0.3); position: relative;
        }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 22px; color: #94A3B8; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #EF4444; }
        .auth-title { margin: 0 0 10px; font-size: 22px; color: #1E293B; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .auth-desc { color: #64748B; font-size: 14px; margin-bottom: 25px; line-height: 1.6; }
        .auth-input { width: 100%; padding: 14px 18px; border: 2px solid #F1F5F9; border-radius: 12px; font-size: 15px; box-sizing: border-box; outline: none; transition: 0.3s; background: #F8FAFC; margin-bottom: 15px; }
        .auth-input:focus { border-color: #8B5CF6; background: #FFF; box-shadow: 0 0 0 4px rgba(139,92,246,0.1); }
        .btn-auth-submit { width: 100%; padding: 16px; background: #8B5CF6; color: #FFF; border: none; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.2s; margin-top: 5px; }
        .btn-auth-submit:active { transform: scale(0.98); }

        /* --- 手机端专项调整 --- */
        @media (max-width: 600px) {
            .search-container { border-radius: 15px; padding: 15px; }
            .filter-group { 
                justify-content: flex-start;
                overflow-x: auto;
                width: 100%;
                padding-bottom: 5px;
                flex-wrap: nowrap;
                scrollbar-width: none; 
            }
            .filter-group::-webkit-scrollbar { display: none; }
            .filter-btn { padding: 8px 18px; font-size: 12px; }
            .media-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .media-item { padding: 25px 10px; }
        }
    </style>
</head>
<body>

<?php if ($auth_error): ?>
    <script>alert('<?= $auth_error ?>');</script>
<?php endif; ?>

<div class="container">
    <div class="header-area">
        <h1>全量素材资源</h1>
        <p>输入关键字或按类别快速筛选所需素材</p>
    </div>

    <div class="search-container">
        <div class="search-input-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="fileSearch" placeholder="搜索素材名称..." onkeyup="executeFilter()">
        </div>
        <div class="filter-group">
            <button class="filter-btn active" data-type="ALL" onclick="setCategory('ALL', this)">全部</button>
            <button class="filter-btn" data-type="PDF" onclick="setCategory('PDF', this)">PDF</button>
            <button class="filter-btn" data-type="WORD" onclick="setCategory('WORD', this)">文档</button>
            <button class="filter-btn" data-type="VIDEO" onclick="setCategory('VIDEO', this)">视频</button>
            <button class="filter-btn" data-type="IMAGE" onclick="setCategory('IMAGE', this)">图片</button>
            <button class="filter-btn private-btn" data-type="PRIVATE" onclick="setCategory('PRIVATE', this)">
                <i class="fa-solid fa-lock" style="font-size:11px; margin-right:4px;"></i> 内部文件
            </button>
        </div>
    </div>

    <div class="media-grid" id="mediaGrid">
        <?php 
        foreach($files as $f): 
            $is_private = (isset($f['visibility']) && $f['visibility'] === 'private');
            
            // 【安全拦截】：如果该文件属于内部保密文件，且用户未验证，则彻底不向前端输出
            if ($is_private && !$is_verified) {
                continue; 
            }

            // 智能分析文件类型
            if ($is_private) {
                $type = 'PRIVATE';
                $icon = 'fa-file-shield';
            } else {
                $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf'])) { $type = 'PDF'; $icon = 'fa-file-pdf'; }
                elseif (in_array($ext, ['doc', 'docx'])) { $type = 'WORD'; $icon = 'fa-file-word'; }
                elseif ($f['file_type'] == 'image') { $type = 'IMAGE'; $icon = 'fa-file-image'; }
                elseif ($f['file_type'] == 'video') { $type = 'VIDEO'; $icon = 'fa-file-video'; }
                else { $type = 'OTHER'; $icon = 'fa-file-lines'; }
            }
        ?>
        <a href="media_download.php?id=<?= $f['id'] ?>&name=<?= urlencode($f['file_name']) ?>" 
           class="media-item <?= $is_private ? 'private-item' : '' ?>" 
           data-name="<?= strtolower(htmlspecialchars($f['file_name'])) ?>" 
           data-type="<?= $type ?>">
            
            <?php if ($is_private): ?>
                <div class="private-placeholder-img">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <div style="font-size:9px; font-weight:900; color:#8B5CF6; letter-spacing:1px; margin-top:2px;">内部资产</div>
                </div>
            <?php else: ?>
                <i class="fa-solid <?= $icon ?>"></i>
            <?php endif; ?>
            
            <span><?= htmlspecialchars($f['file_name']) ?></span>
        </a>
        <?php endforeach; ?>

        <div id="no-results">
            <i class="fa-solid fa-box-open"></i>
            <p>未找到符合条件的素材，请尝试其他关键词</p>
        </div>
    </div>
</div>

<div class="auth-modal-overlay" id="authOverlay">
    <div class="auth-modal">
        <i class="fa-solid fa-xmark close-modal" onclick="document.getElementById('authOverlay').style.display='none'"></i>
        <h3 class="auth-title"><i class="fa-solid fa-shield-halved" style="color:#8B5CF6"></i> 员工身份验证</h3>
        <p class="auth-desc">「内部文件」板块包含企业内部敏感资料及设计源文件，请验证您的员工账号以解除锁定。</p>
        <form method="POST">
            <input type="text" name="emp_account" class="auth-input" placeholder="请输入系统账号" required autocomplete="off">
            <input type="password" name="emp_password" class="auth-input" placeholder="请输入登录密码" required>
            <button type="submit" name="auth_submit" class="btn-auth-submit">验证身份并解锁</button>
        </form>
    </div>
</div>

<script>
    let activeCategory = 'ALL';

    function setCategory(cat, btn) {
        // 如果点击的是内部文件，且当前未认证，则弹出验证框，并终止后续逻辑
        if (cat === 'PRIVATE') {
            const isVerified = <?= $is_verified ? 'true' : 'false' ?>;
            if (!isVerified) {
                document.getElementById('authOverlay').style.display = 'flex';
                return; 
            }
        }

        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeCategory = cat;
        executeFilter();
    }

    function executeFilter() {
        const query = document.getElementById('fileSearch').value.toLowerCase().trim();
        const items = document.querySelectorAll('.media-item');
        let visibleCount = 0;

        items.forEach(item => {
            const name = item.getAttribute('data-name');
            const type = item.getAttribute('data-type');
            const matchesCat = (activeCategory === 'ALL' || type === activeCategory);
            const matchesSearch = name.includes(query);

            if (matchesCat && matchesSearch) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        const noResults = document.getElementById('no-results');
        noResults.style.display = (visibleCount === 0) ? 'block' : 'none';
    }

    // 如果通过表单 POST 验证成功，页面刷新后自动切入“内部文件”Tab展示
    <?php if (isset($auto_open_private) && $auto_open_private): ?>
        document.addEventListener("DOMContentLoaded", function() {
            const privateBtn = document.querySelector('.filter-btn[data-type="PRIVATE"]');
            if(privateBtn) setCategory('PRIVATE', privateBtn);
        });
    <?php endif; ?>
</script>

<?php include_once 'includes/footer.php'; ?>
</body>
</html>