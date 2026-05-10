<?php
/**
 * 代世集团 VMS - 账号权限管理 (严格等级制 & 实体穿透版)
 * 逻辑：1. 向下管理（高管低）；2. 同级禁止互动；3. 禁止提权（不能分配高于自己的角色）
 */
if (!can('admin')) die("权限不足");

// --- 1. 定义角色等级常量 ---
$role_ranks = [
    'super_admin' => 100, // root
    'admin'       => 80,  // 系统管理员
    'hr_admin'    => 50,  // 行政人事管理员
    'web_admin'   => 50,  // 网站管理员
    'employee'    => 10   // 普通员工
];

// 获取当前登录者的等级
$current_role = $_SESSION['admin_role'] ?? 'employee';
$my_rank = $role_ranks[$current_role] ?? 0;
$error = '';
$success = '';

// --- 2. 获取基础数据 ---
$account_pool = [];
$company_entities = [];
try {
    // A. 账号池拉取
    $emps = $pdo->query("SELECT system_user as acc, name FROM employees WHERE system_user IS NOT NULL AND system_user != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach($emps as $e) { $account_pool[$e['acc']] = $e['name']; }
    $adms = $pdo->query("SELECT username as acc, real_name as name FROM admins")->fetchAll(PDO::FETCH_ASSOC);
    foreach($adms as $a) { $account_pool[$a['acc']] = $a['name']; }

    // B. 拉取公司/分公司实体
    $all_nodes = $pdo->query("SELECT id, name, level FROM departments WHERE level IN (1, 2) ORDER BY level ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach($all_nodes as $node) {
        if(($node['level'] == 1) || (mb_strpos($node['name'], '公司') !== false)) {
            $company_entities[] = $node;
        }
    }
} catch (Exception $e) {}

// --- 3. 处理保存逻辑 (编辑/新增) ---
if (isset($_POST['save_account'])) {
    $username = trim($_POST['username']);
    $real_name = trim($_POST['real_name']);
    $target_role = $_POST['role'];
    $password = $_POST['password'];
    $belong_id = ($target_role === 'hr_admin') ? (int)$_POST['belong_company_id'] : 0;
    
    // 权限校验 A：新分配的角色等级是否超过了自己？
    $new_role_rank = $role_ranks[$target_role] ?? 0;
    if ($new_role_rank > $my_rank) {
        $error = "非法提权：您无权创建或分配高于您自身等级的角色！";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, role FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $existing_admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_admin) {
                // 编辑模式权限校验 B：目标账号现在的等级是否比我高或跟我一样？
                $target_current_rank = $role_ranks[$existing_admin['role']] ?? 0;
                if ($target_current_rank >= $my_rank && $current_role !== 'super_admin') {
                    $error = "管理权限锁定：您只能修改等级低于您的账号（同级或上级无法改动）";
                } else {
                    // 执行更新
                    $sql = "UPDATE admins SET real_name = ?, role = ?, belong_company_id = ? " . (!empty($password) ? ", password = ?" : "") . " WHERE id = ?";
                    $params = [$real_name, $target_role, $belong_id];
                    if(!empty($password)) $params[] = md5($password);
                    $params[] = $existing_admin['id'];
                    $pdo->prepare($sql)->execute($params);
                    $success = "账号 [{$username}] 权限及归属更新成功";
                }
            } else {
                // 新增模式
                $pass = !empty($password) ? md5($password) : md5('ds123456');
                $pdo->prepare("INSERT INTO admins (username, password, real_name, role, belong_company_id) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$username, $pass, $real_name, $target_role, $belong_id]);
                $success = "新账号分配成功";
            }
        } catch (Exception $e) { $error = "操作失败：" . $e->getMessage(); }
    }
}

