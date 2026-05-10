<?php
/**
 * 代世集团 VMS - 操作日志审核 (手机端全适配优化版 - 数据自愈修复版)
 * 修复：1. 筛选栏响应式网格；2. 表格增加滚动保护；3. 触控热区优化；4.下拉人员信息增强(含角色)
 */

// 强制安全拦截：只有超管和系统管理员可以访问该页面
if (!in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin'])) {
    die("<div style='text-align:center; padding:50px; color:#EF4444; font-weight:bold;'>权限不足：仅限系统管理员及以上级别访问</div>");
}

$current_role = strtoupper($_SESSION['admin_role'] ?? '');

// 【自愈机制】：防止数据表不存在导致查询失效
$pdo->exec("CREATE TABLE IF NOT EXISTS operation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(50),
    action_item VARCHAR(100),
    action_details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// --- 1. 处理删除逻辑 ---
if (isset($_GET['del_id'])) {
    if ($current_role !== 'SUPER_ADMIN') {
        echo "<script>alert('权限不足，仅超管可删除日志'); location.href='?tab=logs';</script>";
        exit;
    }
    $del_id = (int)$_GET['del_id'];
    $pdo->prepare("DELETE FROM operation_logs WHERE id = ?")->execute([$del_id]);
    header("Location: ?tab=logs"); exit;
}

// --- 2. 预获取下拉数据 (增强版：联表获取部门、姓名和角色，并完美去重) ---
$raw_users = $pdo->query("SELECT a.username, a.real_name, a.role, e.department FROM admins a LEFT JOIN employees e ON a.username = e.system_user ORDER BY e.id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 定义角色映射表
$role_map = [
    'super_admin' => 'root',
    'admin'       => '管理员',
    'hr_admin'    => '行政人事管理员',
    'web_admin'   => '网站管理员',
    'employee'    => '普通员工'
];

$user_list = [];
$seen_usernames = [];
foreach($raw_users as $u) {
    if(!in_array($u['username'], $seen_usernames)) {
        $seen_usernames[] = $u['username'];
        $u['department'] = $u['department'] ?: '暂无部门';
        // 将底层角色映射为中文名称
        $u['role_name'] = $role_map[$u['role']] ?? '未知角色';
        $user_list[] = $u;
    }
}

// 获取操作项目列表保持不变
$item_list = $pdo->query("SELECT DISTINCT action_item FROM operation_logs ORDER BY action_item ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- 3. 构建筛选逻辑保持不变 ---
$where = [];
$params = [];
if (!empty($_GET['s_user'])) { $where[] = "admin_username = ?"; $params[] = $_GET['s_user']; }
if (!empty($_GET['s_item'])) { $where[] = "action_item = ?"; $params[] = $_GET['s_item']; }
if (!empty($_GET['s_date'])) { $where[] = "DATE(created_at) = ?"; $params[] = $_GET['s_date']; }

$sql = "SELECT * FROM operation_logs";
if ($where) { $sql .= " WHERE " . implode(" AND ", $where); }
$sql .= " ORDER BY id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<style>
    .log-header { margin-bottom: 20px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .log-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: clamp(18px, 4vw, 24px); }

    /* --- 1. 响应式筛选栏适配 --- */
    .filter-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: clamp(15px, 3vw, 25px); margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    
    .filter-form { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); 
        gap: 15px; 
        align-items: flex-end; 
    }
    
    .filter-group label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    /* 针对下拉框加入超长文本截断属性，保护移动端视觉 */
    .filter-select, .filter-input { 
        width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; 
        background: #FFF; box-sizing: border-box; color: #1E293B; outline: none; 
        text-overflow: ellipsis; white-space: nowrap; overflow: hidden;
    }
    .filter-select:focus { border-color: #1A3C6C; }
    
    .filter-actions { display: flex; align-items: center; gap: 15px; }
    .btn-filter { background: #1A3C6C; color: #FFF; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; font-size: 14px; }
    .btn-filter:active { transform: scale(0.98); }
    .btn-reset { color: #94A3B8; text-decoration: none; font-size: 13px; font-weight: 700; }

    /* --- 2. 日志列表滚动适配 --- */
    .log-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; overflow: hidden; }
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    
    .log-table { width: 100%; border-collapse: collapse; min-width: 850px; }
    .log-table th { background: #F8FAFC; text-align: left; padding: 15px; font-size: 11px; color: #94A3B8; border-bottom: 2px solid #F1F5F9; text-transform: uppercase; }
    .log-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 13px; vertical-align: top; }
    
    .user-tag { background: #F0F7FF; color: #1A3C6C; padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 11px; white-space: nowrap; }
    .detail-box { 
        color: #64748B; font-family: 'Courier New', Courier, monospace; font-size: 12px; 
        background: #FDFDFD; padding: 10px; border-radius: 8px; border: 1px solid #F1F5F9; 
        line-height: 1.5; word-break: break-all; max-width: 400px;
    }

    /* --- 手机端专项调整 --- */
    @media (max-width: 600px) {
        .filter-actions { width: 100%; justify-content: space-between; margin-top: 5px; }
        .btn-filter { flex: 1; text-align: center; }
        .detail-box { max-width: 250px; }
    }
</style>

<div class="log-header">
    <h2>操作日志审核中心</h2>
</div>

<div class="filter-card">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="logs">
        
        <div class="filter-group">
            <label>操作账号</label>
            <select name="s_user" class="filter-select">
                <option value="">-- 全部账号 --</option>
                <?php foreach($user_list as $u): ?>
                    <option value="<?= htmlspecialchars($u['username']) ?>" <?= (isset($_GET['s_user']) && $_GET['s_user']==$u['username'])?'selected':'' ?>>
                        [<?= htmlspecialchars($u['username']) ?>] <?= htmlspecialchars($u['real_name']) ?> - <?= htmlspecialchars($u['department']) ?> - (<?= $u['role_name'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>操作项目</label>
            <select name="s_item" class="filter-select">
                <option value="">-- 全部项目 --</option>
                <?php foreach($item_list as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= (isset($_GET['s_item']) && $_GET['s_item']==$item)?'selected':'' ?>>
                        <?= htmlspecialchars($item) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>操作日期</label>
            <input type="date" name="s_date" class="filter-input" value="<?= htmlspecialchars($_GET['s_date'] ?? '') ?>">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-magnifying-glass"></i> 执行筛选
            </button>
            <a href="?tab=logs" class="btn-reset">重置</a>
        </div>
    </form>
</div>

<div class="log-card">
    <div class="table-responsive">
        <table class="log-table">
            <thead>
                <tr>
                    <th width="180">操作时间 / IP</th>
                    <th width="140">执行人</th>
                    <th width="140">操作项目</th>
                    <th>详细痕迹</th>
                    <?php if($current_role === 'SUPER_ADMIN'): ?><th width="80" style="text-align:right;">管理</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($logs)): ?>
                    <?php foreach($logs as $l): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color: #1E293B;"><?= htmlspecialchars($l['created_at']) ?></div>
                            <div style="color:#94A3B8; font-size:11px; margin-top:4px;">
                                <i class="fa-solid fa-network-wired"></i> <?= htmlspecialchars($l['ip_address'] ?? '未知IP') ?>
                            </div>
                        </td>
                        <td><span class="user-tag"><?= htmlspecialchars($l['admin_username']) ?></span></td>
                        <td style="font-weight:700; color:#1A3C6C;"><?= htmlspecialchars($l['action_item']) ?></td>
                        <td>
                            <div class="detail-box"><?= htmlspecialchars($l['action_details']) ?></div>
                        </td>
                        <?php if($current_role === 'SUPER_ADMIN'): ?>
                        <td style="text-align:right;">
                            <a href="?tab=logs&del_id=<?= $l['id'] ?>" style="color:#EF4444; font-weight:800; text-decoration:none; padding: 8px;" onclick="return confirm('确定永久删除此条记录？')">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:60px; color:#94A3B8;">未发现匹配的日志记录</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>