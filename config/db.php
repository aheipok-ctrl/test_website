<?php
/**
 * 代世集团 VMS - 核心配置文件
 * 包含：数据库连接(PDO)、全局权限校验、全站操作审计
 */

// --- 1. 数据库基础配置 ---
$host = '127.0.0.1';
$db   = 'dsjtbd_web'; 
$user = 'dsjtbd_web';
$pass = 'Daishi0010';
$charset = 'utf8mb4';

// --- 2. 建立 PDO 数据库连接 ---
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // 开启异常模式
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // 默认返回关联数组
    PDO::ATTR_EMULATE_PREPARES   => false,                     // 禁用模拟预处理，提高安全性
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 如果连接失败，直接停止并报错
    die("数据库连接失败: " . $e->getMessage());
}

// --- 3. 全局权限检查函数 (解决菜单显示与非法访问拦截) ---
if (!function_exists('can')) {
    function can($permission_type) {
        // 验证 Session 是否存在且格式正确
        if (!isset($_SESSION['admin_user']) || !is_array($_SESSION['admin_user'])) {
            return false;
        }

        // 统一转大写，处理 super_admin / SUPER_ADMIN 大小写不一致问题
        $role = strtoupper($_SESSION['admin_user']['role'] ?? '');
        
        // 解析授权模块（确保 null 值保护）
        $permitted = json_decode($_SESSION['admin_user']['permitted_tabs'] ?? '[]', true);
        if (!is_array($permitted)) $permitted = [];

        // 【超级管理员】 拥有所有权限
        if ($role === 'SUPER_ADMIN') {
            return true;
        }

        // 【仪表盘】 只要登录成功即可访问
        if ($permission_type === 'dashboard') {
            return true;
        }

        // 【系统管理员】 拥有除“日志审核(logs)”外的所有权限
        if ($role === 'SYSTEM_ADMIN') {
            return ($permission_type !== 'logs');
        }

        // 【部门负责人/项目管理员】 仅可见勾选授权的模块
        // 逻辑包含：dept_head, project_admin
        if ($role === 'DEPT_HEAD' || $role === 'PROJECT_ADMIN') {
            return in_array($permission_type, $permitted);
        }

        return false;
    }
}

// --- 4. 全局审计函数 (用于记录管理员的一举一动) ---
if (!function_exists('record_log')) {
    function record_log($pdo, $item, $details) {
        // 只有登录用户才能触发日志记录
        if (!isset($_SESSION['admin_user'])) return;
        
        try {
            $username = $_SESSION['admin_user']['username'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            $stmt = $pdo->prepare("INSERT INTO operation_logs (admin_username, action_item, action_details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $item, $details, $ip]);
        } catch (Exception $e) {
            // 日志写入失败通常不打断主业务流程
            error_log("日志记录失败: " . $e->getMessage());
        }
    }
}