// --- 4. 密码重置逻辑 (逻辑同上) ---
if (isset($_POST['do_reset_pwd'])) {
    $target_id = (int)$_POST['reset_admin_id'];
    $t_data = $pdo->query("SELECT role, username FROM admins WHERE id = $target_id")->fetch(PDO::FETCH_ASSOC);
    if ($t_data && $my_rank > ($role_ranks[$t_data['role']] ?? 0)) {
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([md5($_POST['new_reset_pwd'] ?: 'ds123456'), $target_id]);
        $success = "重置成功";
    } else {
        $error = "权限不足，无法重置同级或上级密码";
    }
}

// --- 5. 获取展示列表 ---
$admins = $pdo->query("SELECT a.*, d.name as company_name FROM admins a LEFT JOIN departments d ON a.belong_company_id = d.id ORDER BY FIELD(role,'super_admin','admin','hr_admin','web_admin','employee'), id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .perm-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 25px; margin-bottom: 25px; }
    .role-tag { display: inline-block; font-size: 11px; padding: 4px 10px; border-radius: 50px; color: #FFF; font-weight: bold; white-space: nowrap; }
    .tag-super { background: #EF4444; } .tag-admin { background: #3B82F6; } .tag-hr { background: #8B5CF6; } .tag-web { background: #10B981; } .tag-emp { background: #94A3B8; } 
    .edit-form { background: #F8FAFC; padding: 25px; border-radius: 15px; margin-bottom: 25px; display: none; border: 2px solid #1A3C6C; }
    .f-ctrl { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #CBD5E1; box-sizing: border-box; font-size: 14px; outline: none; background: #FFF; }
    .f-ctrl:focus { border-color: #1A3C6C; box-shadow: 0 0 0 3px rgba(26,60,108,0.1); }
    .f-label { font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 6px; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 15px; background: #F8FAFC; color: #64748B; font-size: 12px; }
    .admin-table td { padding: 18px 15px; border-bottom: 1px solid #F1F5F9; font-size: 14px; }
    .alert-msg { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
    .alert-success { background: #DCFCE7; color: #16A34A; border: 1px solid #BBF7D0; }
    .alert-error { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }
    #company_wrap { border: 2px solid #8B5CF6; padding: 15px; border-radius: 12px; background: #F5F3FF; }
</style>

<div class="perm-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2>账号权限与分级管辖</h2>
    <div>
        <button onclick="showResetForm()" style="background:#8B5CF6; color:#FFF; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold; margin-right:10px;">重置下级密码</button>
        <button onclick="showEditForm()" style="background:#1A3C6C; color:#FFF; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:bold;">+ 分配角色权限</button>
    </div>
</div>

<?php if($success): ?><div class="alert-msg alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>
<?php if($error): ?><div class="alert-msg alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div><?php endif; ?>

<div class="edit-form" id="editForm">
    <h3 id="formTitle" style="margin-top:0; color:#1A3C6C;">角色配置</h3>
    <form method="POST">
        <input type="hidden" name="admin_id" id="f_id">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-top:15px;">
            <div>
                <label class="f-label">登录账号</label>
                <select name="username" id="f_user" class="f-ctrl" required onchange="syncName()">
                    <option value="">- 选择系统员工 -</option>
                    <?php foreach($account_pool as $acc => $name): ?>
                        <option value="<?= htmlspecialchars($acc) ?>" data-name="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($acc) ?> (<?= htmlspecialchars($name) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="f-label">姓名</label>
                <input type="text" name="real_name" id="f_name" class="f-ctrl" readonly style="background:#EDF2F7;">
            </div>
            <div>
                <label class="f-label">系统角色 (仅显示您有权操作的级别)</label>
                <select name="role" id="f_role" class="f-ctrl" onchange="toggleCompanySelect()">
                    <?php 
                    $role_names = ['super_admin' => '超级管理员 (root)', 'admin' => '系统管理员', 'hr_admin' => '行政人事管理员', 'web_admin' => '网站管理员', 'employee' => '普通员工'];
                    foreach($role_ranks as $r_key => $r_val):
                        if($my_rank >= $r_val): // 只能分配不高于自己等级的角色
                    ?>
                        <option value="<?= $r_key ?>"><?= $role_names[$r_key] ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div>
                <label class="f-label">登录密码 (留空不改)</label>
                <input type="password" name="password" class="f-ctrl" placeholder="默认: ds123456">
            </div>
        </div>

        <div id="company_wrap" style="display:none; margin-top:20px;">
            <label class="f-label" style="color:#8B5CF6;"><i class="fa-solid fa-building-shield"></i> 归属公司/分公司 (行政人事专用管辖范围)</label>
            <select name="belong_company_id" id="f_belong" class="f-ctrl">
                <option value="0">-- 请选择管辖的实体 --</option>
                <?php foreach($company_entities as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-top:25px; padding-top:15px; border-top:1px solid #E2E8F0;">
            <button type="submit" name="save_account" style="padding:12px 40px; background:#1A3C6C; color:#FFF; border:none; border-radius:8px; cursor:pointer; font-weight:900;">保存权限设置</button>
            <button type="button" onclick="hideEditForm()" style="margin-left:15px; color:#64748B; background:none; border:none; cursor:pointer;">取消</button>
        </div>
    </form>
</div>

<div class="perm-card" style="padding:0; overflow:hidden;">
    <div style="width:100%; overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>姓名 / 账号</th>
                    <th>角色与管辖</th>
                    <th style="text-align:right;">管理操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($admins as $a): 
                    // 【权限检查】：目标等级是否低于我？
                    $target_rank = $role_ranks[$a['role']] ?? 0;
                    $can_manage = ($my_rank > $target_rank); 
                ?>
                <tr>
                    <td>
                        <strong style="color:#1A3C6C;"><?= htmlspecialchars($a['real_name']) ?></strong><br>
                        <code style="font-size:12px; color:#718096;"><?= htmlspecialchars($a['username']) ?></code>
                    </td>
                    <td>
                        <?php
                            $roles = ['super_admin'=>['root','tag-super'], 'admin'=>['管理员','tag-admin'], 'hr_admin'=>['行政人事','tag-hr'], 'web_admin'=>['网站管理','tag-web'], 'employee'=>['员工','tag-emp']];
                            $r = $roles[$a['role']] ?? ['未知',''];
                        ?>
                        <span class="role-tag <?= $r[1] ?>"><?= $r[0] ?></span>
                        <?php if($a['role'] === 'hr_admin'): ?>
                            <div style="font-size:11px; margin-top:5px; color:#8B5CF6; font-weight:bold;">
                                <i class="fa-solid fa-sitemap"></i> 辖：<?= htmlspecialchars($a['company_name'] ?: '全集团') ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if($can_manage): ?>
                            <button style="background:#EEF2FF; color:#1A3C6C; border:none; padding:8px 12px; border-radius:6px; font-weight:bold; cursor:pointer;" onclick='editAccount(<?= json_encode($a) ?>)'>编辑权限</button>
                        <?php else: ?>
                            <span style="font-size:12px; color:#CBD5E1;"><i class="fa-solid fa-lock"></i> 级别受限</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleCompanySelect() {
    const role = document.getElementById('f_role').value;
    document.getElementById('company_wrap').style.display = (role === 'hr_admin') ? 'block' : 'none';
}
function editAccount(data) {
    showEditForm();
    document.getElementById('formTitle').innerText = '编辑权限: ' + data.real_name;
    document.getElementById('f_id').value = data.id;
    document.getElementById('f_user').value = data.username;
    document.getElementById('f_name').value = data.real_name;
    document.getElementById('f_role').value = data.role;
    document.getElementById('f_belong').value = data.belong_company_id;
    toggleCompanySelect();
}
function showEditForm() { 
    document.getElementById('editForm').style.display = 'block'; 
    document.getElementById('f_id').value = '';
    document.getElementById('f_belong').value = '0';
    toggleCompanySelect();
}
function hideEditForm() { document.getElementById('editForm').style.display = 'none'; }
function syncName() {
    const sel = document.getElementById('f_user');
    document.getElementById('f_name').value = sel.options[sel.selectedIndex].getAttribute('data-name') || '';
}
</script>