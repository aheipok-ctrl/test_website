<?php
/**
 * 代世集团 VMS - 应聘简历中心 (修复 SQL 报错后的权限穿透版)
 */
if (!can('admin')) die("权限不足");

$current_admin_role = $_SESSION['admin_role'] ?? 'employee';
$my_belong_id = $_SESSION['belong_company_id'] ?? 0;

// --- 1. 权限穿透核心函数 ---
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

// --- 2. 构建权限过滤 SQL 片段 ---
$allowed_dept_ids = [];
$auth_sql_part = "";
$auth_params = [];

if ($current_admin_role === 'hr_admin') {
    $allowed_dept_ids = getVisibleDeptIds($pdo, $my_belong_id);
    if (!empty($allowed_dept_ids)) {
        // 关键修复点：由于 job_postings 表里存的是公司名称(company)，我们需要通过 ID 查出名称列表
        $id_placeholders = implode(',', array_fill(0, count($allowed_dept_ids), '?'));
        $stmt_names = $pdo->prepare("SELECT name FROM departments WHERE id IN ($id_placeholders)");
        $stmt_names->execute($allowed_dept_ids);
        $allowed_company_names = $stmt_names->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($allowed_company_names)) {
            $name_placeholders = implode(',', array_fill(0, count($allowed_company_names), '?'));
            // 如果是顶级公司(ID=1)，则可以看到所有“自主投递”的简历(job_id=0)，否则只能看对应职位的
            $top_extra = ($my_belong_id == 1) ? " OR r.job_id = 0" : "";
            
            // 【修复点】：改用 j.company 匹配名称，而不是 j.company_id
            $auth_sql_part = " AND (j.company IN ($name_placeholders) $top_extra)";
            $auth_params = $allowed_company_names;
        } else {
            $auth_sql_part = " AND 1=0";
        }
    } else {
        $auth_sql_part = " AND 1=0";
    }
}

// --- 3. 数据库基础维护 (保持原样) ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS candidate_resumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT DEFAULT 0,
    query_no VARCHAR(50) UNIQUE,
    candidate_name VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    resume_file VARCHAR(255),
    status VARCHAR(50) DEFAULT '待筛选',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 获取受限的组织架构数据（用于入职办理）
$org_raw = $pdo->query("SELECT id, parent_id, name FROM departments ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);
$org_data = [];
foreach ($org_raw as $node) {
    if (in_array($current_admin_role, ['super_admin', 'admin']) || in_array($node['id'], $allowed_dept_ids)) {
        $org_data[] = $node;
    }
}

// --- 4. 处理业务提交 ---
if (isset($_POST['execute_update'])) {
    $rid = (int)$_POST['resume_id'];
    $status = $_POST['new_status'];
    
    // 安全检查：确认此简历是否在管辖范围内
    $check_sql = "SELECT r.id FROM candidate_resumes r LEFT JOIN job_postings j ON r.job_id = j.id WHERE r.id = ? $auth_sql_part";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(array_merge([$rid], $auth_params));
    
    if (!$check_stmt->fetch() && !in_array($current_admin_role, ['super_admin', 'admin'])) {
        die("越权警告：您无权操作管辖范围外的简历！");
    }

    $fields = ["status = ?"]; $params = [$status];
    if ($status === '面试中') {
        array_push($fields, "interviewer=?", "interview_phone=?", "interview_loc=?", "interview_time=?");
        array_push($params, $_POST['interviewer'], $_POST['interview_phone'], $_POST['interview_loc'], $_POST['interview_time']);
    } elseif ($status === '已录取') {
        array_push($fields, "hire_company=?", "hire_dept=?", "hire_pos=?", "hire_loc=?", "hire_contact=?", "hire_phone=?");
        array_push($params, $_POST['hire_company'], $_POST['hire_dept'], $_POST['hire_pos'], $_POST['hire_loc'], $_POST['hire_contact'], $_POST['hire_phone']);
    }
    $sql = "UPDATE candidate_resumes SET " . implode(", ", $fields) . " WHERE id = ?";
    $params[] = $rid;
    $pdo->prepare($sql)->execute($params);
    echo "<script>location.href='?tab=resumes';</script>"; exit;
}

// 删除逻辑
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    $pdo->prepare("DELETE FROM candidate_resumes WHERE id = ?")->execute([$del_id]);
    echo "<script>location.href='?tab=resumes';</script>"; exit;
}

