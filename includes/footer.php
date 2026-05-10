<?php
/**
 * 代世集团 - 统一页脚 (移动端全适配增强版)
 * 逻辑：管理后台链接仅在首页 index.php 显示
 */
// 获取当前运行的文件名
$current_file = basename($_SERVER['PHP_SELF']);
?>

<style>
    .site-footer {
        background-color: #F8FAFC;
        padding: clamp(40px, 8vw, 80px) 0 clamp(20px, 4vw, 40px);
        border-top: 1px solid #E2E8F0;
        font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        gap: clamp(30px, 5vw, 60px);
    }

    /* 左侧品牌区 */
    .footer-brand {
        flex: 2;
    }

    .footer-brand h3 {
        color: #1A3C6C;
        font-size: clamp(18px, 3vw, 22px);
        font-weight: 900;
        margin-bottom: 20px;
        letter-spacing: 2px;
    }

    .footer-brand p {
        color: #64748B;
        font-size: clamp(13px, 2vw, 14px);
        line-height: 1.8;
        max-width: 450px;
    }

    /* 快速链接区 */
    .footer-links {
        flex: 1;
        min-width: 120px;
    }

    .footer-links h4 {
        color: #1A3C6C;
        font-size: clamp(15px, 2vw, 17px);
        font-weight: 800;
        margin-bottom: 20px;
        position: relative;
    }
    
    .footer-links h4::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 30px;
        height: 2px;
        background: #1A3C6C;
    }

    .footer-links ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-links li {
        margin-bottom: 15px; /* 增加移动端点击间距 */
    }

    .footer-links a {
        color: #64748B;
        text-decoration: none;
        font-size: clamp(13px, 2vw, 14px);
        transition: 0.3s;
        display: inline-block;
    }

    .footer-links a:hover {
        color: #1A3C6C;
        transform: translateX(5px);
    }

    /* 底部版权栏 */
    .footer-bottom {
        max-width: 1200px;
        margin: clamp(30px, 5vw, 50px) auto 0;
        padding: 25px 20px 0;
        border-top: 1px solid #E2E8F0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; /* 允许换行 */
    }

    .copyright {
        color: #94A3B8;
        font-size: 12px;
        line-height: 1.5;
    }

    .admin-entry {
        color: #CBD5E1;
        text-decoration: none;
        font-size: 12px;
        transition: 0.3s;
        padding: 5px 0;
    }

    .admin-entry:hover {
        color: #64748B;
    }

    /* --- 移动端深度适配 --- */
    @media (max-width: 768px) {
        .footer-container { 
            flex-direction: column; 
            text-align: center; 
            gap: 40px;
        }

        .footer-brand p {
            margin: 0 auto;
        }

        .footer-links h4::after {
            left: 50%;
            transform: translateX(-50%);
        }

        .footer-bottom { 
            flex-direction: column; 
            gap: 15px; 
            text-align: center; 
        }

        .footer-links a:hover {
            transform: none; /* 移动端取消平移效果以免影响居中视觉 */
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <h3>代世集团</h3>
            <p>智领未来 · 重塑美学。代世集团致力于传媒、水疗、化妆品及高端服装等多产业布局，为全球用户提供极致的品质生活体验。</p>
        </div>

        <div class="footer-links">
            <h4>快速链接</h4>
            <ul>
                <li><a href="/index.php">官网首页</a></li>
                <li><a href="/business.php">集团业务</a></li>
                <li><a href="/news.php">新闻动态</a></li>
                <li><a href="/careers/index.php">人才招聘</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="copyright">
            © <?= date('Y') ?> 代世集团 DAISHI GROUP. 保留所有权利。
        </div>

        <?php if ($current_file === 'index.php' || $current_file === ''): ?>
            <a href="/admin/login.php" class="admin-entry">管理后台</a>
        <?php endif; ?>
    </div>
</footer>