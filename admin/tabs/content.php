<?php
/**
 * 代世集团 VMS - 官网内容全量配置中心 (全功能恢复 + 手机端适配版)
 * 修复：补全关于我们、新闻中心、人才招聘等页面的素材管理入口
 */
if (!can('content')) die("权限不足");

// 1. 初始化全量配置项
$setting_keys = [
    'site_logo', 
    'hero_title', 'hero_subtitle', 'hero_video', 'hero_image',
    'biz_hero_video', 'biz_hero_image', 'biz_media_asset', 'biz_spa_asset', 'biz_dk_asset',
    'about_hero_video', 'about_hero_image', 
    'news_hero_video', 'news_hero_image',
    'careers_hero_video', 'careers_hero_image'
];

foreach($setting_keys as $key) {
    $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('$key', '')");
}

// 1.1 自动创建/更新历程表结构
$pdo->exec("CREATE TABLE IF NOT EXISTS development_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year VARCHAR(20) NOT NULL,
    month VARCHAR(20) DEFAULT '',
    title VARCHAR(100) NOT NULL,
    content TEXT,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

try {
    $pdo->exec("ALTER TABLE development_history ADD COLUMN month VARCHAR(20) DEFAULT '' AFTER year");
} catch (PDOException $e) {}

// 2. 处理保存逻辑
if (isset($_POST['save_all_content'])) {
    // 2.1 保存文字内容
    $texts = ['hero_title', 'hero_subtitle'];
    foreach ($texts as $t) {
        if (isset($_POST[$t])) {
            $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")
                ->execute([trim($_POST[$t]), $t]);
        }
    }
    
    // 2.2 处理素材上传
    $base_dir = dirname(dirname(__DIR__)); 
    $upload_rel_path = '/uploads/media/';
    $upload_abs_path = $base_dir . $upload_rel_path;
    if (!is_dir($upload_abs_path)) mkdir($upload_abs_path, 0777, true);
    
    foreach ($setting_keys as $f) {
        if (isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
            $new_name = $f . '_' . time() . '.' . $ext;
            $target_file = $upload_abs_path . $new_name;
            if (move_uploaded_file($_FILES[$f]['tmp_name'], $target_file)) {
                $db_path = $upload_rel_path . $new_name;
                $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")
                    ->execute([$db_path, $f]);
            }
        }
    }
    echo "<script>alert('全站素材同步成功！'); location.href='?tab=site_config';</script>";
    exit;
}

// 历程处理
if (isset($_POST['add_history'])) {
    $pdo->prepare("INSERT INTO development_history (year, month, title, content) VALUES (?, ?, ?, ?)")
        ->execute([trim($_POST['h_year']), trim($_POST['h_month']), trim($_POST['h_title']), trim($_POST['h_content'])]);
    echo "<script>location.href='?tab=site_config';</script>"; exit;
}
if (isset($_GET['del_history_id'])) {
    $pdo->prepare("DELETE FROM development_history WHERE id = ?")->execute([$_GET['del_history_id']]);
    echo "<script>location.href='?tab=site_config';</script>"; exit;
}

$settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$histories = $pdo->query("SELECT * FROM development_history ORDER BY year DESC, month DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .admin-container { max-width: 1200px; margin: 0 auto; padding-bottom: 120px; }
    .page-header { margin-bottom: 25px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .page-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; font-size: clamp(18px, 4vw, 24px); }

    .visual-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); 
        gap: 20px; margin-bottom: 30px; 
    }
    .visual-card { background: #FFF; border-radius: 12px; border: 1px solid #E2E8F0; padding: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .visual-card label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 10px; }
    .preview-window { width: 100%; height: 150px; background: #000; border-radius: 8px; margin-bottom: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid #F1F5F9; position: relative; }
    .preview-window img, .preview-window video { width: 100%; height: 100%; object-fit: cover; }
    .no-file { color: #FFF; font-size: 11px; opacity: 0.5; }

    .content-section { background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: clamp(15px, 4vw, 25px); margin-bottom: 25px; }
    .section-title { font-size: 15px; font-weight: 900; color: #1A3C6C; margin-bottom: 20px; border-bottom: 2px solid #F1F5F9; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: clamp(15px, 3vw, 20px); }
    .input-group { margin-bottom: 10px; }
    .input-group label { display: block; font-size: 11px; font-weight: 800; color: #64748B; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; box-sizing: border-box; outline: none; }
    .file-path { display: block; font-size: 10px; color: #94A3B8; margin-top: 5px; word-break: break-all; }
    
    .history-input-grid { display: grid; gap: 15px; background: #F8FAFC; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
    @media (min-width: 992px) { .history-input-grid { grid-template-columns: 100px 100px 200px 1fr 120px; align-items: end; } }

    .table-wrapper { width: 100%; overflow-x: auto; }
    .history-table { width: 100%; border-collapse: collapse; min-width: 700px; }
    .history-table th { text-align: left; background: #F8FAFC; padding: 12px; font-size: 11px; color: #64748B; }
    .history-table td { padding: 12px; border-bottom: 1px solid #F1F5F9; font-size: 13px; }
    
    .btn-del { color: #EF4444; text-decoration: none; font-weight: 800; font-size: 12px; }

    .save-btn { 
        position: fixed; bottom: 20px; right: 20px; left: 20px; 
        background: #1A3C6C; color: #FFF; border: none; padding: 16px; 
        border-radius: 12px; font-weight: 800; cursor: pointer; 
        box-shadow: 0 10px 25px rgba(26,60,108,0.3); z-index: 1000; text-align: center;
    }
    @media (min-width: 768px) { .save-btn { width: auto; left: auto; padding: 15px 50px; border-radius: 50px; } }
</style>

<div class="admin-container">
    <div class="page-header">
        <h2>官网全量配置中心</h2>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="visual-grid">
            <div class="visual-card">
                <label>全站 Logo (PNG透明最佳)</label>
                <div class="preview-window" style="background:#1A3C6C;">
                    <?php if(!empty($settings['site_logo'])): ?><img src="<?= $settings['site_logo'] ?>" style="object-fit:contain; padding:20px;"><?php else: ?><span class="no-file">未上传</span><?php endif; ?>
                </div>
                <input type="file" name="site_logo" class="form-control">
            </div>

            <div class="visual-card">
                <label>首页背景视频 (Hero Video)</label>
                <div class="preview-window">
                    <?php if(!empty($settings['hero_video'])): ?><video src="<?= $settings['hero_video'] ?>" muted autoplay loop></video><?php else: ?><span class="no-file">未上传</span><?php endif; ?>
                </div>
                <input type="file" name="hero_video" class="form-control">
            </div>

            <div class="visual-card">
                <label>首页背景静态图 (Hero Image)</label>
                <div class="preview-window">
                    <?php if(!empty($settings['hero_image'])): ?><img src="<?= $settings['hero_image'] ?>"><?php else: ?><span class="no-file">未上传</span><?php endif; ?>
                </div>
                <input type="file" name="hero_image" class="form-control">
            </div>
        </div>

        <div class="content-section">
            <div class="section-title"><i class="fa-solid fa-font"></i> 首页文字配置</div>
            <div class="form-grid">
                <div class="input-group">
                    <label>首页主标题</label>
                    <input type="text" name="hero_title" class="form-control" value="<?= htmlspecialchars($settings['hero_title']??'') ?>">
                </div>
                <div class="input-group">
                    <label>首页副标题</label>
                    <input type="text" name="hero_subtitle" class="form-control" value="<?= htmlspecialchars($settings['hero_subtitle']??'') ?>">
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-title"><i class="fa-solid fa-images"></i> 页面顶部横幅 (Hero) 统一管理</div>
            <div class="form-grid">
                <div class="visual-card">
                    <label>集团业务页横幅 (视频/图片)</label>
                    <div class="preview-window">
                        <?php if(!empty($settings['biz_hero_video'])): ?><video src="<?= $settings['biz_hero_video'] ?>" muted autoplay loop></video><?php else: ?><img src="<?= $settings['biz_hero_image'] ?>"><?php endif; ?>
                    </div>
                    <input type="file" name="biz_hero_video" class="form-control" title="视频">
                    <input type="file" name="biz_hero_image" class="form-control" title="图片" style="margin-top:5px;">
                </div>

                <div class="visual-card">
                    <label>关于代世页横幅</label>
                    <div class="preview-window">
                        <?php if(!empty($settings['about_hero_video'])): ?><video src="<?= $settings['about_hero_video'] ?>" muted autoplay loop></video><?php else: ?><img src="<?= $settings['about_hero_image'] ?>"><?php endif; ?>
                    </div>
                    <input type="file" name="about_hero_video" class="form-control">
                    <input type="file" name="about_hero_image" class="form-control" style="margin-top:5px;">
                </div>

                <div class="visual-card">
                    <label>新闻中心页横幅</label>
                    <div class="preview-window">
                        <?php if(!empty($settings['news_hero_video'])): ?><video src="<?= $settings['news_hero_video'] ?>" muted autoplay loop></video><?php else: ?><img src="<?= $settings['news_hero_image'] ?>"><?php endif; ?>
                    </div>
                    <input type="file" name="news_hero_video" class="form-control">
                    <input type="file" name="news_hero_image" class="form-control" style="margin-top:5px;">
                </div>

                <div class="visual-card">
                    <label>人才招聘页横幅</label>
                    <div class="preview-window">
                        <?php if(!empty($settings['careers_hero_video'])): ?><video src="<?= $settings['careers_hero_video'] ?>" muted autoplay loop></video><?php else: ?><img src="<?= $settings['careers_hero_image'] ?>"><?php endif; ?>
                    </div>
                    <input type="file" name="careers_hero_video" class="form-control">
                    <input type="file" name="careers_hero_image" class="form-control" style="margin-top:5px;">
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="section-title"><i class="fa-solid fa-cubes"></i> 业务板块专用素材</div>
            <div class="form-grid">
                <div class="input-group">
                    <label>代世传媒板块素材 (图片/海报)</label>
                    <input type="file" name="biz_media_asset" class="form-control">
                    <span class="file-path"><?= $settings['biz_media_asset'] ?></span>
                </div>
                <div class="input-group">
                    <label>七号汤泉板块素材 (图片/海报)</label>
                    <input type="file" name="biz_spa_asset" class="form-control">
                    <span class="file-path"><?= $settings['biz_spa_asset'] ?></span>
                </div>
                <div class="input-group">
                    <label>DK化妆品板块素材 (图片/海报)</label>
                    <input type="file" name="biz_dk_asset" class="form-control">
                    <span class="file-path"><?= $settings['biz_dk_asset'] ?></span>
                </div>
            </div>
        </div>
        
        <button type="submit" name="save_all_content" class="save-btn">
            <i class="fa-solid fa-cloud-arrow-up"></i> 保存并同步素材
        </button>
    </form>

    <div class="content-section" style="margin-top: 30px;">
        <div class="section-title">发展历程管理</div>
        <form method="POST" class="history-input-grid">
            <div class="input-group"><label>年份</label><input type="text" name="h_year" class="form-control" placeholder="如: 2024" required></div>
            <div class="input-group"><label>月份</label><input type="text" name="h_month" class="form-control" placeholder="如: 10月" required></div>
            <div class="input-group"><label>里程碑标题</label><input type="text" name="h_title" class="form-control" placeholder="标题" required></div>
            <div class="input-group"><label>详细描述</label><input type="text" name="h_content" class="form-control" placeholder="具体描述..."></div>
            <button type="submit" name="add_history" style="height:45px; background:#1A3C6C; color:#FFF; border:none; border-radius:8px; font-weight:800; cursor:pointer;">新增节点</button>
        </form><?php
/**
 * 代世集团 VMS - 官网全局配置中心
 * 功能：Banner管理、Logo上传、SEO信息、备案号、联系方式
 */
if (!can('admin')) die("权限不足");

// 1. 定义本页面处理的所有配置项
$config_keys = [
    // 视觉素材
    'site_logo', 
    'banner_home_video', 'banner_home_image',
    'banner_biz_video', 'banner_biz_image',
    'banner_about_video', 'banner_about_image',
    'banner_news_video', 'banner_news_image',
    'banner_careers_video', 'banner_careers_image',
    // SEO & 备案
    'seo_keywords', 'seo_description', 'site_icp', 'site_copyright',
    // 联系方式
    'contact_phone', 'contact_email', 'contact_address', 'contact_wechat'
];

// 初始化数据库配置项（防止首次使用报错）
foreach($config_keys as $key) {
    $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('$key', '')");
}

// 2. 处理保存逻辑
if (isset($_POST['save_config'])) {
    $upload_dir = dirname(dirname(__DIR__)) . '/uploads/config/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // A. 处理文本类更新
    $text_keys = [
        'seo_keywords', 'seo_description', 'site_icp', 'site_copyright',
        'contact_phone', 'contact_email', 'contact_address'
    ];
    foreach ($text_keys as $tk) {
        if (isset($_POST[$tk])) {
            $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")
                ->execute([trim($_POST[$tk]), $tk]);
        }
    }

    // B. 处理文件上传（Logo 和 Banners）
    $file_keys = [
        'site_logo', 'banner_home_video', 'banner_home_image', 'banner_biz_video', 'banner_biz_image',
        'banner_about_video', 'banner_about_image', 'banner_news_video', 'banner_news_image', 'banner_careers_video', 'banner_careers_image'
    ];
    foreach ($file_keys as $fk) {
        if (isset($_FILES[$fk]) && $_FILES[$fk]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$fk]['name'], PATHINFO_EXTENSION));
            $new_filename = $fk . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$fk]['tmp_name'], $upload_dir . $new_filename)) {
                $db_path = '/uploads/config/' . $new_filename;
                $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")
                    ->execute([$db_path, $fk]);
            }
        }
    }
    echo "<script>alert('全局配置已实时同步至官网！'); location.href='?tab=site_config';</script>";
    exit;
}

// 3. 读取当前配置
$s = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<style>
    .config-container { max-width: 1200px; margin: 0 auto; padding-bottom: 100px; }
    .page-header { margin-bottom: 25px; border-left: 6px solid #1A3C6C; padding-left: 15px; }
    .page-header h2 { margin: 0; color: #1A3C6C; font-weight: 900; }

    /* 模块样式 */
    .config-section { background: #FFF; border-radius: 20px; border: 1px solid #E2E8F0; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .section-title { font-size: 16px; font-weight: 800; color: #1A3C6C; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #F1F5F9; padding-bottom: 15px; }

    /* 网格布局 */
    .asset-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
    .asset-card { background: #F8FAFC; border-radius: 12px; padding: 15px; border: 1px solid #E2E8F0; }
    .asset-card label { display: block; font-size: 13px; font-weight: 800; color: #475569; margin-bottom: 10px; }

    /* 预览窗 */
    .preview-box { width: 100%; height: 120px; background: #1A3C6C; border-radius: 8px; margin-bottom: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .preview-box img, .preview-box video { width: 100%; height: 100%; object-fit: cover; }
    .preview-box.logo-bg { background: #f0f4f8; }
    .preview-box.logo-bg img { object-fit: contain; padding: 10px; }
    .empty-hint { color: rgba(255,255,255,0.4); font-size: 11px; }

    /* 输入框样式 */
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 12px; font-weight: 800; color: #64748B; margin-bottom: 6px; }
    .input-ctrl { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; outline: none; transition: 0.2s; font-size: 14px; box-sizing: border-box; }
    .input-ctrl:focus { border-color: #1A3C6C; box-shadow: 0 0 0 3px rgba(26,60,108,0.1); }
    textarea.input-ctrl { min-height: 80px; resize: vertical; }

    /* 底部保存条 */
    .sticky-bar { position: fixed; bottom: 20px; left: 280px; right: 20px; background: #FFF; padding: 15px 30px; border-radius: 50px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #1A3C6C; z-index: 1000; }
    .save-btn { background: #1A3C6C; color: #FFF; border: none; padding: 12px 40px; border-radius: 25px; font-weight: 800; cursor: pointer; transition: 0.3s; }
    .save-btn:hover { transform: scale(1.02); background: #254a85; }

    @media (max-width: 1024px) { .sticky-bar { left: 20px; } }
</style>

<div class="config-container">
    <div class="page-header">
        <h2>官网全局配置中心</h2>
        <p style="font-size:12px; color:#94A3B8; margin-top:5px;">管理全站视觉素材、SEO优化及企业核心公开信息</p>
    </div>

    <form method="POST" enctype="multipart/form-data">
        
        <div class="config-section">
            <div class="section-title"><i class="fa-solid fa-eye"></i> 品牌与核心视觉</div>
            <div class="asset-grid">
                <div class="asset-card">
                    <label>网站 Logo (PNG透明背景最佳)</label>
                    <div class="preview-box logo-bg">
                        <?php if($s['site_logo']): ?><img src="<?= $s['site_logo'] ?>"><?php else: ?><span class="empty-hint">未设置</span><?php endif; ?>
                    </div>
                    <input type="file" name="site_logo" accept="image/*">
                </div>
                <div class="asset-card">
                    <label>首页背景视频 (MP4)</label>
                    <div class="preview-box">
                        <?php if($s['banner_home_video']): ?><video src="<?= $s['banner_home_video'] ?>" muted loop autoplay></video><?php else: ?><span class="empty-hint">未设置</span><?php endif; ?>
                    </div>
                    <input type="file" name="banner_home_video" accept="video/mp4">
                </div>
                <div class="asset-card">
                    <label>首页备选静态图 (JPG/PNG)</label>
                    <div class="preview-box">
                        <?php if($s['banner_home_image']): ?><img src="<?= $s['banner_home_image'] ?>"><?php else: ?><span class="empty-hint">未设置</span><?php endif; ?>
                    </div>
                    <input type="file" name="banner_home_image" accept="image/*">
                </div>
            </div>
        </div>

        <div class="config-section">
            <div class="section-title"><i class="fa-solid fa-images"></i> 分页面顶部横幅 (Banners)</div>
            <div class="asset-grid">
                <div class="asset-card">
                    <label>集团业务页 Banner (图/影)</label>
                    <div class="preview-box">
                        <?php if($s['banner_biz_video']): ?><video src="<?= $s['banner_biz_video'] ?>" muted loop autoplay></video><?php else: ?><img src="<?= $s['banner_biz_image'] ?>"><?php endif; ?>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <input type="file" name="banner_biz_video" accept="video/mp4" title="上传视频">
                        <input type="file" name="banner_biz_image" accept="image/*" title="上传图片">
                    </div>
                </div>
                <div class="asset-card">
                    <label>关于代世页 Banner (图/影)</label>
                    <div class="preview-box">
                        <?php if($s['banner_about_video']): ?><video src="<?= $s['banner_about_video'] ?>" muted loop autoplay></video><?php else: ?><img src="<?= $s['banner_about_image'] ?>"><?php endif; ?>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <input type="file" name="banner_about_video" accept="video/mp4">
                        <input type="file" name="banner_about_image" accept="image/*">
                    </div>
                </div>
                <div class="asset-card">
                    <label>人才招聘页 Banner (图/影)</label>
                    <div class="preview-box">
                        <?php if($s['banner_careers_video']): ?><video src="<?= $s['banner_careers_video'] ?>" muted loop autoplay></video><?php else: ?><img src="<?= $s['banner_careers_image'] ?>"><?php endif; ?>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <input type="file" name="banner_careers_video" accept="video/mp4">
                        <input type="file" name="banner_careers_image" accept="image/*">
                    </div>
                </div>
            </div>
        </div>

        <div class="config-section">
            <div class="section-title"><i class="fa-solid fa-magnifying-glass-chart"></i> SEO 与 站点信息</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div>
                    <div class="form-group">
                        <label>SEO 关键词 (Keywords, 英文逗号隔开)</label>
                        <input type="text" name="seo_keywords" class="input-ctrl" value="<?= htmlspecialchars($s['seo_keywords']) ?>">
                    </div>
                    <div class="form-group">
                        <label>SEO 站点描述 (Description)</label>
                        <textarea name="seo_description" class="input-ctrl"><?= htmlspecialchars($s['seo_description']) ?></textarea>
                    </div>
                </div>
                <div>
                    <div class="form-group">
                        <label>ICP 备案号 (显示在页脚)</label>
                        <input type="text" name="site_icp" class="input-ctrl" value="<?= htmlspecialchars($s['site_icp']) ?>" placeholder="如：京ICP备12345678号">
                    </div>
                    <div class="form-group">
                        <label>版权信息 (Copyright)</label>
                        <input type="text" name="site_copyright" class="input-ctrl" value="<?= htmlspecialchars($s['site_copyright']) ?>" placeholder="如：© 2024 代世集团 版权所有">
                    </div>
                </div>
            </div>
        </div>

        <div class="config-section">
            <div class="section-title"><i class="fa-solid fa-headset"></i> 企业联系方式 (页脚显示)</div>
            <div class="asset-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="form-group">
                    <label>联系电话</label>
                    <input type="text" name="contact_phone" class="input-ctrl" value="<?= htmlspecialchars($s['contact_phone']) ?>">
                </div>
                <div class="form-group">
                    <label>企业邮箱</label>
                    <input type="text" name="contact_email" class="input-ctrl" value="<?= htmlspecialchars($s['contact_email']) ?>">
                </div>
                <div class="form-group">
                    <label>办公地址</label>
                    <input type="text" name="contact_address" class="input-ctrl" value="<?= htmlspecialchars($s['contact_address']) ?>">
                </div>
            </div>
        </div>

        <div class="sticky-bar">
            <div style="color:#64748B; font-size:13px;"><i class="fa-solid fa-circle-info"></i> 修改将在点击保存后全局生效。</div>
            <button type="submit" name="save_config" class="save-btn">保存并同步全站</button>
        </div>

    </form>
</div>

        <div class="table-wrapper">
            <table class="history-table">
                <thead><tr><th>年份</th><th>月份</th><th>标题</th><th>内容</th><th style="text-align:right;">操作</th></tr></thead>
                <tbody>
                    <?php foreach($histories as $h): ?>
                    <tr>
                        <td style="font-weight:900; color:#1A3C6C;"><?= htmlspecialchars($h['year']) ?></td>
                        <td style="font-weight:700; color:#3B82F6;"><?= htmlspecialchars($h['month']) ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars($h['title']) ?></td>
                        <td style="color:#64748B;"><?= htmlspecialchars($h['content']) ?></td>
                        <td style="text-align:right;"><a href="?tab=site_config&del_history_id=<?= $h['id'] ?>" class="btn-del" onclick="return confirm('确定删除？')">删除</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>