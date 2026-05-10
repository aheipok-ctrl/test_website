<?php
/**
 * 代世集团 VMS - 初始化安全设置 (首次登录改密 - 移动端适配版)
 */

// 仅验证登录状态，不验证角色
if (!isset($_SESSION['admin_id'])) {
    die("未登录，请返回登录页。");
}

$msg = '';
$msg_type = '';

if (isset($_POST['update_pwd'])) {
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    if (strlen($new_pwd) < 6) {
        $msg = "为了安全，密码长度不能少于 6 位";
        $msg_type = "error";
    } elseif ($new_pwd !== $confirm_pwd) {
        $msg = "两次输入的密码不一致";
        $msg_type = "error";
    } else {
        try {
            // 使用安全哈希加密密码
            $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_pwd, $_SESSION['admin_id']]);
            
            // 清除强制改密标记
            if (isset($_SESSION['must_reset'])) {
                unset($_SESSION['must_reset']);
            }
            
            echo "<script>alert('安全设置成功！请使用新密码重新登录。'); location.href='logout.php';</script>";
            exit;
        } catch (Exception $e) {
            $msg = "数据库更新失败：" . $e->getMessage();
            $msg_type = "error";
        }
    }
}
?>

<style>
    /* --- 响应式容器适配 --- */
    .reset-box { 
        max-width: 450px; 
        width: 90%; /* 移动端自动撑满 */
        margin: clamp(30px, 8vh, 80px) auto; 
        background: #FFF; 
        padding: clamp(25px, 5vw, 40px); 
        border-radius: 24px; 
        box-shadow: 0 15px 40px rgba(26,60,108,0.08); 
        border: 1px solid #E2E8F0; 
        box-sizing: border-box;
    }

    .reset-header { text-align: center; margin-bottom: 30px; }
    .reset-header i { 
        font-size: clamp(36px, 8vw, 48px); 
        color: #1A3C6C; 
        margin-bottom: 15px; 
        display: block; 
    }
    .reset-header h2 { margin: 0; color: #1A3C6C; font-size: clamp(18px, 4vw, 22px); font-weight: 900; }
    .reset-header p { color: #94A3B8; font-size: 13px; margin-top: 8px; line-height: 1.5; }

    .form-group { margin-bottom: 20px; text-align: left; }
    .form-group label { display: block; font-size: 12px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    
    /* 移动端 16px 字号可防止 iOS 聚焦时自动放大页面 */
    .form-control { 
        width: 100%; 
        padding: 14px 15px; 
        border: 1px solid #E2E8F0; 
        border-radius: 12px; 
        box-sizing: border-box; 
        outline: none; 
        transition: 0.3s; 
        font-size: 16px; 
        background: #FDFDFD;
    }
    .form-control:focus { border-color: #1A3C6C; background: #FFF; box-shadow: 0 0 0 4px rgba(26,60,108,0.08); }

    .msg-box { 
        padding: 12px; 
        border-radius: 12px; 
        font-size: 13px; 
        margin-bottom: 20px; 
        text-align: center; 
        background: #FEF2F2; 
        color: #EF4444; 
        border: 1px solid #FEE2E2; 
        animation: shake 0.4s ease;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .btn-save { 
        width: 100%; 
        padding: 16px; 
        background: #1A3C6C; 
        color: #FFF; 
        border: none; 
        border-radius: 14px; 
        font-size: 16px; 
        font-weight: 800; 
        cursor: pointer; 
        transition: 0.3s; 
        box-shadow: 0 8px 15px rgba(26,60,108,0.15);
    }
    .btn-save:active { transform: scale(0.98); opacity: 0.95; }
    
    /* 针对超窄屏手机的微调 */
    @media (max-width: 360px) {
        .reset-box { padding: 20px; }
        .reset-header p { font-size: 12px; }
    }
</style>

<div class="reset-box">
    <div class="reset-header">
        <i class="fa-solid fa-shield-halved"></i>
        <h2>初始化安全设置</h2>
        <p>系统检测到您是首次登录管理账号</p>
        <p style="color:#EF4444; font-weight:800;">请先设置您的个人登录密码</p>
    </div>

    <?php if ($msg): ?>
        <div class="msg-box">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label>设置新密码</label>
            <input type="password" name="new_password" class="form-control" placeholder="6 位以上字母或数字" required>
        </div>
        
        <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="再次输入以确认" required>
        </div>

        <button type="submit" name="update_pwd" class="btn-save">
            保存并完成初始化
        </button>
    </form>
</div>