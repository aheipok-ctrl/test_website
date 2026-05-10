<?php
/**
 * 代世集团 VMS - 业务网页可视化拖拽构建器
 * 引擎：GrapesJS (支持文字、图片、视频、多列布局拖拽)
 */
if (!can('admin')) die("权限不足");

// --- 1. 获取数据 ---
$edit_id = isset($_GET['edit_page_id']) ? (int)$_GET['edit_page_id'] : 0;
$current_page = null;

$all_pages = $pdo->query("SELECT id, name, slug FROM business_units ORDER BY sort_order ASC, id ASC")->fetchAll();

if ($edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM business_units WHERE id = ?");
    $stmt->execute([$edit_id]);
    $current_page = $stmt->fetch();
}

// --- 2. 处理保存请求 (由 AJAX 触发) ---
if (isset($_POST['action']) && $_POST['action'] == 'save_canvas') {
    $id = (int)$_POST['page_id'];
    $html = $_POST['html'];
    $css = $_POST['css'];
    // 合并 HTML 和 CSS 存入 page_body，确保前台直接渲染预览效果
    $full_content = "<style>" . $css . "</style>" . $html;
    
    $stmt = $pdo->prepare("UPDATE business_units SET page_body = ? WHERE id = ?");
    $stmt->execute([$full_content, $id]);
    echo json_encode(['status' => 'success']);
    exit;
}
?>

<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs"></script>

<style>
    body, html { height: 100%; margin: 0; }
    .builder-container { display: flex; flex-direction: column; height: calc(100vh - 100px); border: 1px solid #E2E8F0; border-radius: 12px; overflow: hidden; background: #fff; }
    
    /* 顶部工具栏 */
    .builder-nav { background: #1A3C6C; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }
    .builder-nav h3 { margin: 0; font-size: 16px; }
    
    /* 布局分栏 */
    .builder-main { display: flex; flex: 1; overflow: hidden; }
    #gjs { flex: 1; height: 100%; }
    
    /* 左侧页面切换面板 */
    .page-sidebar { width: 220px; background: #F8FAFC; border-right: 1px solid #E2E8F0; overflow-y: auto; padding: 15px; }
    .page-link { display: block; padding: 10px; border-radius: 6px; color: #64748B; text-decoration: none; font-size: 13px; margin-bottom: 5px; }
    .page-link:hover { background: #F1F5F9; }
    .page-link.active { background: #1A3C6C; color: #fff; font-weight: bold; }

    /* 自定义块管理器样式 */
    .gjs-blocks-cs { border-left: 1px solid #E2E8F0; background: #F8FAFC; }
    
    .btn-save-canvas { background: #10B981; color: #fff; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
</style>

<div class="pages-header" style="margin-bottom:15px; border-left:6px solid #1A3C6C; padding-left:15px;">
    <h2 style="margin:0; font-size:22px; color:#1A3C6C;">业务详情页可视化构建</h2>
</div>

<div class="builder-container">
    <div class="builder-nav">
        <h3><?php echo $current_page ? "正在编辑内容：" . htmlspecialchars($current_page['name']) : "请在左侧选择页面"; ?></h3>
        <?php if($current_page): ?>
        <div>
            <button class="btn-save-canvas" onclick="saveData()">发布更新</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="builder-main">
        <aside class="page-sidebar">
            <div style="font-size:11px; color:#94A3B8; margin-bottom:10px; font-weight:bold;">可用业务页面</div>
            <?php foreach($all_pages as $p): ?>
                <a href="?tab=pages&edit_page_id=<?= $p['id'] ?>" class="page-link <?= $edit_id==$p['id']?'active':'' ?>">
                    <?= htmlspecialchars($p['name']) ?>
                </a>
            <?php endforeach; ?>
        </aside>

        <div id="gjs">
            <?php 
                // 如果有内容则加载内容
                if($current_page && !empty($current_page['page_body'])){
                    echo $current_page['page_body'];
                } else {
                    echo '<div style="padding:50px; text-align:center;"><h1>新业务页面</h1><p>从右侧拖拽模块到此处开始排版</p></div>';
                }
            ?>
        </div>
    </div>
</div>

<script>
    <?php if($current_page): ?>
    // 初始化 GrapesJS
    const editor = grapesjs.init({
        container: '#gjs',
        fromElement: true,
        height: '100%',
        storageManager: false, // 我们手动处理保存
        blockManager: {
            appendTo: '#blocks', // 如果需要自定义容器
            blocks: [
                {
                    id: 'section',
                    label: '<b>基础区块</b>',
                    attributes: { class: 'gjs-block-section' },
                    content: '<section style="padding:50px 0; text-align:center;"><h2>请输入标题</h2><p>在此处添加描述文字...</p></section>',
                }, {
                    id: 'text',
                    label: '文本模块',
                    content: '<div style="padding:10px; line-height:1.6; color:#333;">请输入您的内容...</div>',
                }, {
                    id: 'image',
                    label: '图片',
                    select: true,
                    content: { type: 'image' },
                    activate: true,
                }, {
                    id: 'video',
                    label: '视频模块',
                    content: {
                        type: 'video',
                        src: 'https://cdn.pixabay.com/vimeo/326194192/pixabay-326194192.mp4', // 默认演示
                        style: { width: '100%', height: '400px' }
                    }
                }, {
                    id: 'two-cols',
                    label: '两列布局',
                    content: '<div style="display:flex; gap:20px; padding:20px;"><div style="flex:1;">左侧内容</div><div style="flex:1;">右侧内容</div></div>',
                }
            ]
        },
    });

    // 保存函数
    function saveData() {
        const html = editor.getHtml();
        const css = editor.getCss();
        
        // 使用原生 Fetch 发送 AJAX
        const formData = new FormData();
        formData.append('action', 'save_canvas');
        formData.append('page_id', '<?= $current_page['id'] ?>');
        formData.append('html', html);
        formData.append('css', css);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(res => res.json()).then(data => {
            if(data.status === 'success') alert('前台页面已同步更新！');
        });
    }
    <?php endif; ?>
</script>