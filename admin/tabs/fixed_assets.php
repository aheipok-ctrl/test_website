<?php
/**
 * 代世集团 VMS - 固定资产档案 (纵向权限穿透版)
 * 修复：1. 自动标记资产归属；2. 跨公司穿透查询；3. 响应式布局
 */

if (!can('admin')) die("权限不足");

$current_admin_role = $_SESSION['admin_role'] ?? 'employee';
$my_belong_id = $_SESSION['belong_company_id'] ?? 0;

// --- 【权限穿透核心函数】 ---
// 获取当前管理员有权查看的所有部门/公司 ID 列表
if (!function_exists('getVisibleDeptIds')) {
    function getVisibleDeptIds($pdo, $rootId) {
        if ($rootId == 0) return [];
        $all = $pdo->query("SELECT id, parent_id FROM departments")->fetchAll(PDO::FETCH_ASSOC);
        $res = [$rootId]; $stack = [$rootId];
        while (!empty($stack)) {
            $curr = array_pop($stack);
            foreach ($all as $d) {
                if ($curr == $d['parent_id']) {
                    $res[] = (int)$d['id'];
                    $stack[] = (int)$d['id'];
                }
            }
        }
        return array_unique($res);
    }
}

// --- 1. 数据库自愈：确保存在归属字段 ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fixed_assets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('belong_company_id', $cols)) {
        $pdo->exec("ALTER TABLE fixed_assets ADD COLUMN belong_company_id INT DEFAULT 0 AFTER id");
    }
} catch (Exception $e) {}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 准备权限 SQL
$auth_where = "1=1";
$auth_params = [];
if ($current_admin_role === 'hr_admin') {
    $allowed_ids = getVisibleDeptIds($pdo, $my_belong_id);
    if (!empty($allowed_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
        $auth_where .= " AND belong_company_id IN ($placeholders)";
        $auth_params = $allowed_ids;
    } else {
        $auth_where .= " AND 1=0";
    }
}

// --- 2. 处理新增逻辑 ---
if (isset($_POST['add_asset'])) {
    $name  = trim($_POST['asset_name'] ?? '');
    $type  = $_POST['asset_type'] ?? '办公设备';
    $sn    = trim($_POST['serial_number'] ?? '');
    $date  = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
    $price = (float)($_POST['purchase_price'] ?? 0);
    $user  = trim($_POST['custodian'] ?? '');
    $status = $_POST['status'] ?? '在用';
    
    // 如果是 hr_admin，强制归属于其所在公司；如果是 root/admin，可扩展为选填（目前默认为顶级）
    $final_belong_id = ($current_admin_role === 'hr_admin') ? $my_belong_id : 0;

    $auto_code = "DS" . date('Ymd') . strtoupper(substr(uniqid(), -4));

    if (!empty($name)) {
        try {
            $sql = "INSERT INTO fixed_assets (belong_company_id, asset_code, asset_name, asset_type, serial_number, purchase_date, purchase_price, custodian, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$final_belong_id, $auto_code, $name, $type, $sn, $date, $price, $user, $status]);
            echo "<script>alert('资产已成功入库！'); location.href='?tab=fixed_assets';</script>";
            exit;
        } catch (PDOException $e) { $db_error = $e->getMessage(); }
    }
}

// --- 3. 处理删除逻辑 (增加越权删除检查) ---
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    // 安全校验
    $target_belong = $pdo->query("SELECT belong_company_id FROM fixed_assets WHERE id = $del_id")->fetchColumn();
    if ($current_admin_role === 'hr_admin' && !in_array($target_belong, getVisibleDeptIds($pdo, $my_belong_id))) {
        die("越权操作：无权删除此资产");
    }
    $pdo->prepare("DELETE FROM fixed_assets WHERE id = ?")->execute([$del_id]);
    echo "<script>location.href='?tab=fixed_assets';</script>"; exit;
}

// --- 4. 获取受限后的数据列表 ---
$sql = "SELECT a.*, d.name as company_name 
        FROM fixed_assets a 
        LEFT JOIN departments d ON a.belong_company_id = d.id 
        WHERE $auth_where 
        ORDER BY a.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($auth_params);
$assets = $stmt->fetchAll();
?>

