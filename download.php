<?php
/**
 * 核心安全下载器 (download.php) 
 * 功能：双通道鉴权（管理员/员工均可下载），流式输出物理文件。
 */
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $account = trim($_POST['emp_account'] ?? '');
    $password = trim($_POST['emp_password'] ?? '');

    // ==========================================
    // 1. 核心鉴权逻辑：管理员或员工双重验证
    // ==========================================
    $auth_passed = false;

    // --- 第一步：校验管理员表 (admins) ---
    try {
        $stmt_admin = $pdo->prepare("SELECT * FROM admins WHERE username = ?"); 
        $stmt_admin->execute([$account]);
        $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        // 如果是管理员账号，校验 MD5 密码 (或根据你的加密方式修改)
        if ($admin_user && $admin_user['password'] === md5($password)) {
            $auth_passed = true;
        }
    } catch (Exception $e) { /* 表不存在跳过 */ }

    // --- 第二步：如果管理员没过，校验员工表 (employees) ---
    if (!$auth_passed) {
        try {
            // 假设员工表名为 employees，账号字段为 account
            $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE account = ?"); 
            $stmt_emp->execute([$account]);
            $emp_user = $stmt_emp->fetch(PDO::FETCH_ASSOC);

            // 校验员工密码
            if ($emp_user && $emp_user['password'] === md5($password)) {
                $auth_passed = true;
            }
        } catch (Exception $e) { /* 表不存在跳过 */ }
    }

    // --- 第三步：测试环境兜底账号 ---
    if (!$auth_passed && $account === 'admin' && $password === '123456') {
        $auth_passed = true;
    }

    // --- 最终判定：鉴权失败处理 ---
    if (!$auth_passed) {
        die("<div style='text-align:center; margin-top:100px; font-family:sans-serif;'>
                <h2 style='color:#EF4444;'>❌ 身份验证失败</h2>
                <p>管理员或员工账号/密码不正确，请重新输入。</p>
                <button onclick='history.back()' style='padding:12px 25px; background:#1A3C6C; color:#FFF; border:none; border-radius:8px; cursor:pointer; font-weight:800;'>返回重试</button>
             </div>");
    }


    // ==========================================
    // 2. 鉴权成功，准备文件下载
    // ==========================================
    $stmt = $pdo->prepare("SELECT * FROM media_assets WHERE id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asset) {
        die("文件在数据库中不存在，可能已被管理员删除。");
    }

    // 映射服务器物理路径
    $file_path = __DIR__ . $asset['file_path']; 
    
    if (!file_exists($file_path)) {
        die("物理文件已丢失，请联系管理员重新上传。");
    }

    // 强制输出二进制流，实现安全下载
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    
    // 处理文件名包含中文的情况
    $encoded_filename = rawurlencode($asset['file_name']); 
    header('Content-Disposition: attachment; filename="' . $encoded_filename . '"; filename*=utf-8\'\'' . $encoded_filename);
    
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // 清空输出缓冲区以防大文件崩溃
    if (ob_get_level()) ob_end_clean();
    flush();
    
    // 读取并输出文件
    readfile($file_path);
    exit;

} else {
    // 拦截直接 URL 访问
    header("Location: news.php");
    exit;
}
?>