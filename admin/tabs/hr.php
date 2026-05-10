<?php
/**
 * 代世集团 VMS - 人事档案管理 (纵向权限穿透增强版)
 */
if (!can('admin')) die("权限不足");

$current_admin_role = $_SESSION['admin_role'] ?? 'employee';
$my_belong_id = $_SESSION['belong_company_id'] ?? 0;

// --- 【新增：权限穿透核心函数】 ---
// 获取当前管理员有权查看的所有部门/公司 ID 列表
function getVisibleDeptIds($pdo, $rootId) {
    if ($rootId == 0) return [];
    $all = $pdo->query("SELECT id, parent_id FROM departments")->fetchAll(PDO::FETCH_ASSOC);
    $res = [$rootId];
    $stack = [$rootId];
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

// 根据角色准备权限 SQL 片段
$auth_where = "status != 'resigned'";
$auth_params = [];
$allowed_dept_ids = [];

if ($current_admin_role === 'hr_admin') {
    $allowed_dept_ids = getVisibleDeptIds($pdo, $my_belong_id);
    if (!empty($allowed_dept_ids)) {
        // 既然数据库 employees 存的是名称，我们需要把 ID 转为名称列表进行过滤
        $placeholders = implode(',', array_fill(0, count($allowed_dept_ids), '?'));
        $allowed_names = $pdo->prepare("SELECT name FROM departments WHERE id IN ($placeholders)");
        $allowed_names->execute($allowed_dept_ids);
        $names = $allowed_names->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($names)) {
            $name_placeholders = implode(',', array_fill(0, count($names), '?'));
            $auth_where .= " AND company IN ($name_placeholders)";
            $auth_params = $names;
        } else {
            $auth_where .= " AND 1=0"; // 兜底：没有归属则什么都看不见
        }
    } else {
        $auth_where .= " AND 1=0";
    }
}
// super_admin 和 admin 不需要额外条件，保持原样

// --- 1. 数据库自愈 (保持原样) ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    company VARCHAR(100),
    department VARCHAR(100),
    position VARCHAR(100),
    phone VARCHAR(20),
    system_user VARCHAR(50),
    entry_date DATE,
    probation_end DATE,
    status VARCHAR(20) DEFAULT 'probation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 获取组织架构原始数据
