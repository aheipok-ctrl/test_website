<?php
/**
 * 代世集团 VMS - 物品借还管理 (风格统一版)
 * 逻辑：高还原后台UI规范，确保功能与视觉双重对齐
 */
if (!can('admin')) die("权限不足");

// --- 1. 获取“在库可用”的资产数据 ---
$asset_query = $pdo->query("
    SELECT a.asset_type, a.asset_name, a.asset_code, a.serial_number 
    FROM fixed_assets a 
    WHERE a.status != '报废' 
    AND a.asset_code NOT IN (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(item_name, '(', -1), ')', 1) 
        FROM item_borrowing 
        WHERE status = '借出中'
    )
    ORDER BY a.asset_type ASC
");
$all_assets = $asset_query->fetchAll(PDO::FETCH_ASSOC);
$assets_json = json_encode($all_assets);

// --- 2. 获取员工名单 ---
$employees = [];
try {
    $emp_query = $pdo->query("SELECT name FROM employees ORDER BY name ASC");
    if ($emp_query) $employees = $emp_query->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// --- 3. 逻辑处理 ---
$success_msg = '';
$error_msg = '';

if (isset($_POST['add_batch_record'])) {
    $borrower = trim($_POST['borrower_name'] ?? '');
    $date = $_POST['borrow_date'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $items_array = json_decode($_POST['items_data'] ?? '[]', true);

    if (!empty($borrower) && !empty($items_array)) {
        try {
            $pdo->beginTransaction();
            $checkStmt = $pdo->prepare("SELECT id FROM item_borrowing WHERE item_name LIKE ? AND status = '借出中'");
            $insStmt = $pdo->prepare("INSERT INTO item_borrowing (item_name, borrower_name, borrow_date, notes, status) VALUES (?, ?, ?, ?, '借出中')");
            foreach ($items_array as $item) {
                $full_name = "【{$item['t']}】{$item['n']} ({$item['c']})";
                $checkStmt->execute(["%({$item['c']})%"]);
                if ($checkStmt->fetch()) throw new Exception("物品 [{$item['n']}] 已被借出。");
                $insStmt->execute([$full_name, $borrower, $date, $notes]);
            }
            $pdo->commit();
            $success_msg = "借用登记已成功提交！";
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            $error_msg = $e->getMessage(); 
        }
    }
}

if (isset($_GET['return_id'])) {
    $pdo->prepare("UPDATE item_borrowing SET status='已归还', actual_return=? WHERE id=?")->execute([date('Y-m-d'), (int)$_GET['return_id']]);
    header("Location: ?tab=items"); exit;
}

$records = $pdo->query("SELECT * FROM item_borrowing ORDER BY status DESC, id DESC LIMIT 50")->fetchAll();
?>

<style>
    /* --- 核心UI组件规范 --- */
    .items-header { margin-bottom: 25px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .items-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: 24px; }
    
    .ds-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    
    /* 表单控件风格 */
    .f-label { font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 8px; }
    .f-ctrl { 
        width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #CBD5E1; 
        font-size: 14px; box-sizing: border-box; background: #F8FAFC; outline: none; transition: 0.2s; 
    }
    .f-ctrl:focus { border-color: #1A3C6C; background: #FFF; box-shadow: 0 0 0 3px rgba(26,60,108,0.1); }
    .f-ctrl:disabled { opacity: 0.6; cursor: not-allowed; }

    /* 按钮组 */
    .btn-ds { border: none; border-radius: 10px; padding: 12px 25px; font-weight: 800; cursor: pointer; transition: 0.3s; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: #1A3C6C; color: #FFF; }
    .btn-success { background: #10B981; color: #FFF; }
    .btn-gray { background: #64748B; color: #FFF; }
    .btn-ds:active { transform: scale(0.96); }

    /* 列表与表格 */
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th { background: #F8FAFC; text-align: left; padding: 15px; font-size: 11px; color: #94A3B8; text-transform: uppercase; border-bottom: 2px solid #F1F5F9; }
    .items-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 14px; }
    
    .badge { font-size: 11px; padding: 4px 10px; border-radius: 50px; font-weight: bold; }
    .badge-out { background: #FEF9C3; color: #854D0E; }
    .badge-in { background: #DCFCE7; color: #166534; }

    /* 预览清单区域 */
    .cart-box { background: #F1F5F9; border-radius: 12px; padding: 15px; margin: 15px 0; border: 1px dashed #CBD5E1; }
    .cart-item { display: flex; justify-content: space-between; align-items: center; background: #FFF; padding: 8px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }

    /* 提示信息 */
    .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
    .alert-success { background: #DCFCE7; color: #16A34A; border: 1px solid #BBF7D0; }
    .alert-error { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }

    @media (max-width: 768px) {
        .ds-card { padding: 15px; }
        .grid-2 { grid-template-columns: 1fr !important; }
        .items-table th:nth-child(3), .items-table td:nth-child(3) { display: none; }
    }
</style>

<div class="items-header">
    <h2>物品借还登记管理</h2>
</div>

<?php if($success_msg): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></div><?php endif; ?>
<?php if($error_msg): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?></div><?php endif; ?>

<div class="ds-card">
    <form method="POST" id="batch-form">
        <h4 style="margin-top:0; color:#1A3C6C; margin-bottom:15px;"><i class="fa-solid fa-cart-flatbed"></i> 1. 构建借用清单</h4>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px;">
            <div>
                <label class="f-label">资产类别</label>
                <select id="sel-type" class="f-ctrl" onchange="updateNames()"><option value="">-- 选择类别 --</option></select>
            </div>
            <div>
                <label class="f-label">物品名称</label>
                <select id="sel-name" class="f-ctrl" onchange="updateCodes()" disabled><option value="">-- 先选类别 --</option></select>
            </div>
            <div>
                <label class="f-label">唯一编号</label>
                <select id="sel-code" class="f-ctrl" disabled><option value="">-- 先选名称 --</option></select>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button type="button" class="btn-ds btn-success" style="width:100%;" onclick="addItem()"><i class="fa-solid fa-plus"></i> 加入清单</button>
            </div>
        </div>

        <div class="cart-box">
            <div id="preview-body">
                <div style="text-align:center; color:#94A3B8; padding:10px; font-size:13px;">暂未添加任何待借出资产</div>
            </div>
        </div>

        <h4 style="color:#1A3C6C; margin-bottom:15px;"><i class="fa-solid fa-user-pen"></i> 2. 借用信息确认</h4>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
            <div>
                <label class="f-label">借用人姓名</label>
                <input type="text" name="borrower_name" id="f-user" list="emp-list" class="f-ctrl" placeholder="检索内部员工..." required>
                <datalist id="emp-list">
                    <?php foreach($employees as $name): ?><option value="<?= htmlspecialchars($name) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="f-label">借出日期</label>
                <input type="date" name="borrow_date" id="f-date" class="f-ctrl" value="<?= date('Y-m-d') ?>">
            </div>
            <div>
                <label class="f-label">备注信息</label>
                <input type="text" name="notes" id="f-notes" class="f-ctrl" placeholder="可选填事项">
            </div>
        </div>

        <input type="hidden" name="items_data" id="items_hidden">
        <div style="display:flex; gap:10px;">
            <button type="submit" name="add_batch_record" class="btn-ds btn-primary" style="flex:2;" onclick="return checkSubmit()">提交借出登记</button>
            <button type="button" class="btn-ds btn-gray" style="flex:1;" onclick="printCurrentBatch()"><i class="fa-solid fa-print"></i> 打印单据</button>
        </div>
    </form>
</div>

<div class="ds-card" style="padding:0; overflow:hidden;">
    <div style="padding:20px 25px; border-bottom:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center;">
        <h4 style="margin:0; color:#1A3C6C;">历史借还记录 (最近50条)</h4>
    </div>
    <div style="width:100%; overflow-x:auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>借用资产</th>
                    <th>借用人</th>
                    <th>借用日期</th>
                    <th>状态</th>
                    <th style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($records as $r): ?>
                <tr>
                    <td>
                        <strong style="color:#1E293B;"><?= htmlspecialchars($r['item_name']) ?></strong>
                        <?php if($r['notes']): ?><div style="font-size:11px; color:#94A3B8; margin-top:3px;">注: <?= htmlspecialchars($r['notes']) ?></div><?php endif; ?>
                    </td>
                    <td style="font-weight:bold; color:#1A3C6C;"><?= htmlspecialchars($r['borrower_name']) ?></td>
                    <td style="color:#64748B; font-family:monospace;"><?= $r['borrow_date'] ?></td>
                    <td>
                        <span class="badge <?= $r['status']=='借出中'?'badge-out':'badge-in' ?>"><?= $r['status'] ?></span>
                    </td>
                    <td style="text-align:right;">
                        <button class="btn-ds btn-gray" style="padding:6px 12px; font-size:12px;" onclick="printSingle('<?= addslashes($r['item_name']) ?>', '<?= addslashes($r['borrower_name']) ?>', '<?= $r['borrow_date'] ?>', '<?= addslashes($r['notes']) ?>')"><i class="fa-solid fa-print"></i></button>
                        <?php if($r['status']=='借出中'): ?>
                            <a href="?tab=items&return_id=<?= $r['id'] ?>" class="btn-ds btn-success" style="padding:6px 12px; font-size:12px; text-decoration:none;" onclick="return confirm('确认该物品已归还并入库？')">确认归还</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="print-container" style="display:none; color:#000; padding:20px; font-family:sans-serif;">
    <div style="text-align:center; border-bottom:2px solid #000; padding-bottom:15px; margin-bottom:20px;">
        <h1 style="margin:0; font-size:24px;">代世集团固定资产借还存根</h1>
        <p style="margin:5px 0 0; font-size:12px; letter-spacing:2px;">DAISHI GROUP ASSET FORM</p>
    </div>
    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:bold;">
        <span>借用人员：<span id="p-user-val"></span></span>
        <span>单据日期：<span id="p-date-val"></span></span>
    </div>
    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <thead><tr><th style="border:1px solid #000; padding:10px; width:50px;">NO.</th><th style="border:1px solid #000; padding:10px;">资产详细描述 (类别/名称/编号)</th><th style="border:1px solid #000; padding:10px;">借用备注</th></tr></thead>
        <tbody id="p-list-body"></tbody>
    </table>
    <div style="font-size:12px; border:1px solid #000; padding:10px; line-height:1.6;">
        <strong>注意事项：</strong><br>
        1. 借用人需妥善保管资产，如有损坏或丢失按公司规定赔偿；<br>
        2. 借用期满或离职前必须办理归还手续。
    </div>
    <div style="margin-top:50px; display:flex; justify-content:space-between; font-weight:bold;">
        <span>借用人签名：____________________</span>
        <span>管理员确认：____________________</span>
    </div>
</div>

<script>
    const assets = <?= $assets_json ?>;
    let cart = [];

    // 初始化类型
    const ts = document.getElementById('sel-type');
    [...new Set(assets.map(a => a.asset_type))].forEach(t => ts.add(new Option(t, t)));

    function updateNames() {
        const t = document.getElementById('sel-type').value;
        const ns = document.getElementById('sel-name');
        ns.innerHTML = '<option value="">-- 物品名称 --</option>';
        ns.disabled = !t;
        if(t) [...new Set(assets.filter(a => a.asset_type === t).map(a => a.asset_name))].forEach(n => ns.add(new Option(n, n)));
        updateCodes();
    }

    function updateCodes() {
        const t = document.getElementById('sel-type').value;
        const n = document.getElementById('sel-name').value;
        const cs = document.getElementById('sel-code');
        cs.innerHTML = '<option value="">-- 资产编号 --</option>';
        cs.disabled = !n;
        if(n) assets.filter(a => a.asset_type === t && a.asset_name === n).forEach(c => cs.add(new Option(c.asset_code, c.asset_code)));
    }

    function addItem() {
        const t = document.getElementById('sel-type').value, n = document.getElementById('sel-name').value, c = document.getElementById('sel-code').value;
        if(!c) return alert("请先选择完整的资产信息");
        if(cart.some(i => i.c === c)) return alert("该编号已在清单中");
        cart.push({t, n, c});
        renderCart();
    }

    function renderCart() {
        const b = document.getElementById('preview-body');
        if(!cart.length) { b.innerHTML = '<div style="text-align:center; color:#94A3B8; padding:10px; font-size:13px;">暂未添加任何待借出资产</div>'; return; }
        b.innerHTML = cart.map((i, idx) => `
            <div class="cart-item">
                <span><i class="fa-solid fa-cube"></i> 【${i.t}】<b>${i.n}</b> (${i.c})</span>
                <a href="javascript:rmItem(${idx})" style="color:#EF4444; font-size:12px; text-decoration:none;"><i class="fa-solid fa-trash-can"></i> 移除</a>
            </div>
        `).join('');
    }

    function rmItem(idx) { cart.splice(idx, 1); renderCart(); }

    function checkSubmit() {
        if(!cart.length) { alert("清单为空，请先添加资产"); return false; }
        document.getElementById('items_hidden').value = JSON.stringify(cart);
        return true;
    }

    function printCurrentBatch() {
        const user = document.getElementById('f-user').value;
        const date = document.getElementById('f-date').value;
        if(!user || !cart.length) return alert("请先填写借用人并添加物品");
        preparePrint(user, date, cart.map((i, idx) => `<tr><td style="border:1px solid #000;padding:10px;text-align:center;">${idx+1}</td><td style="border:1px solid #000;padding:10px;">【${i.t}】${i.n} (${i.c})</td><td style="border:1px solid #000;padding:10px;">-</td></tr>`).join(''));
    }

    function printSingle(name, user, date, notes) {
        preparePrint(user, date, `<tr><td style="border:1px solid #000;padding:10px;text-align:center;">1</td><td style="border:1px solid #000;padding:10px;">${name}</td><td style="border:1px solid #000;padding:10px;">${notes||'-'}</td></tr>`);
    }

    function preparePrint(user, date, body) {
        document.getElementById('p-user-val').innerText = user;
        document.getElementById('p-date-val').innerText = date;
        document.getElementById('p-list-body').innerHTML = body;
        
        // 执行打印
        const content = document.getElementById('print-container').innerHTML;
        const pWin = window.open('', '_blank');
        pWin.document.write('<html><body onload="window.print();window.close()">' + content + '</body></html>');
        pWin.document.close();
    }
</script>