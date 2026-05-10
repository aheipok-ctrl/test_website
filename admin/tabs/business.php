<?php
/**
 * 代世集团 VMS - 集团业务管理 (全量加固修复版)
 * 修复：1. 解决 Header already sent 报错；2. 物理文件同步清理；3. 详情页页头页脚自动挂载。
 */
if (!can('admin')) die("权限不足");

// 1. 路径定义
$base_dir = dirname(dirname(__DIR__)); 
$web_dir = $base_dir . DIRECTORY_SEPARATOR . 'dajt_web' . DIRECTORY_SEPARATOR; 

// --- 2. 数据库结构自动维护 ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS business_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) DEFAULT '',
    brief TEXT,
    icon_class VARCHAR(50) DEFAULT 'fa-cube',
    media_path VARCHAR(255) DEFAULT '',
    link_url VARCHAR(255) DEFAULT '',
    page_body LONGTEXT,
    sort_order INT DEFAULT 0,
    UNIQUE KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

// --- 3. 辅助函数：生成包含页头页脚的物理 PHP 文件 ---
function generateBusinessPage($web_dir, $slug, $name) {
    if (empty($slug)) return;
    if (!is_dir($web_dir)) mkdir($web_dir, 0777, true);
    $file_path = $web_dir . "business_{$slug}.php";
    
    // 使用 Nowdoc 语法，其中的 PHP 变量会在前台运行时才解析
    $template = <<<'EOT'
<?php
/**
 * 自动生成的业务详情页 - 代世集团
 */
require_once '../config/db.php';

$current_page_slug = '{SLUG_VAL}';
$stmt = $pdo->prepare("SELECT * FROM business_units WHERE slug = ? LIMIT 1");
$stmt->execute([$current_page_slug]);
$biz = $stmt->fetch();

if (!$biz) die("内容未找到");

// 引入根目录 includes 文件夹下的标准页头
include_once '../includes/header.php';
?>

<style>
    .biz-hero { position: relative; width: 100%; height: 50vh; min-height: 400px; background: #000; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .biz-hero-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.6; z-index: 1; }
    .biz-hero-content { position: relative; z-index: 3; text-align: center; color: #fff; padding: 0 20px; }
    .biz-hero-content h1 { font-size: clamp(32px, 5vw, 56px); font-weight: 900; margin: 0 0 15px; letter-spacing:2px; }
    .biz-detail-container { max-width: 1200px; margin: 60px auto 100px; padding: 0 20px; line-height: 1.8; }
</style>

<div class="biz-hero">
    <?php if(!empty($biz['media_path'])): ?>
        <?php if(strpos($biz['media_path'], '.mp4') !== false): ?>
            <video src="<?php echo $biz['media_path']; ?>" class="biz-hero-bg" autoplay muted loop playsinline></video>
        <?php else: ?>
            <img src="<?php echo $biz['media_path']; ?>" class="biz-hero-bg">
        <?php endif; ?>
    <?php endif; ?>
    <div class="biz-hero-content">
        <h1><?php echo htmlspecialchars($biz['name']); ?></h1>
        <p style="opacity:0.8; font-size:18px;"><?php echo htmlspecialchars($biz['brief'] ?? ''); ?></p>
    </div>
</div>

<main class="biz-detail-container">
    <?php echo $biz['page_body'] ?? '<p style="text-align:center; color:#94a3b8;">暂无详情内容</p>'; ?>
</main>

<?php 
// 引入根目录 includes 文件夹下的标准页脚
include_once '../includes/footer.php'; 
?>
EOT;

    $template = str_replace('{SLUG_VAL}', $slug, $template);
    file_put_contents($file_path, $template);
}

// --- 4. 逻辑处理：删除功能 (放在输出之前) ---
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    $stmt = $pdo->prepare("SELECT slug FROM business_units WHERE id = ?");
    $stmt->execute([$del_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $target_php = $web_dir . "business_{$item['slug']}.php";
        if (file_exists($target_php)) @unlink($target_php);
        
        $pdo->prepare("DELETE FROM business_units WHERE id = ?")->execute([$del_id]);
        echo "<script>alert('业务页面已成功物理删除'); window.location.href='?tab=business';</script>";
        exit;
    }
}

// --- 5. 逻辑处理：保存 ---
if (isset($_POST['save_business'])) {
    $id = (int)($_POST['biz_id'] ?? 0);
    $name = trim($_POST['name']);
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['slug'])); 
    $brief = trim($_POST['brief']);
    $icon = trim($_POST['icon_class']);
    $sort = (int)$_POST['sort_order'];
    $page_body = $_POST['page_body'] ?? '';
    $media_path = $_POST['old_media_path'] ?? '';
    
    // 处理跳转链接
    $selected_link = $_POST['link_url'];
    if ($selected_link === "AUTO_NEW") {
        $link = "/dajt_web/business_{$slug}.php";
    } else {
        $link = "/dajt_web/" . $selected_link;
    }

    // 处理文件上传
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $upload_rel = '/uploads/business/';
        $abs_path = $base_dir . $upload_rel;
        if (!is_dir($abs_path)) mkdir($abs_path, 0777, true);
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $new_name = 'biz_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['media_file']['tmp_name'], $abs_path . $new_name)) {
            $media_path = $upload_rel . $new_name;
        }
    }

    if (!empty($name) && !empty($slug)) {
        try {
            if ($id > 0) {
                $sql = "UPDATE business_units SET name=?, slug=?, brief=?, icon_class=?, media_path=?, link_url=?, sort_order=?, page_body=? WHERE id=?";
                $pdo->prepare($sql)->execute([$name, $slug, $brief, $icon, $media_path, $link, $sort, $page_body, $id]);
            } else {
                $sql = "INSERT INTO business_units (name, slug, brief, icon_class, media_path, link_url, sort_order, page_body) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$name, $slug, $brief, $icon, $media_path, $link, $sort, $page_body]);
            }
            
            // 每次保存都自动生成/更新详情页文件
            generateBusinessPage($web_dir, $slug, $name);
            
            echo "<script>alert('业务详情已同步发布'); window.location.href='?tab=business';</script>";
            exit;
        } catch (PDOException $e) { $error = "操作失败：" . $e->getMessage(); }
    }
}