$org_raw = $pdo->query("SELECT id, parent_id, name, level FROM departments ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 【权限过滤：前端下拉菜单数据】 ---
// 如果是 hr_admin，只把他们有权管辖的部门推给前端 JS
$org_data = [];
foreach ($org_raw as $node) {
    if (in_array($current_admin_role, ['super_admin', 'admin']) || in_array($node['id'], $allowed_dept_ids)) {
        $org_data[] = $node;
    }
}

// --- 2. 账号生成逻辑 (保持原样) ---
function generateDsAccount($name) {
    $res = ''; $name_len = mb_strlen($name, 'UTF-8');
    for ($i = 0; $i < $name_len; $i++) {
        $char = mb_substr($name, $i, 1, 'UTF-8'); $p = iconv('UTF-8', 'GBK//IGNORE', $char);
        if (isset($p[0]) && ord($p[0]) > 128) {
            $asc = ord($p[0])*256+ord($p[1])-65536;
            if ($asc >= -20319 && $asc <= -20284) $res .= 'a'; else if ($asc >= -20283 && $asc <= -19776) $res .= 'b'; else if ($asc >= -19775 && $asc <= -19219) $res .= 'c'; else if ($asc >= -19218 && $asc <= -18711) $res .= 'd'; else if ($asc >= -18710 && $asc <= -18527) $res .= 'e'; else if ($asc >= -18526 && $asc <= -18240) $res .= 'f'; else if ($asc >= -18239 && $asc <= -17923) $res .= 'g'; else if ($asc >= -17922 && $asc <= -17418) $res .= 'h'; else if ($asc >= -17417 && $asc <= -16475) $res .= 'j'; else if ($asc >= -16474 && $asc <= -16213) $res .= 'k'; else if ($asc >= -16212 && $asc <= -15641) $res .= 'l'; else if ($asc >= -15640 && $asc <= -15166) $res .= 'm'; else if ($asc >= -15165 && $asc <= -14923) $res .= 'n'; else if ($asc >= -14922 && $asc <= -14915) $res .= 'o'; else if ($asc >= -14914 && $asc <= -14631) $res .= 'p'; else if ($asc >= -14630 && $asc <= -14150) $res .= 'q'; else if ($asc >= -14149 && $asc <= -14091) $res .= 'r'; else if ($asc >= -14090 && $asc <= -13319) $res .= 's'; else if ($asc >= -13318 && $asc <= -12839) $res .= 't'; else if ($asc >= -12838 && $asc <= -12557) $res .= 'w'; else if ($asc >= -12556 && $asc <= -11848) $res .= 'x'; else if ($asc >= -11847 && $asc <= -11056) $res .= 'y'; else if ($asc >= -11055 && $asc <= -10247) $res .= 'z';
        } else { $res .= strtolower($char); }
    }
    return "ds_" . $res;
}

// --- 3. 提交逻辑 (增强安全验证) ---
$msg = '';
if (isset($_POST['save_employee'])) {
    $name = trim($_POST['name']); $entry = $_POST['entry_date']; $dur = (int)$_POST['probation_duration'];
    $p_end = date('Y-m-d', strtotime("+$dur months", strtotime($entry)));
    $user = generateDsAccount($name);
    
    // 办理入职时验证归属合法性
    if ($current_admin_role === 'hr_admin') {
        $check_stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
        $check_stmt->execute([$_POST['company']]);
        $target_cid = $check_stmt->fetchColumn();
        if (!in_array($target_cid, $allowed_dept_ids)) die("越权操作：您无权向该公司录入人员");
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO employees (name, company, department, position, system_user, entry_date, probation_end, status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$name, $_POST['company'], $_POST['department'], $_POST['position'], $user, $entry, $p_end, ($dur==0?'active':'probation')]);
        
        $pdo->prepare("INSERT IGNORE INTO admins (username, password, real_name, role) VALUES (?,?,?,?)")
            ->execute([$user, '', $name, 'employee']);
            
        $pdo->commit(); $msg = "入职成功！账号：$user";
    } catch (Exception $e) { $pdo->rollBack(); $msg = "录入失败"; }
}

