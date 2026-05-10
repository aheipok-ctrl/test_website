<?php
/**
 * 代世集团 VMS - 组织架构管理 (重叠样式修复版)
 * 逻辑：1. 全局架构穿透显示；2. 跨区域/上级节点锁定禁止编辑；3. 严谨的后端操作鉴权
 */
if (!can('admin')) die("权限不足");

$current_admin_role = $_SESSION['admin_role'] ?? 'employee';
$my_belong_id = $_SESSION['belong_company_id'] ?? 0;

// --- 【核心鉴权函数】 ---
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

// 预计算当前管理员管辖的所有节点 ID
$is_restricted = ($current_admin_role === 'hr_admin');
$my_jurisdiction_ids = $is_restricted ? getVisibleDeptIds($pdo, $my_belong_id) : [];

// --- 2. 逻辑处理 (后端二次校验) ---
if (isset($_POST['add_dept'])) {
    $parent_id = (int)$_POST['parent_id'];
    if ($is_restricted && !in_array($parent_id, $my_jurisdiction_ids)) {
        die("越权操作：无权在该上级节点下添加部门");
    }
    
    $name = trim($_POST['name']);
    $manager = trim($_POST['manager']);
    $description = trim($_POST['description']);
    if (!empty($name)) {
        $level = 1;
        if ($parent_id > 0) {
            $stmt = $pdo->prepare("SELECT level FROM departments WHERE id = ?");
            $stmt->execute([$parent_id]);
            $level = (int)$stmt->fetchColumn() + 1;
        }
        $pdo->prepare("INSERT INTO departments (name, parent_id, level, manager, description) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $parent_id, $level, $manager, $description]);
        echo "<script>location.href='?tab=org';</script>"; exit;
    }
}

if (isset($_POST['edit_dept'])) {
    $target_id = (int)$_POST['dept_id'];
    if ($is_restricted && !in_array($target_id, $my_jurisdiction_ids)) die("越权操作：禁止编辑非管辖节点");
    
    $pdo->prepare("UPDATE departments SET name=?, manager=?, description=? WHERE id=?")
        ->execute([trim($_POST['edit_name']), trim($_POST['edit_manager']), trim($_POST['edit_description']), $target_id]);
    echo "<script>location.href='?tab=org';</script>"; exit;
}

if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    if ($is_restricted && !in_array($del_id, $my_jurisdiction_ids)) die("越权操作：禁止删除非管辖节点");
    
    $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$del_id]);
    echo "<script>location.href='?tab=org';</script>"; exit;
}

