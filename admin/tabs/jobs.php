<?php
/**
 * 代世集团 VMS - 招聘发布管理 (编辑器调优版)
 */
if (!can('admin')) die("权限不足");

$current_admin_role = $_SESSION['admin_role'] ?? 'employee';
$my_belong_id = $_SESSION['belong_company_id'] ?? 0;

// --- 【权限穿透核心函数】 ---
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

// --- 1. 权限预处理 ---
$is_restricted = ($current_admin_role === 'hr_admin');
$allowed_dept_ids = $is_restricted ? getVisibleDeptIds($pdo, $my_belong_id) : [];
$allowed_names = [];

if ($is_restricted) {
    if (!empty($allowed_dept_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_dept_ids), '?'));
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($allowed_dept_ids);
        $allowed_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// --- 2. 数据库自愈 ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_title VARCHAR(100),
    company VARCHAR(100) DEFAULT '',
    department VARCHAR(100) DEFAULT '',
    location VARCHAR(50),
    salary VARCHAR(50),
    experience VARCHAR(50),
    education VARCHAR(50),
    job_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 3. 获取组织架构数据 (带权限过滤) ---
try {
    $org_raw = $pdo->query("SELECT id, parent_id, name, level FROM departments ORDER BY level ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $org_raw = []; }

$org_data = [];
foreach ($org_raw as $node) {
    $in_jurisdiction = (!$is_restricted || in_array($node['id'], $allowed_dept_ids));
    if ($in_jurisdiction) {
        $node['is_company'] = ($node['level'] == 1 || $node['parent_id'] == 0 || mb_strpos($node['name'], '公司') !== false);
        $org_data[] = $node;
    }
}

$companies = array_filter($org_data, function($node) {
    return $node['is_company'];
});

// --- 4. 处理新增逻辑 ---
if (isset($_POST['add_job'])) {
    $comp = $_POST['company'];
    if ($is_restricted && !in_array($comp, $allowed_names)) {
        die("越权操作：无权发布非管辖公司的招聘信息");
    }

    $title = trim($_POST['job_title']);
    $dept = $_POST['department'];
    $loc = trim($_POST['location']);
    $sal = trim($_POST['salary']);
    $exp = $_POST['experience'];
    $edu = $_POST['education'];
    $desc = $_POST['job_description'];

    if (!empty($title)) {
        $sql = "INSERT INTO job_postings (job_title, company, department, location, salary, experience, education, job_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$title, $comp, $dept, $loc, $sal, $exp, $edu, $desc]);
        echo "<script>alert('职位发布成功！'); window.location.href='?tab=jobs';</script>"; exit;
    }
}

// 删除逻辑
if (isset($_GET['del_id'])) {
    $pdo->prepare("DELETE FROM job_postings WHERE id = ?")->execute([(int)$_GET['del_id']]);
    echo "<script>window.location.href='?tab=jobs';</script>"; exit;
}

// --- 5. 获取受限后的列表数据 ---
$auth_where = "1=1";
$auth_params = [];
if ($is_restricted) {
    if (!empty($allowed_names)) {
        $placeholders = implode(',', array_fill(0, count($allowed_names), '?'));
        $auth_where = "company IN ($placeholders)";
        $auth_params = $allowed_names;
    } else {
        $auth_where = "1=0";
    }
}

$stmt_list = $pdo->prepare("SELECT * FROM job_postings WHERE $auth_where ORDER BY id DESC");
$stmt_list->execute($auth_params);
$jobs = $stmt_list->fetchAll();
?>

<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<style>
    .jobs-header { margin-bottom: 20px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .jobs-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: clamp(18px, 4vw, 24px); }
    .jobs-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: clamp(15px, 4vw, 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .jobs-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 15px; background: #F8FAFC; padding: clamp(15px, 3vw, 25px); border-radius: 12px; margin-bottom: 25px; border: 1px solid #F1F5F9; align-items: flex-end; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #FFF; outline: none; }
    
    /* 【修改点】：将编辑器高度从 250px 增加到 500px */
    .ck-editor__editable { min-height: 500px; }
    .ck.ck-editor { max-width: 100% !important; margin-bottom: 20px; }

    .btn-submit { background: #1A3C6C; color: #FFF; border: none; padding: 16px; border-radius: 12px; cursor: pointer; font-weight: 800; width: 100%; font-size: 16px; transition: 0.3s; }
    .table-responsive { width: 100%; overflow-x: auto; margin-top: 30px; border-radius: 12px; border: 1px solid #F1F5F9; }
    .job-table { width: 100%; border-collapse: collapse; min-width: 850px; }
    .job-table th { text-align: left; padding: 15px; color: #94A3B8; border-bottom: 2px solid #F1F5F9; font-size: 11px; background: #F8FAFC; }
    .job-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 14px; }
    .comp-tag { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; background: #E0E7FF; color: #3730A3; }
    .dept-tag { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
</style>

<div class="jobs-header">
    <h2>招聘发布中心 <?php if($is_restricted) echo "<small style='font-size:12px; background:#EEF2FF; padding:4px 10px; border-radius:5px;'>管辖发布</small>"; ?></h2>
</div>

<div class="jobs-card">
    <form method="POST">
        <div class="jobs-form-grid">
            <div class="form-group">
                <label>招聘职位名称</label>
                <input type="text" name="job_title" class="form-control" placeholder="如：店长" required>
            </div>
            
            <div class="form-group">
                <label>所属公司</label>
                <select name="company" id="company_select" class="form-control" required>
                    <option value="">-- 请选择公司 --</option>
                    <?php foreach($companies as $c): ?>
                        <option value="<?= htmlspecialchars($c['name']) ?>" data-id="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>所属部门</label>
                <select name="department" id="department_select" class="form-control">
                    <option value="">-- 无部门 / 不限 --</option>
                </select>
            </div>

            <div class="form-group">
                <label>工作地点</label>
                <input type="text" name="location" class="form-control" placeholder="如：上海">
            </div>
            
            <div class="form-group">
                <label>薪资范围</label>
                <input type="text" name="salary" class="form-control" placeholder="如：8K - 12K">
            </div>
        </div>

        <div class="form-group">
            <label style="display:block; font-size:11px; font-weight:800; color:#64748B; margin-bottom:12px;">职位详情与任职要求 (CKEditor 5 高级编辑器)</label>
            <textarea name="job_description" id="job-editor"></textarea>
        </div>

        <button type="submit" name="add_job" class="btn-submit">立即发布职位至官网</button>
    </form>

    <div class="table-responsive">
        <table class="job-table">
            <thead>
                <tr>
                    <th>招聘职位</th>
                    <th>所属公司 / 部门</th>
                    <th>地点 / 薪资</th>
                    <th style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($jobs)): ?>
                    <?php foreach($jobs as $j): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($j['job_title']) ?></strong></td>
                        <td>
                            <span class="comp-tag"><?= htmlspecialchars($j['company']) ?></span><br>
                            <span class="dept-tag"><?= htmlspecialchars($j['department'] ?: '全公司') ?></span>
                        </td>
                        <td><?= htmlspecialchars($j['location']) ?> / <span style="color:#10B981;font-weight:800"><?= htmlspecialchars($j['salary']) ?></span></td>
                        <td style="text-align:right;">
                            <a href="?tab=jobs&del_id=<?= $j['id'] ?>" style="color:#EF4444;" onclick="return confirm('确定撤回？')"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:40px; color:#94A3B8;">暂无您管辖范围内的招聘信息</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    ClassicEditor.create(document.querySelector('#job-editor')).catch(e => console.error(e));

    const orgData = <?= json_encode($org_data) ?>;
    const compSelect = document.getElementById('company_select');
    const deptSelect = document.getElementById('department_select');

    compSelect.addEventListener('change', function() {
        const compId = this.options[this.selectedIndex].getAttribute('data-id');
        deptSelect.innerHTML = '<option value="">-- 无部门 / 不限 --</option>';
        if (!compId) return;

        function buildDeptTree(parentId, depth) {
            orgData.filter(d => d.parent_id == parentId).forEach(child => {
                if (child.is_company) return; 
                const opt = document.createElement('option');
                opt.value = child.name;
                opt.innerHTML = "&nbsp;".repeat(depth*4) + "├─ " + child.name;
                deptSelect.appendChild(opt);
                buildDeptTree(child.id, depth + 1);
            });
        }
        buildDeptTree(compId, 0);
    });
</script>