// --- 4. 获取受限后的列表数据 ---
$stmt = $pdo->prepare("SELECT * FROM employees WHERE $auth_where ORDER BY id DESC");
$stmt->execute($auth_params);
$emps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .hr-card { background: #FFF; border-radius: 12px; border: 1px solid #E2E8F0; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
    .f-label { display: block; font-size: 12px; font-weight: 800; color: #64748B; margin-bottom: 5px; }
    .f-input { width: 100%; padding: 10px; border: 1px solid #CBD5E1; border-radius: 6px; background: #F8FAFC; margin-bottom: 10px; box-sizing: border-box; outline: none; }
    .f-input:focus { border-color: #1A3C6C; background: #FFF; }
    .btn-blue { background: #1A3C6C; color: #FFF; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 800; cursor: pointer; }
    .emp-table { width: 100%; border-collapse: collapse; }
    .emp-table th { text-align: left; padding: 12px; background: #F8FAFC; color: #64748B; font-size: 13px; border-bottom: 1px solid #E2E8F0; }
    .emp-table td { padding: 12px; border-bottom: 1px solid #F1F5F9; font-size: 14px; }
    .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .badge-probation { background: #FEF3C7; color: #D97706; }
    .badge-active { background: #DCFCE7; color: #16A34A; }
    #actionModal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-box { background: #FFF; padding: 30px; border-radius: 20px; width: 450px; max-width: 90%; }
</style>

<div class="hr-container">
    <h2 style="color:#1A3C6C; margin-bottom:20px;">人事档案管理 <?php if($current_admin_role == 'hr_admin') echo "<small style='font-size:12px; background:#EEF2FF; padding:4px 10px; border-radius:5px;'>受限模式</small>"; ?></h2>
    <?php if($msg): ?><div style="padding:15px; background:#F0F9FF; border:1px solid #BAE6FD; color:#0369A1; border-radius:8px; margin-bottom:20px; font-weight:800;"><?= $msg ?></div><?php endif; ?>

    <div class="hr-card">
        <form method="POST">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px;">
                <div><label class="f-label">姓名</label><input type="text" name="name" class="f-input" required></div>
                <div><label class="f-label">单位</label><select name="company" id="e_comp" class="f-input" required><option value="">- 选择单位 -</option></select></div>
                <div><label class="f-label">部门</label><select name="department" id="e_dept" class="f-input" required><option value="">- 选择部门 -</option></select></div>
                <div><label class="f-label">职位</label><input type="text" name="position" class="f-input" placeholder="如：经理"></div>
                <div><label class="f-label">入职日期</label><input type="date" name="entry_date" class="f-input" value="<?=date('Y-m-d')?>"></div>
                <div><label class="f-label">试用时长</label><select name="probation_duration" class="f-input"><option value="1">1个月</option><option value="3" selected>3个月</option><option value="6">6个月</option><option value="0">无试用期</option></select></div>
            </div>
            <button type="submit" name="save_employee" class="btn-blue">办理入职</button>
        </form>
    </div>

    <div class="hr-card" style="padding:0; overflow:hidden;">
        <table class="emp-table">
            <thead>
                <tr><th>姓名/账号</th><th>单位/部门</th><th>到期预警</th><th>状态</th><th>业务办理</th></tr>
            </thead>
            <tbody>
                <?php foreach($emps as $e): 
                    $is_exp = ($e['status'] == 'probation' && strtotime($e['probation_end']) <= strtotime('+7 days'));
                ?>
                <tr class="<?= $is_exp ? 'alert-row' : '' ?>">
                    <td><strong><?=htmlspecialchars($e['name'])?></strong><br><small style="color:#94A3B8"><?=$e['system_user']?></small></td>
                    <td><small><?=$e['company']?></small><br><?=$e['department']?></td>
                    <td><?= $e['probation_end'] ?></td>
                    <td><span class="badge badge-<?=$e['status']?>"><?=$e['status']=='probation'?'试用期':'正式'?></span></td>
                    <td><button class="btn-blue" style="font-size:11px; padding:5px 10px;" onclick='openBusiness(<?=json_encode($e)?>)'>办理</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="actionModal"><div class="modal-box">
    <h3 id="m_title" style="margin-top:0; color:#1A3C6C;">办理业务</h3>
    <form method="POST">
        <input type="hidden" name="emp_id" id="m_id">
        <div style="margin-bottom:15px;">
            <label class="f-label">操作类型</label>
            <select name="action_type" id="m_action" class="f-input" onchange="toggleM()">
                <option value="active">办理转正</option>
                <option value="transfer">办理调岗</option>
                <option value="resign" style="color:red;">办理离职</option>
            </select>
        </div>
        <div id="m_trans" style="display:none;">
            <label class="f-label">新单位</label><select name="new_company" id="m_comp" class="f-input"></select>
            <label class="f-label">新部门</label><select name="new_dept" id="m_dept" class="f-input"></select>
        </div>
        <div style="margin-top:20px; display:flex; gap:10px;">
            <button type="submit" name="do_action" class="btn-blue" style="flex:1;">提交</button>
            <button type="button" onclick="closeModal()" style="flex:1; border:none; background:#F1F5F9; border-radius:6px; cursor:pointer;">取消</button>
        </div>
    </form>
</div></div>

<script>
// 【重要】JS 端 org 数据已经经过 PHP 预处理，只包含有权看到的分支
const org = <?= json_encode($org_data) ?>;
function initC(sel) { 
    org.filter(d => d.level == 1 || d.name.includes("公司")).forEach(u => sel.add(new Option(u.name, u.name))); 
}
function bind(c, d) {
    c.onchange = function() {
        let cid = org.find(x => x.name == this.value)?.id; d.innerHTML = '';
        if(!cid) return;
        org.filter(i => i.parent_id == cid).forEach(x => d.add(new Option(x.name, x.name)));
    };
}
initC(document.getElementById('e_comp')); bind(document.getElementById('e_comp'), document.getElementById('e_dept'));
initC(document.getElementById('m_comp')); bind(document.getElementById('m_comp'), document.getElementById('m_dept'));

function openBusiness(emp) { document.getElementById('m_id').value = emp.id; document.getElementById('m_title').innerText = "业务办理: " + emp.name; document.getElementById('actionModal').style.display = 'flex'; }
function toggleM() { document.getElementById('m_trans').style.display = (document.getElementById('m_action').value=='transfer'?'block':'none'); }
function closeModal() { document.getElementById('actionModal').style.display = 'none'; }
</script>