<style>
    .header-box { margin-bottom: 20px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .header-box h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: clamp(18px, 4vw, 24px); }
    .main-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: clamp(15px, 4vw, 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 15px; background: #F8FAFC; padding: clamp(15px, 3vw, 25px); border-radius: 12px; margin-bottom: 35px; border: 1px solid #F1F5F9; align-items: flex-end; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    .form-input { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #FFF; box-sizing: border-box; outline: none; }
    .form-input:focus { border-color: #1A3C6C; }
    .btn-submit { background: #1A3C6C; color: #FFF; border: none; padding: 14px; border-radius: 8px; cursor: pointer; font-weight: 800; width: 100%; font-size: 15px; transition: 0.3s; }
    .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; border: 1px solid #F1F5F9; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .data-table th { text-align: left; padding: 15px; color: #94A3B8; border-bottom: 2px solid #F1F5F9; font-size: 11px; text-transform: uppercase; }
    .data-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 13px; color: #475569; }
    .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .st-inuse { background: #DCFCE7; color: #166534; }
    .st-idle { background: #F1F5F9; color: #64748B; }
    .company-tag { color: #8B5CF6; font-weight: 800; font-size: 11px; background: #F5F3FF; padding: 2px 6px; border-radius: 4px; }
</style>

<div class="header-box">
    <h2>固定资产管理中心 <?php if($current_admin_role == 'hr_admin') echo "<small style='font-size:12px; background:#EEF2FF; padding:4px 10px; border-radius:5px;'>辖区管控模式</small>"; ?></h2>
</div>

<div class="main-card">
    <?php if(isset($db_error)): ?>
        <div style="background:#FEF2F2; color:#991B1B; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #FCA5A5;">
            <strong>写入失败：</strong> <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="form-grid">
        <div class="form-group">
            <label>资产名称</label>
            <input type="text" name="asset_name" class="form-input" placeholder="如：办公电脑" required>
        </div>
        <div class="form-group">
            <label>资产类别</label>
            <select name="asset_type" class="form-input">
                <option value="办公设备">办公设备</option>
                <option value="家具器材">家具器材</option>
                <option value="数码电子">数码电子</option>
                <option value="车辆运输">车辆运输</option>
            </select>
        </div>
        <div class="form-group">
            <label>序列号/型号</label>
            <input type="text" name="serial_number" class="form-input" placeholder="SN码">
        </div>
        <div class="form-group">
            <label>购置金额 (元)</label>
            <input type="number" step="0.01" name="purchase_price" class="form-input" placeholder="0.00">
        </div>
        <div class="form-group">
            <label>购置日期</label>
            <input type="date" name="purchase_date" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label>保管人</label>
            <input type="text" name="custodian" class="form-input" placeholder="姓名">
        </div>
        <div class="form-group">
            <label>当前状态</label>
            <select name="status" class="form-input">
                <option value="在用">在用</option>
                <option value="闲置">闲置</option>
                <option value="维修">维修</option>
                <option value="报废">报废</option>
            </select>
        </div>
        <div class="form-group">
            <button type="submit" name="add_asset" class="btn-submit">
                <i class="fa-solid fa-plus"></i> 录入档案
            </button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>资产编号</th>
                    <th>归属单位</th>
                    <th>资产详情</th>
                    <th>购置日期</th>
                    <th>保管人</th>
                    <th>状态</th>
                    <th style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($assets) > 0): ?>
                    <?php foreach($assets as $a): ?>
                    <tr>
                        <td><code style="color:#1A3C6C; font-weight:bold;"><?= htmlspecialchars($a['asset_code'] ?: '--') ?></code></td>
                        <td><span class="company-tag"><?= htmlspecialchars($a['company_name'] ?: '代世集团') ?></span></td>
                        <td><strong><?= htmlspecialchars($a['asset_name']) ?></strong><br><small style="color:#94A3B8;"><?= $a['asset_type'] ?></small></td>
                        <td style="font-size: 12px;"><?= $a['purchase_date'] ?></td>
                        <td><?= htmlspecialchars($a['custodian'] ?: '公司') ?></td>
                        <td>
                            <span class="badge <?= $a['status']=='在用'?'st-inuse':'st-idle' ?>"><?= $a['status'] ?></span>
                        </td>
                        <td style="text-align:right;">
                            <a href="?tab=fixed_assets&del_id=<?= $a['id'] ?>" style="color:#EF4444; font-size:12px; font-weight:800; text-decoration:none;" onclick="return confirm('确定删除该资产档案？')">
                                <i class="fa-solid fa-trash-can"></i> 删除
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:50px; color:#94A3B8;">未发现您管辖范围内的资产档案</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>