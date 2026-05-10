<?php
/**
 * 代世集团 VMS - 现代登录网关 (手机端全适配版)
 * 功能：支持首次登录强制跳转改密逻辑
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
                // --- 核心逻辑：首次登陆识别 ---
                if ($user['password'] === 'FIRST_LOGIN') {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_user'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                    $_SESSION['must_reset'] = true;
                    
                    header("Location: index.php?tab=reset_password");
                    exit;
                } 
                
                // --- 常规登陆校验 ---
                $auth_success = false;
                if ($password === $user['password']) {
                    $auth_success = true;
                } elseif (password_verify($password, $user['password'])) {
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
            overflow: hidden; 
        }
        
        /* --- 响应式登录框 --- */
        .login-box { 
            width: 400px; 
            max-width: 88vw; /* 移动端自动缩小 */
            background: #FFF; 
            padding: clamp(30px, 10vw, 50px) clamp(25px, 8vw, 45px); 
            border-radius: 24px; 
            box-shadow: 0 15px 45px rgba(0,0,0,0.08); 
            text-align: center; 
            box-sizing: border-box;
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
            letter-spacing: 6px; 
            margin-top: 8px; 
        }

        .input-wrapper { position: relative; margin-bottom: 20px; text-align: left; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #CBD5E1; }
        
        .form-input { 
            width: 100%; 
            padding: 15px 15px 15px 45px; 
            border: 1px solid #E2E8F0; 
            border-radius: 14px; 
            font-size: 16px; /* 16px 可防止 iOS 聚焦时自动缩放 */
            box-sizing: border-box; 
            outline: none; 
            transition: 0.3s; 
            -webkit-appearance: none;
        }
        .form-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(26,60,108,0.08); }
        
        .error-hint { 
            background: #FEF2F2; color: #EF4444; 
            padding: 12px; border-radius: 12px; 
            font-size: 13px; margin-bottom: 20px; 
            border: 1px solid #FEE2E2; 
        }
        
        .btn-login { 
            width: 100%; padding: 15px; 
            background: var(--primary-color); 
            color: #FFF; border: none; 
            border-radius: 14px; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: 0.3s; 
            margin-top: 10px; 
            box-shadow: 0 10px 20px rgba(26,60,108,0.1);
        }
        .btn-login:active { transform: scale(0.98); opacity: 0.9; }
        
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