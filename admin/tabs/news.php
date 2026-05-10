<?php
/**
 * 代世集团 VMS - 新闻管理中心 (手机端全适配优化版)
 * 升级：1. 响应式布局适配；2. 修复硬编码路径；3. 增加表格滑动保护
 */
if (!can('admin')) die("权限不足");

// --- 1. 获取基准目录 (避免硬编码) ---
$base_dir = dirname(dirname(__DIR__)); 

// --- 2. 处理新增新闻逻辑 ---
if (isset($_POST['add_news'])) {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $content = $_POST['content']; 
    $publish_date = $_POST['publish_date'] ?: date('Y-m-d');
    $cover_path = '';

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_abs_path = $base_dir . '/uploads/media/';
        if (!is_dir($upload_abs_path)) mkdir($upload_abs_path, 0777, true);
        
        $new_name = 'news_cover_' . time() . '.' . pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_abs_path . $new_name)) {
            $cover_path = '/uploads/media/' . $new_name;
        }
    }

    if (!empty($title)) {
        $sql = "INSERT INTO news_articles (title, category, cover_image, content, publish_date, status) VALUES (?, ?, ?, ?, ?, 'active')";
        $pdo->prepare($sql)->execute([$title, $category, $cover_path, $content, $publish_date]);
        echo "<script>alert('新闻已发布！'); location.href='?tab=news';</script>";
        exit;
    }
}

// --- 3. 处理删除逻辑 ---
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    $stmt = $pdo->prepare("SELECT cover_image FROM news_articles WHERE id = ?");
    $stmt->execute([$del_id]);
    $article = $stmt->fetch();
    
    if ($article && !empty($article['cover_image'])) {
        $file_path = $base_dir . $article['cover_image'];
        if (file_exists($file_path)) unlink($file_path);
    }
    
    $pdo->prepare("DELETE FROM news_articles WHERE id = ?")->execute([$del_id]);
    echo "<script>alert('删除成功！'); location.href='?tab=news';</script>"; 
    exit;
}

$news_list = $pdo->query("SELECT * FROM news_articles ORDER BY publish_date DESC, id DESC")->fetchAll();
?>

<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<style>
    .news-header { margin-bottom: 20px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .news-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: clamp(18px, 4vw, 24px); }
    
    .news-card { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: clamp(15px, 4vw, 30px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    
    /* --- 1. 表单网格响应式适配 --- */
    .news-form-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); 
        gap: clamp(15px, 3vw, 20px); 
        background: #F8FAFC; 
        padding: clamp(15px, 3vw, 25px); 
        border-radius: 12px; 
        margin-bottom: 25px; 
        border: 1px solid #F1F5F9; 
    }
    
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #E2E8F0; outline: none; font-size: 14px; box-sizing: border-box; background: #FFF; }
    .form-control:focus { border-color: #1A3C6C; box-shadow: 0 0 0 3px rgba(26,60,108,0.1); }
    
    /* --- 2. CKEditor 5 移动端溢出保护 --- */
    .ck-editor__editable { min-height: clamp(250px, 40vh, 400px); }
    .ck.ck-editor { max-width: 100% !important; }

    .publish-btn { 
        background: #1A3C6C; color: #FFF; border: none; padding: 16px; 
        border-radius: 12px; cursor: pointer; font-weight: 800; width: 100%; font-size: 16px;
        transition: 0.3s; margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .publish-btn:active { transform: scale(0.98); opacity: 0.9; }

    /* --- 3. 数据表格横向滚动保护 --- */
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 30px; border-radius: 12px; border: 1px solid #F1F5F9; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 750px; }
    .data-table th { text-align: left; padding: 15px; color: #94A3B8; border-bottom: 2px solid #F1F5F9; font-size: 11px; text-transform: uppercase; }
    .data-table td { padding: 15px; border-bottom: 1px solid #F1F5F9; font-size: 14px; vertical-align: middle; }
    
    .cover-thumb { width: 80px; height: 50px; object-fit: cover; border-radius: 6px; background: #F1F5F9; flex-shrink: 0; }
    
    .btn-del { color: #EF4444; font-size: 13px; font-weight: 800; text-decoration: none; padding: 8px; border-radius: 6px; transition: 0.2s; }
    .btn-del:active { background: #FEF2F2; }

    /* 手机端间距压缩 */
    @media (max-width: 600px) {
        .news-form-grid { padding: 15px; }
        .publish-btn { font-size: 15px; }
    }
</style>

<div class="news-header">
    <h2>新闻管理中心</h2>
</div>

<div class="news-card">
    <form method="POST" enctype="multipart/form-data">
        <div class="news-form-grid">
            <div class="form-group">
                <label>新闻标题</label>
                <input type="text" name="title" class="form-control" placeholder="输入引人注目的标题" required>
            </div>
            <div class="form-group">
                <label>所属分类</label>
                <select name="category" class="form-control">
                    <option value="CORP">集团要闻</option>
                    <option value="BIZ">业务动态</option>
                    <option value="TECH">科技创新</option>
                    <option value="EVENT">活动快讯</option>
                </select>
            </div>
            <div class="form-group">
                <label>发布日期</label>
                <input type="date" name="publish_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>封面图片</label>
                <input type="file" name="cover_image" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label>正文内容排版 (CKEditor 5)</label>
            <div id="editor-container">
                <textarea name="content" id="news-editor"></textarea>
            </div>
        </div>

        <button type="submit" name="add_news" class="publish-btn">
            <i class="fa-solid fa-cloud-arrow-up"></i> 发布并同步至前台页面
        </button>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>封面</th>
                    <th>新闻标题</th>
                    <th>分类</th>
                    <th>发布日期</th>
                    <th style="text-align:right;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($news_list)): ?>
                    <?php foreach($news_list as $n): ?>
                    <tr>
                        <td>
                            <?php if($n['cover_image']): ?>
                                <img src="<?= htmlspecialchars($n['cover_image']) ?>" class="cover-thumb">
                            <?php else: ?>
                                <div class="cover-thumb" style="display:flex; align-items:center; justify-content:center; color:#CBD5E1;"><i class="fa-solid fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><strong style="color: #1E293B;"><?= htmlspecialchars($n['title']) ?></strong></td>
                        <td><span style="background:#E0E7FF; color:#3730A3; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:bold; white-space:nowrap;"><?= htmlspecialchars($n['category']) ?></span></td>
                        <td style="color: #64748B; font-size: 13px; white-space:nowrap;"><?= htmlspecialchars($n['publish_date']) ?></td>
                        <td style="text-align:right;">
                            <a href="?tab=news&del_id=<?= $n['id'] ?>" class="btn-del" onclick="return confirm('确定彻底删除这篇文章吗？')">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:60px; color:#94A3B8;">暂无新闻记录</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // 初始化编辑器并确保容器不溢出
    ClassicEditor
        .create(document.querySelector('#news-editor'), {
            placeholder: '在此编写新闻正文内容...',
            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'insertTable', 'mediaEmbed', 'undo', 'redo' ]
        })
        .catch(error => { console.error(error); });
</script>