<?php
/**
 * 代世集团 VMS - 管理后台主架构 (移动端适配修复版)
 */
require_once '../config/db.php';
session_start();

// --- 1. 核心：内置注销逻辑 ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- 2. 权限检查 ---
function can($role) {
    return isset($_SESSION['admin_user']); 
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';

// --- 3. 动态获取当前用户信息用于右上角显示 ---
$current_user = $_SESSION['admin_user'] ?? '未知账号';
$current_role_key = $_SESSION['admin_role'] ?? 'employee';

// 全局权限名称映射表
$role_map = [
    'super_admin' => 'root',
    'admin'       => '管理员',
    'hr_admin'    => '行政人事管理员',
    'web_admin'   => '网站管理员',
    'employee'    => '普通员工'
];
$display_role = $role_map[$current_role_key] ?? '未知';

// --- 4. 全局拦截：获取用户最新数据并存入 Session ---
$stmt_pwd_check = $pdo->prepare("SELECT id, password, belong_company_id FROM admins WHERE username = ?");
$stmt_pwd_check->execute([$current_user]);
$current_admin_data = $stmt_pwd_check->fetch(PDO::FETCH_ASSOC);

if ($current_admin_data) {
    $_SESSION['belong_company_id'] = (int)$current_admin_data['belong_company_id'];
}

$require_password_setup = ($current_admin_data && empty(trim($current_admin_data['password'])));

// 处理强制修改密码提交
if (isset($_POST['set_initial_password'])) {
    $new_pwd = trim($_POST['initial_pwd']);
    if ($new_pwd !== '') {
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")
            ->execute([md5($new_pwd), $current_admin_data['id']]);
        echo "<script>alert('密码设置成功！请妥善保管您的新密码。'); location.href='index.php?tab={$tab}';</script>";
        exit;
    }
}

// --- 5. 处理常规修改密码提交 ---
if (isset($_POST['change_password'])) {
    $old_pwd = $_POST['old_pwd'] ?? '';
    $new_pwd = trim($_POST['new_pwd'] ?? '');
    $db_pwd = $current_admin_data['password'] ?? '';
    
    $is_old_pwd_correct = false;
    if ($old_pwd === $db_pwd || md5($old_pwd) === $db_pwd || password_verify($old_pwd, $db_pwd)) {
        $is_old_pwd_correct = true;
    }

    if ($is_old_pwd_correct && $new_pwd !== '') {
        $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?")->execute([md5($new_pwd), $current_user]);
        echo "<script>alert('密码修改成功！'); location.href='index.php?tab={$tab}';</script>";
        exit;
    } else {
        echo "<script>alert('原密码错误，修改失败！'); location.href='index.php?tab={$tab}';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>代世集团控制台 - VMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ds-blue: #1A3C6C; --ds-light-blue: #EEF2FF; --sidebar-width: 260px; --header-height: 70px; --bg-gray: #F8FAFC; }
        body { margin: 0; font-family: "Alibaba PuHuiTi 2.0", sans-serif; background: var(--bg-gray); display: flex; color: #334155; overflow-x: hidden; }

        /* 左侧菜单栏 */
        .sidebar { width: var(--sidebar-width); height: 100vh; background: #FFF; border-right: 1px solid #E2E8F0; display: flex; flex-direction: column; position: fixed; left: 0; top: 0; z-index: 1001; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-logo { padding: 25px; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; }
        .sidebar-logo h1 { font-size: 18px; margin: 0; color: var(--ds-blue); font-weight: 900; }
        .nav-container { flex: 1; overflow-y: auto; padding: 15px; }
        .nav-group-title { font-size: 11px; font-weight: 800; color: #94A3B8; margin: 20px 0 8px 10px; text-transform: uppercase; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; text-decoration: none; color: #64748B; font-size: 14px; font-weight: 600; border-radius: 12px; margin-bottom: 2px; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { background: var(--ds-light-blue); color: var(--ds-blue); }
        .nav-link.site-home { background: #F0F7FF; color: var(--ds-blue) !important; margin-bottom: 20px; border: 1px solid #CFE5FF; font-weight: 800; }

        /* 移动端专用关闭按钮 */
        .mobile-close { display: none; font-size: 20px; color: #94A3B8; cursor: pointer; }

        /* 遮罩层 */
        .sidebar-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 1000; display: none; opacity: 0; transition: 0.3s; }

        /* 主内容区 */
        .main-wrapper { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; display: flex; flex-direction: column; width: 100%; }
        .top-header { height: var(--header-height); background: #FFF; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: sticky; top: 0; z-index: 900; }
        
        .header-left { display: flex; align-items: center; gap: 15px; }
        .menu-toggle { display: none; font-size: 20px; color: var(--ds-blue); cursor: pointer; padding: 10px; margin-left: -10px; }

        .user-info { display: flex; align-items: center; gap: 15px; }
        .nav-action-btn { color: #64748B; text-decoration: none; font-size: 13px; font-weight: 800; display: flex; align-items: center; gap: 5px; padding: 8px; border-radius: 8px; cursor: pointer; }
        .nav-action-btn:hover { background: #F1F5F9; color: var(--ds-blue); }
        .logout-btn { color: #EF4444; }
        .admin-badge { font-size: 10px; padding: 2px 8px; border-radius: 6px; font-weight: 900; }
        .badge-super { background: #FEF2F2; color: #EF4444; }
        .badge-normal { background: var(--ds-light-blue); color: var(--ds-blue); }
        .content-area { padding: 25px; flex: 1; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; opacity: 1; }
            .main-wrapper { margin-left: 0; }
            .menu-toggle { display: block; }
            .mobile-close { display: block; }
            .top-header { padding: 0 15px; }
            .user-name { display: none; } /* 手机端隐藏名字，仅显示角色标签 */
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="overlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div>
                <h1>代世集团</h1>
                <span style="font-size:10px; color:#94A3B8;">VMS 管理系统</span>
            </div>
            <i class="fa-solid fa-xmark mobile-close" id="closeSidebar"></i>
        </div>

        <nav class="nav-container">
            <a href="../index.php" class="nav-link site-home" target="_blank">
                <i class="fa-solid fa-paper-plane"></i><span>返回官网首页</span>
            </a>

            <?php if (in_array($current_role_key, ['super_admin', 'admin', 'hr_admin'])): ?>
            <div class="nav-group-title">核心监控</div>
            <a href="?tab=dashboard" class="nav-link <?= $tab == 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i><span>数据概览</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($current_role_key, ['super_admin', 'admin', 'web_admin'])): ?>
            <div class="nav-group-title">官网与品牌</div>
            <a href="?tab=site_config" class="nav-link <?= $tab == 'site_config' ? 'active' : '' ?>"><i class="fa-solid fa-gears"></i><span>官网全量配置</span></a>
            <a href="?tab=business" class="nav-link <?= $tab == 'business' ? 'active' : '' ?>"><i class="fa-solid fa-layer-group"></i><span>集团业务管理</span></a>
            <a href="?tab=news" class="nav-link <?= $tab == 'news' ? 'active' : '' ?>"><i class="fa-solid fa-file-pen"></i><span>新闻管理中心</span></a>
            <a href="?tab=assets" class="nav-link <?= $tab == 'assets' ? 'active' : '' ?>"><i class="fa-solid fa-photo-film"></i><span>媒体素材管理</span></a>
            <?php endif; ?>

            <?php if (in_array($current_role_key, ['super_admin', 'admin', 'hr_admin'])): ?>
            <div class="nav-group-title">人力资源管理</div>
            <a href="?tab=jobs" class="nav-link <?= $tab == 'jobs' ? 'active' : '' ?>"><i class="fa-solid fa-bullhorn"></i><span>招聘发布中心</span></a>
            <a href="?tab=resumes" class="nav-link <?= $tab == 'resumes' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice"></i><span>简历管理中心</span></a>
            <a href="?tab=hr" class="nav-link <?= $tab == 'hr' ? 'active' : '' ?>"><i class="fa-solid fa-user-tie"></i><span>人事档案管理</span></a>
            <a href="?tab=org" class="nav-link <?= $tab == 'org' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap"></i><span>组织架构管理</span></a>
            <?php endif; ?>

            <?php if (in_array($current_role_key, ['super_admin', 'admin', 'hr_admin'])): ?>
            <div class="nav-group-title">资产管理</div>
            <a href="?tab=fixed_assets" class="nav-link <?= $tab == 'fixed_assets' ? 'active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i><span>固定资产管理</span></a>
            <a href="?tab=items" class="nav-link <?= $tab == 'items' ? 'active' : '' ?>"><i class="fa-solid fa-hand-holding-hand"></i><span>物品借还管理</span></a>
            <?php endif; ?>

            <?php if (in_array($current_role_key, ['super_admin', 'admin'])): ?>
            <div class="nav-group-title">系统与权限</div>
            <a href="?tab=admins" class="nav-link <?= $tab == 'admins' ? 'active' : '' ?>"><i class="fa-solid fa-user-shield"></i><span>账号管理中心</span></a>
            <a href="?tab=logs" class="nav-link <?= $tab == 'logs' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>操作日志审计</span></a>
            <?php endif; ?>

            <?php if ($current_role_key === 'super_admin'): ?>
            <div class="nav-group-title">运维工具</div>
            <a href="http://47.83.138.43:29977/dsjtbd" target="_blank" class="nav-link"><i class="fa-solid fa-server"></i><span>宝塔面板管理</span></a>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="top-header">
            <div class="header-left">
                <i class="fa-solid fa-bars-staggered menu-toggle" id="menuToggle"></i>
                <div class="breadcrumb">VMS / <b><?= strtoupper($tab) ?></b></div>
            </div>
            <div class="user-info">
                <span class="user-name" style="font-weight: 800; font-size: 14px;">
                    <i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($current_user) ?>
                </span>
                <span class="admin-badge <?= ($current_role_key === 'super_admin') ? 'badge-super' : 'badge-normal' ?>">
                    <?= $display_role ?>
                </span>
                <a class="nav-action-btn" onclick="document.getElementById('normalChangePwdModal').style.display='flex';">
                    <i class="fa-solid fa-key"></i>
                </a>
                <a href="?action=logout" class="nav-action-btn logout-btn" onclick="return confirm('确定退出系统吗？')">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </header>

        <main class="content-area">
            <?php 
                $allowed_tabs = ['dashboard', 'site_config', 'business', 'news', 'assets', 'jobs', 'resumes', 'hr', 'org', 'fixed_assets', 'items', 'admins', 'logs'];
                if (in_array($tab, $allowed_tabs)) {
                    include "tabs/{$tab}.php";
                } else {
                    echo "非法请求";
                }
            ?>
        </main>
    </div>

    <div id="normalChangePwdModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
        <div style="background:#FFF; padding:30px; border-radius:20px; width:400px; max-width:90%;">
            <h3 style="margin-top:0;">修改密码</h3>
            <form method="POST">
                <label style="display:block; font-size:12px; margin-bottom:5px;">原密码</label>
                <input type="password" name="old_pwd" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #DDD; border-radius:8px; box-sizing:border-box;">
                <label style="display:block; font-size:12px; margin-bottom:5px;">新密码</label>
                <input type="password" name="new_pwd" required minlength="6" style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #DDD; border-radius:8px; box-sizing:border-box;">
                <button type="submit" name="change_password" style="width:100%; background:var(--ds-blue); color:#FFF; border:none; padding:12px; border-radius:8px; cursor:pointer;">确认修改</button>
                <button type="button" onclick="document.getElementById('normalChangePwdModal').style.display='none';" style="width:100%; background:none; border:none; color:#64748B; margin-top:10px; cursor:pointer;">取消</button>
            </form>
        </div>
    </div>

    <?php if($require_password_setup): ?>
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:3000; display:flex; align-items:center; justify-content:center;">
        <div style="background:#FFF; padding:40px; border-radius:20px; width:400px; max-width:90%; text-align:center;">
            <h3>请设置您的初始密码</h3>
            <p style="color:#64748B; font-size:13px;">首次登录必须设置新密码以激活账号</p>
            <form method="POST">
                <input type="password" name="initial_pwd" required minlength="6" placeholder="设置新密码" style="width:100%; padding:12px; border:1px solid #DDD; border-radius:8px; margin:20px 0; box-sizing:border-box;">
                <button type="submit" name="set_initial_password" style="width:100%; background:var(--ds-blue); color:#FFF; border:none; padding:12px; border-radius:8px;">确认设置</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const menuToggle = document.getElementById('menuToggle');
        const closeSidebar = document.getElementById('closeSidebar');

        // 切换菜单函数
        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            // 防止背景滚动
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : 'auto';
        }

        menuToggle.addEventListener('click', toggleMenu);
        closeSidebar.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);

        // 点击导航链接后自动关闭菜单（针对手机端）
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if(window.innerWidth <= 1024) toggleMenu();
            });
        });
    </script>

</body>
</html>