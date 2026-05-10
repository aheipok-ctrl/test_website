<?php
/**
 * 代世集团 VMS - 现代登录网关 (终极修复版：加入 MD5 密码校验)
 * 功能：支持首次登录强制跳转改密逻辑，完美适配移动端触控
 */
session_start();
require_once __DIR__ . '/../config/db.php';

// 如果已经登录，直接进入后台
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $error = '请输入管理员账号';
    } else {
        try {
            // 查询用户信息
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // --- 首次登陆识别 ---
                // 识别数据库中密码为空的情况（兼容 NULL 和 空字符串 ''）
                if (empty(trim($user['password']))) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_user'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'] ?? 'employee';
                    
                    // 直接跳入后台，后台的全局强制拦截弹窗会接管并要求设置密码
                    header("Location: index.php");
                    exit;
                } 
                
                // --- 常规登陆校验 (核心修复区) ---
                $auth_success = false;
                
                if ($password === $user['password']) {
                    // 1. 兼容早期的明文密码
                    $auth_success = true;
                } elseif (md5($password) === $user['password']) {
                    // 2. 【核心修复】：加入 MD5 哈希比对！因为后台弹窗存入的是 md5() 加密后的密码
                    $auth_success = true;
                } elseif (password_verify($password, $user['password'])) {
                    // 3. 兼容 bcrypt 等现代加密方式
                    $auth_success = true;
                }

                if ($auth_success) {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_user'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                    header("Location: index.php");
                    exit;
                } else {
                    $error = '密码验证失败，请核对后再试';
                }
            } else {
                $error = '该管理账号不存在';
            }
        } catch (PDOException $e) {
            $error = '数据库连接失败，请检查配置';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>登录 - 代世集团控制台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-color: #1A3C6C; --text-muted: #94A3B8; }
        body { 
            margin: 0; padding: 0; 
            font-family: "PingFang SC", "Microsoft YaHei", sans-serif; 
            height: 100vh; display: flex; align-items: center; justify-content: center; 
            background: #F4F7FA; 
            overflow: hidden; /* 防止移动端回弹 */
        }
        
        /* --- 登录框响应式适配 --- */
        .login-box { 
            width: 400px; 
            max-width: 88vw; /* 手机端自动缩小 */
            background: #FFF; 
            padding: clamp(30px, 10vw, 50px) clamp(25px, 8vw, 45px); 
            border-radius: 24px; 
            box-shadow: 0 15px 45px rgba(0,0,0,0.08); 
            text-align: center; 
            box-sizing: border-box;
            transition: 0.3s;
        }
        
        .brand-area { margin-bottom: clamp(30px, 8vw, 40px); }
        .brand-title { 
            color: var(--primary-color); 
            font-size: clamp(24px, 6vw, 30px); 
            font-weight: 900; 
            letter-spacing: 4px; 
            margin: 0; 
        }
        .brand-subtitle { 
            color: var(--text-muted); 
            font-size: 13px; 
            font-weight: 800; 
            letter-spacing: clamp(4px, 1.5vw, 6px); 
            margin-top: 8px; 
        }

        .input-wrapper { position: relative; margin-bottom: 20px; text-align: left; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #CBD5E1; }
        .form-input { 
            width: 100%; 
            padding: 15px 15px 15px 45px; 
            border: 1px solid #E2E8F0; 
            border-radius: 14px; 
            font-size: 16px; /* 移动端 16px 防止输入时页面自动放大 */
            box-sizing: border-box; 
            outline: none; 
            transition: 0.3s; 
            -webkit-appearance: none; /* 清除iOS默认样式 */
        }
        .form-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(26,60,108,0.08); }
        
        .error-hint { 
            background: #FEF2F2; color: #EF4444; 
            padding: 12px; border-radius: 12px; 
            font-size: 13px; margin-bottom: 20px; 
            border: 1px solid #FEE2E2; 
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .btn-login { 
            width: 100%; padding: clamp(14px, 4vw, 16px); 
            background: var(--primary-color); 
            color: #FFF; border: none; 
            border-radius: 14px; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: 0.3s; 
            margin-top: 10px; 
            box-shadow: 0 10px 20px rgba(26,60,108,0.15);
        }
        .btn-login:active { transform: scale(0.98); opacity: 0.95; }
        
        .back-link { 
            display: inline-block; 
            margin-top: 30px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: bold; 
            transition: 0.3s; 
            padding: 10px;
        }
        .back-link:hover { color: var(--primary-color); }

        /* --- 小屏专项调整 --- */
        @media (max-height: 600px) {
            .login-box { padding: 30px 25px; margin-top: 10px; }
            .brand-area { margin-bottom: 20px; }
            .back-link { margin-top: 15px; }
        }
    </style>
</head>
<body>

<div class="login-box">
    <div class="brand-area">
        <h1 class="brand-title">代世集团</h1>
        <div class="brand-subtitle">控制台管理系统</div>
    </div>

    <?php if ($error): ?>
        <div class="error-hint"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="input-wrapper">
            <i class="fa-solid fa-user-shield"></i>
            <input type="text" name="username" class="form-input" placeholder="管理员账号" required>
        </div>
        
        <div class="input-wrapper">
            <i class="fa-solid fa-key"></i>
            <input type="password" name="password" class="form-input" placeholder="登录密码">
        </div>

        <button type="submit" class="btn-login">进入控制台</button>
    </form>

    <a href="/" class="back-link"><i class="fa-solid fa-arrow-left"></i> 返回官网首页</a>
</div>

</body>
</html>