// --- 3. 架构树构建 ---
$all = $pdo->query("SELECT * FROM departments ORDER BY level ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

function buildTree($items, $pid = 0) {
    $branch = [];
    foreach ($items as $item) {
        if ($item['parent_id'] == $pid) {
            $children = buildTree($items, $item['id']);
            if ($children) $item['children'] = $children;
            $branch[] = $item;
        }
    }
    return $branch;
}
$full_tree = buildTree($all, 0);

function renderRows($tree, $prefix = '', $my_jurisdiction_ids, $is_restricted) {
    $html = '';
    $count = count($tree);
    foreach ($tree as $index => $node) {
        $is_last = ($index === $count - 1);
        $indent_str = ($prefix !== '' || $node['parent_id'] > 0) ? $prefix . ($is_last ? '└─ ' : '├─ ') : '';
        $indent = str_replace(' ', '&nbsp;', $indent_str);
        
        $can_edit = !$is_restricted || in_array((int)$node['id'], $my_jurisdiction_ids);
        
        $icon_set = [
            1 => ['icon' => 'fa-building-shield', 'color' => '#1A3C6C', 'bg' => '#F1F7FF', 'border' => '#D1E3F8'],
            2 => ['icon' => 'fa-city', 'color' => '#334155', 'bg' => '#F8FAFC', 'border' => '#E2E8F0'],
            3 => ['icon' => 'fa-sitemap', 'color' => '#64748B', 'bg' => '#FFFFFF', 'border' => '#E2E8F0']
        ];
        $current_set = $icon_set[$node['level']] ?? $icon_set[3];
        
        $op_html = $can_edit ? "
                <button class='btn-text-edit' onclick='openEdit({$node['id']})'><i class='fa-regular fa-pen-to-square'></i></button>
                <a href='?tab=org&del_id={$node['id']}' class='btn-text-del' onclick='return confirm(\"确定删除该节点及其子级？\")'><i class='fa-regular fa-trash-can'></i></a>" 
                : "<span style='color:#CBD5E1; font-size:12px;'><i class='fa-solid fa-lock'></i> 只读</span>";

        $html .= "<tr class='level-{$node['level']} " . ($can_edit ? '' : 'row-readonly') . "'>
            <td>
                <div class='org-tree-wrapper'>
                    <span class='indent-guide'>{$indent}</span>
                    <div class='node-wrapper' style='background:{$current_set['bg']}; border-color:{$current_set['border']}; " . ($can_edit ? '' : 'opacity:0.7;') . "'>
                        <i class='fa-solid {$current_set['icon']}' style='color:{$current_set['color']};'></i>
                        <span class='name-text' style='color:{$current_set['color']};'>".htmlspecialchars($node['name'])."</span>
                    </div>
                </div>
            </td>
            <td><span class='mgr-badge'>".htmlspecialchars($node['manager'] ?: '-')."</span></td>
            <td class='desc-cell'>".htmlspecialchars($node['description'])."</td>
            <td style='text-align:right; white-space:nowrap;'>$op_html</td>
        </tr>";

        if ($can_edit) {
            $html .= "<tr id='edit-{$node['id']}' class='edit-row' style='display:none;'>
                <td colspan='4' style='background:#F1F5F9; padding:15px;'>
                    <form method='POST'>
                        <input type='hidden' name='dept_id' value='{$node['id']}'>
                        <div class='edit-form-inner'>
                            <input type='text' name='edit_name' value='".htmlspecialchars($node['name'])."' class='f-input' required placeholder='名称'>
                            <input type='text' name='edit_manager' value='".htmlspecialchars($node['manager'])."' class='f-input' placeholder='负责人'>
                            <input type='text' name='edit_description' value='".htmlspecialchars($node['description'])."' class='f-input' placeholder='职能'>
                            <button type='submit' name='edit_dept' class='btn-save-mini'>保存</button>
                            <button type='button' class='btn-cancel-mini' onclick='closeEdit({$node['id']})'>取消</button>
                        </div>
                    </form>
                </td>
            </tr>";
        }
        
        if (!empty($node['children'])) {
            $is_root_without_prefix = ($prefix === '' && $node['parent_id'] == 0);
            $new_prefix = $prefix . (($is_last && !$is_root_without_prefix) ? '    ' : '│   ');
            $html .= renderRows($node['children'], $new_prefix, $my_jurisdiction_ids, $is_restricted);
        }
    }
    return $html;
}

function renderSelect($tree, $prefix = '', $my_jurisdiction_ids, $is_restricted) {
    $html = '';
    foreach ($tree as $node) {
        $can_use_as_parent = !$is_restricted || in_array((int)$node['id'], $my_jurisdiction_ids);
        if ($can_use_as_parent) {
            $html .= "<option value='{$node['id']}'>{$prefix}{$node['name']}</option>";
        }
        if (!empty($node['children'])) $html .= renderSelect($node['children'], $prefix.'&nbsp;&nbsp;├─ ', $my_jurisdiction_ids, $is_restricted);
    }
    return $html;
}
?>

<style>
    .org-header { margin-bottom: 20px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .org-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: 22px; }
    .org-card { background: #FFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
    
    /* 修复重叠的关键 CSS */
    .org-form-grid { display: flex; flex-wrap: wrap; gap: 15px; background: #F8FAFC; padding: 20px; border-bottom: 1px solid #E2E8F0; align-items: flex-end; }
    .f-group { flex: 1; min-width: 180px; }
    .f-group label { display: block; font-size: 11px; font-weight: 800; color: #94A3B8; margin-bottom: 5px; text-transform: uppercase; }
    .f-input { 
        width: 100%; padding: 10px; border: 1px solid #E2E8F0; border-radius: 6px; 
        font-size: 13px; outline: none; box-sizing: border-box; /* 核心修复点 */
    }
    .f-input:focus { border-color: #1A3C6C; box-shadow: 0 0 0 2px rgba(26,60,108,0.1); }
    
    .btn-create { 
        background: #1A3C6C; color: #FFF; border: none; padding: 0 20px; 
        border-radius: 6px; font-weight: bold; cursor: pointer; height: 38px; 
        white-space: nowrap; flex-shrink: 0; /* 核心修复点 */
    }

    .table-scroll { width: 100%; overflow-x: auto; background: #fff; }
    .org-table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 1000px; }
    .org-table th { background: #F8FAFC; text-align: left; padding: 12px 20px; color: #64748B; font-size: 11px; border-bottom: 1px solid #E2E8F0; }
    .org-table td { padding: 12px 20px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
    .indent-guide { color: #CBD5E1; font-family: "Courier New", Courier, monospace; font-size: 18px; vertical-align: middle; }
    .node-wrapper { display: flex; align-items: center; padding: 6px 12px; border: 1px solid #E2E8F0; border-radius: 8px; white-space: nowrap; box-sizing: border-box; }
    .mgr-badge { background: #F1F7FF; color: #1A3C6C; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; }
    .btn-text-edit { color: #3B82F6; background: none; border: none; cursor: pointer; font-size: 16px; }
    .btn-text-del { color: #EF4444; font-size: 16px; margin-left: 8px; }
    .restricted-info { background: #F0F9FF; color: #0369A1; padding: 8px 15px; font-size: 12px; font-weight: bold; border-bottom: 1px solid #BAE6FD; }
    
    /* 修复编辑行的重叠 */
    .edit-form-inner { display: flex; gap: 10px; flex-wrap: wrap; }
    .edit-form-inner .f-input { flex: 1; min-width: 120px; }
    .btn-save-mini { background: #10B981; color: #fff; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer; white-space: nowrap; }
    .btn-cancel-mini { background: #94A3B8; color: #fff; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer; white-space: nowrap; }
    
    .org-tree-wrapper { display: flex; align-items: center; white-space: nowrap; }
</style>

<div class="org-header">
    <h2>集团组织架构管理</h2>
</div>

<div class="org-card">
    <?php if($is_restricted): ?>
        <div class="restricted-info">
            <i class="fa-solid fa-circle-info"></i> 已开启「分级管控」模式：您可以查看集团完整架构，但仅能对所属分支进行编辑/增删操作。
        </div>
    <?php endif; ?>

    <form method="POST" class="org-form-grid">
        <div class="f-group">
            <label>上级节点</label>
            <select name="parent_id" class="f-input" required>
                <?php if(!$is_restricted): ?>
                    <option value="0">顶级 (集团总部)</option>
                <?php endif; ?>
                <?= renderSelect($full_tree, '', $my_jurisdiction_ids, $is_restricted) ?>
            </select>
        </div>
        <div class="f-group">
            <label>新节点名称</label>
            <input type="text" name="name" class="f-input" placeholder="输入部门/公司名称" required>
        </div>
        <div class="f-group">
            <label>负责人</label>
            <input type="text" name="manager" class="f-input" placeholder="姓名">
        </div>
        <div class="f-group">
            <label>职能简述</label>
            <input type="text" name="description" class="f-input" placeholder="简述">
        </div>
        <button type="submit" name="add_dept" class="btn-create"><i class="fa-solid fa-plus"></i> 添加新节点</button>
    </form>

    <div class="table-scroll">
        <table class="org-table">
            <thead>
                <tr>
                    <th style="width: 45%;">组织层级结构</th>
                    <th style="width: 15%;">负责人</th>
                    <th style="width: 25%;">职能描述</th>
                    <th style="width: 15%; text-align:right;">管理权限</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($full_tree)): ?>
                    <?= renderRows($full_tree, '', $my_jurisdiction_ids, $is_restricted) ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:50px; color:#94A3B8;">暂无架构数据。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function openEdit(id) {
        document.querySelectorAll('.edit-row').forEach(r => r.style.display = 'none');
        const row = document.getElementById('edit-' + id);
        if(row) row.style.display = 'table-row';
    }
    function closeEdit(id) {
        const row = document.getElementById('edit-' + id);
        if(row) row.style.display = 'none';
    }
</script>