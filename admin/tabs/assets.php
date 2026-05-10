<?php
/**
 * 代世集团 VMS - 媒体素材管理 (权限归属加固版 + 自定义素材名称 + 隐私默认图)
 * 功能：1. 公开/内部权限划分；2. 组织架构联动归属；3. 瀑布流画廊
 */
if (!can('admin')) die("权限不足");

// --- 1. 数据库自动维护与升级 ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS media_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    file_type VARCHAR(50),
    file_size INT,
    upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 自动检测并增加权限与归属字段
try {
    $cols = $pdo->query("SHOW COLUMNS FROM media_assets")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('visibility', $cols)) {
        $pdo->exec("ALTER TABLE media_assets ADD COLUMN visibility VARCHAR(20) DEFAULT 'public'");
        $pdo->exec("ALTER TABLE media_assets ADD COLUMN company VARCHAR(100) DEFAULT ''");
        $pdo->exec("ALTER TABLE media_assets ADD COLUMN department VARCHAR(100) DEFAULT ''");
    }
} catch (Exception $e) {}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 定义路径
$base_dir = dirname(dirname(__DIR__)); 
$upload_rel_path = '/uploads/media/';
$upload_abs_path = $base_dir . $upload_rel_path;
if (!is_dir($upload_abs_path)) mkdir($upload_abs_path, 0777, true);

// 辅助函数
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