// --- 5. 获取受限后的列表数据 ---
$query = "SELECT r.*, IFNULL(j.job_title, '自主投递') as target_job 
          FROM candidate_resumes r 
          LEFT JOIN job_postings j ON r.job_id = j.id 
          WHERE 1=1 $auth_sql_part 
          ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($auth_params);
$resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root { --ds-blue: #1A3C6C; --ds-slate: #64748B; --ds-bg: #F8FAFC; }
    .res-container { animation: fadeIn 0.4s ease-out; padding: 10px; }
    .page-header { border-left: 6px solid var(--ds-blue); padding-left: 15px; margin-bottom: 20px; }
    .page-header h2 { margin: 0; color: var(--ds-blue); font-size: 22px; font-weight: 900; }
    .vms-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .table-responsive { width: 100%; overflow-x: auto; }
    .vms-table { width: 100%; border-collapse: collapse; min-width: 800px; }
    .vms-table th { text-align: left; padding: 15px; background: var(--ds-bg); color: var(--ds-slate); font-size: 11px; font-weight: 800; border-bottom: 2px solid #F1F5F9; }
    .vms-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
    .st-tag { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; display: inline-block; }
    .st-待筛选 { background: #F1F5F9; color: #475569; }
    .st-面试中 { background: #DBEAFE; color: #1E40AF; }
    .st-已录取 { background: #DCFCE7; color: #15803D; }
    .st-不合适 { background: #FEE2E2; color: #B91C1C; }
    .q-no { font-family: monospace; background: #EEF2FF; color: var(--ds-blue); padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    #bizModal { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .modal-box { background: #FFF; width: 95%; max-width: 550px; border-radius: 24px; padding: 30px; max-height: 90vh; overflow-y: auto; }
    .f-label { display: block; font-size: 11px; font-weight: 800; color: var(--ds-slate); margin-bottom: 6px; }
    .f-input { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 10px; margin-bottom: 12px; box-sizing: border-box; outline: none; }
    .btn-submit { background: var(--ds-blue); color: #FFF; border: none; padding: 14px; border-radius: 12px; font-weight: 800; width: 100%; cursor: pointer; }
    .btn-biz { background: #F0F7FF; color: var(--ds-blue); border: 1px solid #CFE5FF; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 800; cursor: pointer; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
</style>

<div class="res-container">
    <div class="page-header">
        <h2>应聘简历中心 <?php if($current_admin_role == 'hr_admin') echo "<small style='font-size:12px; background:#EEF2FF; padding:4px 10px; border-radius:5px;'>辖区管辖</small>"; ?></h2>
    </div>

    <div class="vms-card">
        <div class="table-responsive">
            <table class="vms-table">
                <thead>
                    <tr>
                        <th>候选人 / 编号</th>
                        <th>应聘职位</th>
                        <th>简历附件</th>
                        <th>状态</th>
                        <th style="text-align:right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($resumes as $r): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($r['candidate_name']) ?></strong><br>
                            <span class="q-no"><?= $r['query_no'] ?></span>
                        </td>
                        <td style="color:var(--ds-blue); font-weight:800;"><?= htmlspecialchars($r['target_job']) ?></td>
                        <td>
                            <?php if($r['resume_file']): ?>
                                <a href="/<?= ltrim($r['resume_file'], '/') ?>" target="_blank" style="color:var(--ds-blue); font-weight:800; text-decoration:none;"><i class="fa-solid fa-file-pdf"></i> 预览</a>
                            <?php else: ?> <span style="color:#CCC">未上传</span> <?php endif; ?>
                        </td>
                        <td><span class="st-tag st-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                        <td style="text-align:right; white-space:nowrap;">
                            <button class="btn-biz" onclick='openBizModal(<?= json_encode($r) ?>)'>办理</button>
                            <a href="?tab=resumes&del_id=<?= $r['id'] ?>" style="color:#EF4444; margin-left:12px;" onclick="return confirm('确定永久删除该简历？')"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="bizModal">
    <div class="modal-box">
        <h3 id="mTitle" style="margin-top:0; color:var(--ds-blue); border-bottom:1px solid #F1F5F9; padding-bottom:15px;">办理业务</h3>
        <form method="POST">
            <input type="hidden" name="resume_id" id="mId">
            <label class="f-label">变更状态</label>
            <select name="new_status" id="mStatus" class="f-input" onchange="toggleFields()">
                <option value="待筛选">待筛选</option>
                <option value="面试中">面试中</option>
                <option value="已录取">已录取</option>
                <option value="不合适">不合适</option>
            </select>

            <div id="fields_interview" style="display:none; border-top:1px dashed #DDD; padding-top:15px;">
                <div class="grid-2">
                    <div><label class="f-label">面试官</label><input type="text" name="interviewer" class="f-input"></div>
                    <div><label class="f-label">联系方式</label><input type="text" name="interview_phone" class="f-input"></div>
                </div>
                <label class="f-label">地点/链接</label><input type="text" name="interview_loc" class="f-input">
                <label class="f-label">面试时间</label><input type="datetime-local" name="interview_time" class="f-input">
            </div>

            <div id="fields_hire" style="display:none; border-top:1px dashed #DDD; padding-top:15px;">
                <div class="grid-2">
                    <div><label class="f-label">入职公司</label><select name="hire_company" id="h_comp" class="f-input"></select></div>
                    <div><label class="f-label">入职部门</label><select name="hire_dept" id="h_dept" class="f-input"></select></div>
                </div>
                <div class="grid-2">
                    <div><label class="f-label">职务</label><input type="text" name="hire_pos" class="f-input"></div>
                    <div><label class="f-label">报到地点</label><input type="text" name="hire_loc" class="f-input"></div>
                </div>
            </div>

            <button type="submit" name="execute_update" class="btn-submit">保存更新</button>
            <button type="button" onclick="closeModal()" style="width:100%; border:none; background:none; color:var(--ds-slate); margin-top:10px; cursor:pointer;">返回</button>
        </form>
    </div>
</div>

<script>
const org = <?= json_encode($org_data) ?>;

function openBizModal(data) {
    document.getElementById('mId').value = data.id;
    document.getElementById('mStatus').value = data.status;
    document.getElementById('mTitle').innerText = "简历处理: " + data.candidate_name;

    const hComp = document.getElementById('h_comp');
    hComp.innerHTML = '<option value="">- 选择入职单位 -</option>';
    org.filter(d => d.name.includes("公司") || d.name.includes("集团")).forEach(u => {
        hComp.add(new Option(u.name, u.name));
    });

    hComp.onchange = function() {
        const dept = document.getElementById('h_dept');
        dept.innerHTML = '<option value="">- 选择部门 -</option>';
        const cid = org.find(x => x.name === this.value)?.id;
        if(cid) org.filter(i => i.parent_id === cid).forEach(x => dept.add(new Option(x.name, x.name)));
    };
    
    toggleFields();
    document.getElementById('bizModal').style.display = 'flex';
}

function toggleFields() {
    const status = document.getElementById('mStatus').value;
    document.getElementById('fields_interview').style.display = (status === '面试中' ? 'block' : 'none');
    document.getElementById('fields_hire').style.display = (status === '已录取' ? 'block' : 'none');
}

function closeModal() { document.getElementById('bizModal').style.display = 'none'; }
document.getElementById('bizModal').onclick = function(e) { if(e.target === this) closeModal(); }
</script>