// --- 6. 数据拉取 ---
$existing_pages = is_dir($web_dir) ? glob($web_dir . "*.php") : [];
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM business_units WHERE id = ?");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_item = $stmt->fetch();
}
$list = $pdo->query("SELECT * FROM business_units ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<style>
    .biz-card { background: #FFF; border-radius: 12px; padding: 25px; border: 1px solid #E2E8F0; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #64748B; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #DDD; border-radius: 8px; box-sizing: border-box; outline: none; }
    .btn-save { background: #1A3C6C; color: #FFF; padding: 15px; border: none; border-radius: 8px; width: 100%; cursor: pointer; font-weight: bold; margin-top: 10px; }
    .data-table { width: 100%; margin-top: 30px; border-collapse: collapse; font-size: 14px; }
    .data-table th { background: #F8FAFC; padding: 12px; text-align: left; color: #64748B; border-bottom: 2px solid #F1F5F9; }
    .data-table td { padding: 12px; border-bottom: 1px solid #F1F5F9; }
    .btn-action { text-decoration: none; font-weight: bold; margin-right: 10px; }
</style>

<div class="biz-card">
    <h2 style="color:#1A3C6C; margin-top:0;">集团业务发布中心</h2>
    
    <?php if(isset($error)): ?><p style="color:#EF4444; font-weight:bold;"><?= $error ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php if($edit_item): ?>
            <input type="hidden" name="biz_id" value="<?= $edit_item['id'] ?>">
            <input type="hidden" name="old_media_path" value="<?= $edit_item['media_path']??'' ?>">
            <p style="color:#3B82F6;">正在编辑: <strong><?= htmlspecialchars($edit_item['name']) ?></strong></p>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom:15px;">
            <div class="form-group"><label>业务名称</label><input type="text" name="name" class="form-control" value="<?= $edit_item['name']??'' ?>" required></div>
            <div class="form-group"><label>URL 标识 (Slug)</label><input type="text" name="slug" class="form-control" value="<?= $edit_item['slug']??'' ?>" placeholder="如: cosmetics" required></div>
        </div>

        <div class="form-group">
            <label>指向链接</label>
            <select name="link_url" class="form-control">
                <option value="AUTO_NEW" selected>+ 自动生成/覆盖物理文件 (推荐)</option>
                <?php foreach($existing_pages as $page): $basename = basename($page); ?>
                    <option value="<?= $basename ?>" <?= (isset($edit_item['link_url']) && strpos($edit_item['link_url'], $basename)!==false)?'selected':'' ?>><?= $basename ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group"><label>详情页排版 HTML (在此粘贴定制代码)</label>
            <textarea name="page_body" class="form-control" style="height:300px; font-family:monospace; background:#F8FAFC;"><?= htmlspecialchars($edit_item['page_body']??'') ?></textarea>
        </div>

        <div class="form-group"><label>首页简述</label><input type="text" name="brief" class="form-control" value="<?= $edit_item['brief']??'' ?>"></div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom:15px;">
            <div class="form-group"><label>Banner 素材 (图/视频)</label><input type="file" name="media_file" class="form-control"></div>
            <div class="form-group"><label>显示排序</label><input type="number" name="sort_order" class="form-control" value="<?= $edit_item['sort_order']??0 ?>"></div>
        </div>

        <button type="submit" name="save_business" class="btn-save">保存并同步更新前台页面</button>
    </form>

    <table class="data-table">
        <thead><tr><th>业务名称</th><th>物理路径</th><th>排序</th><th>操作</th></tr></thead>
        <tbody>
            <?php foreach($list as $b): ?>
            <tr>
                <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
                <td><small style="color:#94A3B8">/dajt_web/business_<?= $b['slug'] ?>.php</small></td>
                <td><?= $b['sort_order'] ?></td>
                <td>
                    <a href="?tab=business&edit_id=<?= $b['id'] ?>" class="btn-action" style="color:#3B82F6;">编辑</a>
                    <a href="?tab=business&del_id=<?= $b['id'] ?>" class="btn-action" style="color:#EF4444;" onclick="return confirm('警告：这将永久删除该记录及物理 PHP 文件，确定吗？')">删除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>