// --- 2. 动态拉取组织架构数据 ---
try {
    $org_data = $pdo->query("SELECT id, parent_id, name, level FROM departments ORDER BY level ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $org_data = []; }

// 智能标记并提取公司
foreach ($org_data as &$node) {
    $node['is_company'] = ($node['level'] == 1 || $node['parent_id'] == 0 || mb_strpos($node['name'], '公司') !== false);
}
unset($node);
$companies = array_filter($org_data, function($node) { return $node['is_company']; });

// --- 3. 处理素材上传逻辑 ---
$error = null;
if (isset($_POST['upload_media'])) {
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $vid_exts = ['mp4', 'webm', 'mov'];
        $doc_exts = ['pdf', 'doc', 'docx', 'zip', 'rar']; // 增加文档支持，方便内部下载
        
        if (in_array($ext, array_merge($img_exts, $vid_exts, $doc_exts))) {
            $new_name = 'media_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target_path = $upload_abs_path . $new_name;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $db_path = $upload_rel_path . $new_name;
                $type = in_array($ext, $img_exts) ? 'image' : (in_array($ext, $vid_exts) ? 'video' : 'document');
                
                $visibility = $_POST['visibility'];
                $company = ($visibility === 'private') ? ($_POST['company'] ?? '') : '';
                $department = ($visibility === 'private') ? ($_POST['department'] ?? '') : '';
                
                // 【新增功能】：处理自定义素材名称，若未填则使用原文件名
                $custom_name = trim($_POST['custom_name'] ?? '');
                $final_file_name = ($custom_name !== '') ? $custom_name : $file['name'];
                
                $stmt = $pdo->prepare("INSERT INTO media_assets (file_name, file_path, file_type, file_size, visibility, company, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$final_file_name, $db_path, $type, $file['size'], $visibility, $company, $department]);
                
                echo "<script>window.location.href='?tab=assets';</script>";
                exit;
            } else { $error = "文件保存失败，请检查文件夹权限。"; }
        } else { $error = "不支持的文件格式。"; }
    } else { $error = "请选择文件或文件大小超过服务器限制。"; }
}

// --- 4. 处理删除逻辑 ---
if (isset($_GET['del_asset_id'])) {
    $id = (int)$_GET['del_asset_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM media_assets WHERE id = ?");
    $stmt->execute([$id]);
    $asset = $stmt->fetch();

    if ($asset) {
        $full_path = $base_dir . $asset['file_path'];
        if (file_exists($full_path)) @unlink($full_path);
        $pdo->prepare("DELETE FROM media_assets WHERE id = ?")->execute([$id]);
    }
    echo "<script>window.location.href='?tab=assets';</script>";
    exit;
}

$assets = $pdo->query("SELECT * FROM media_assets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .assets-container { padding: 10px 0; }
    .assets-header { margin-bottom: 25px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .assets-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: 24px; }

    /* 上传卡片与表单 */
    .upload-box { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 15px; }
    .f-group label { display: block; font-size: 12px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    .f-input { width: 100%; padding: 12px; border: 1px solid #CBD5E1; border-radius: 8px; background: #F8FAFC; outline: none; box-sizing: border-box; }
    .f-input:focus { border-color: #1A3C6C; background: #FFF; }
    
    .file-drop-area { border: 2px dashed #CBD5E1; border-radius: 8px; padding: 20px; text-align: center; background: #F8FAFC; cursor: pointer; transition: 0.3s; margin-bottom: 20px; }
    .file-drop-area:hover { border-color: #1A3C6C; background: #F1F7FF; }
    
    .btn-up { background: #1A3C6C; color: #FFF; border: none; padding: 14px 40px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; width: 100%; font-size: 15px; }
    .btn-up:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26,60,108,0.2); }

    /* 素材网格与标签 */
    .assets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
    .asset-item { background: #FFF; border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0; position: relative; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); display: flex; flex-direction: column; }
    .asset-item:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }

    .asset-preview { width: 100%; height: 160px; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
    .asset-preview img, .asset-preview video { width: 100%; height: 100%; object-fit: cover; }
    
    .badge-wrap { position: absolute; top: 10px; left: 10px; display: flex; gap: 5px; flex-direction: column; z-index: 5; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; color: #FFF; text-shadow: 0 1px 2px rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
    .badge.video { background: rgba(0,0,0,0.6); }
    .badge.doc { background: rgba(59, 130, 246, 0.8); }
    .badge.public { background: rgba(16, 185, 129, 0.9); }
    .badge.private { background: rgba(245, 158, 11, 0.9); }
    
    .asset-info { padding: 15px; flex: 1; display: flex; flex-direction: column; }
    .asset-title { font-size: 14px; font-weight: 800; color: #1E293B; margin-bottom: 8px; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .asset-meta { font-size: 12px; color: #94A3B8; display: flex; justify-content: space-between; margin-bottom: 8px; }
    .asset-owner { font-size: 11px; color: #64748B; background: #F1F5F9; padding: 6px; border-radius: 6px; margin-top: auto; }

    .btn-del { position: absolute; top: 10px; right: 10px; background: rgba(239, 68, 68, 0.9); color: #FFF; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; border: 2px solid #FFF; transition: 0.3s; opacity: 0; z-index: 10; }
    .asset-item:hover .btn-del { opacity: 1; }

    @media (max-width: 600px) {
        .assets-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-del { opacity: 1; }
    }
</style>

<div class="assets-container">
    <div class="assets-header">
        <h2>媒体素材与资源管理</h2>
    </div>

    <?php if($error): ?>
        <div style="background:#FEF2F2; color:#DC2626; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #FECACA;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="upload-box">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-grid">
                <div class="f-group">
                    <label>素材名称 (选填)</label>
                    <input type="text" name="custom_name" class="f-input" placeholder="留空则自动提取原文件名">
                </div>

                <div class="f-group">
                    <label>下载权限归属</label>
                    <select name="visibility" id="visibility_select" class="f-input">
                        <option value="public">🌍 任何人可公开查看/下载</option>
                        <option value="private">🔒 内部文件 (需员工验证下载)</option>
                    </select>
                </div>
                
                <div class="f-group org-selector" style="display:none;">
                    <label>归属公司</label>
                    <select name="company" id="company_select" class="f-input">
                        <option value="" data-id="">-- 请选择归属公司 --</option>
                        <?php foreach($companies as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>" data-id="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="f-group org-selector" style="display:none;">
                    <label>归属部门</label>
                    <select name="department" id="department_select" class="f-input">
                        <option value="">-- 先选择公司 --</option>
                    </select>
                </div>
            </div>

            <div class="file-drop-area" onclick="document.getElementById('media_file').click()">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 32px; color: #1A3C6C; margin-bottom: 10px;"></i>
                <div style="font-size: 14px; font-weight: bold; color: #334155;">点击选择文件 (图片/视频/文档)</div>
                <div style="font-size: 12px; color: #94A3B8; margin-top: 5px;">最大支持 50MB</div>
                <input type="file" name="media_file" id="media_file" style="display: none;" required>
            </div>

            <button type="submit" name="upload_media" class="btn-up">开始上传并入库</button>
        </form>
    </div>

    <div class="assets-grid">
        <?php foreach ($assets as $a): ?>
            <div class="asset-item">
                <div class="asset-preview">
                    <div class="badge-wrap">
                        <?php if($a['visibility'] === 'public'): ?>
                            <span class="badge public"><i class="fa-solid fa-earth-americas"></i> 公开</span>
                        <?php else: ?>
                            <span class="badge private"><i class="fa-solid fa-lock"></i> 内部保密</span>
                        <?php endif; ?>
                        
                        <?php if ($a['file_type'] === 'video'): ?>
                            <span class="badge video"><i class="fa-solid fa-video"></i> 视频</span>
                        <?php elseif ($a['file_type'] === 'document'): ?>
                            <span class="badge doc"><i class="fa-solid fa-file-pdf"></i> 文档</span>
                        <?php endif; ?>
                    </div>

                    <?php 
                        // 【新增】：私有鉴权文件在前台预览区显示默认保护图片，防止隐私泄漏
                        if ($a['visibility'] === 'private'): 
                    ?>
                        <div style="background:#F8FAFC; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
                            <i class="fa-solid fa-file-shield" style="font-size:45px; color:#CBD5E1; margin-bottom:10px;"></i>
                            <span style="font-size:11px; color:#94A3B8; font-weight:bold; letter-spacing:1px;">受保护的内部素材</span>
                        </div>
                    <?php else: ?>
                        <?php if ($a['file_type'] === 'image'): ?>
                            <img src="<?= htmlspecialchars($a['file_path']) ?>">
                        <?php elseif ($a['file_type'] === 'video'): ?>
                            <video src="<?= htmlspecialchars($a['file_path']) ?>" muted></video>
                        <?php else: ?>
                            <div style="background:#F1F5F9; width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-file-lines" style="font-size:50px; color:#CBD5E1;"></i>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="?tab=assets&del_asset_id=<?= $a['id'] ?>" class="btn-del" onclick="return confirm('确定永久删除？')">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
                
                <div class="asset-info">
                    <div class="asset-title" title="<?= htmlspecialchars($a['file_name']) ?>"><?= htmlspecialchars($a['file_name']) ?></div>
                    <div class="asset-meta">
                        <span><?= formatSize($a['file_size']) ?></span>
                        <span><?= date('m-d H:i', strtotime($a['upload_time'])) ?></span>
                    </div>
                    <?php if($a['visibility'] === 'private'): ?>
                        <div class="asset-owner">
                            <i class="fa-solid fa-sitemap"></i> <?= htmlspecialchars($a['company']) ?> 
                            <?= $a['department'] ? ' > ' . htmlspecialchars($a['department']) : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // 1. 权限可见性切换逻辑
    const visSelect = document.getElementById('visibility_select');
    const orgSelectors = document.querySelectorAll('.org-selector');
    
    visSelect.addEventListener('change', function() {
        if (this.value === 'private') {
            orgSelectors.forEach(el => el.style.display = 'block');
            document.getElementById('company_select').required = true;
        } else {
            orgSelectors.forEach(el => el.style.display = 'none');
            document.getElementById('company_select').required = false;
        }
    });

    // 2. 组织架构公司与部门联动逻辑 (复用自 jobs.php)
    const orgData = <?= json_encode($org_data) ?>;
    const compSelect = document.getElementById('company_select');
    const deptSelect = document.getElementById('department_select');

    compSelect.addEventListener('change', function() {
        const compId = this.options[this.selectedIndex].getAttribute('data-id');
        deptSelect.innerHTML = '<option value="">-- 全公司可见 --</option>';
        if (!compId) return;

        function buildDeptTree(parentId, depth) {
            const children = orgData.filter(d => d.parent_id == parentId);
            children.forEach(child => {
                if (child.is_company) return; // 跳过子公司
                
                let indent = '';
                for(let i = 0; i < depth; i++) indent += '&nbsp;&nbsp;&nbsp;&nbsp;';
                
                const option = document.createElement('option');
                option.value = child.name;
                option.innerHTML = indent + '├─ ' + child.name;
                deptSelect.appendChild(option);
                
                buildDeptTree(child.id, depth + 1);
            });
        }
        buildDeptTree(compId, 0);
    });
</script>