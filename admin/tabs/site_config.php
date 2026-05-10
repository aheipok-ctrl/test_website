<?php
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