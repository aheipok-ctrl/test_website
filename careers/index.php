<?php
/**
 * 代世集团 - 人才招聘门户 (集成详情查询 + 深度人文关怀版)
 */
session_start();

// 1. 指向 config/db.php 路径
require_once dirname(__DIR__) . '/config/db.php'; 
$active_page = 'careers';

// --- 逻辑处理：响应查询请求 ---
if (isset($_POST['action']) && $_POST['action'] === 'check_status') {
    $q_no = strtoupper(trim($_POST['query_no'] ?? ''));
    $res = ['success' => false, 'data' => null];
    
    if (!empty($q_no)) {
        // 【核心修改】确保关联 job_postings 表获取所属公司 (company)
        $stmt = $pdo->prepare("
            SELECT r.*, j.job_title, j.company 
            FROM candidate_resumes r 
            LEFT JOIN job_postings j ON r.job_id = j.id 
            WHERE r.query_no = ?
        ");
        $stmt->execute([$q_no]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $res = ['success' => true, 'data' => $data];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

// 2. 数据读取保护
try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $jobs = $pdo->query("SELECT * FROM job_postings WHERE status = 'active' ORDER BY id DESC")->fetchAll();
} catch (PDOException $e) {
    $settings = []; $jobs = [];
}

$hero_video = $settings['careers_hero_video'] ?? '';
$hero_image = $settings['careers_hero_image'] ?? '';

// 3. 引用统一页头
include_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    body { background: #F8FAFC; color: #334155; margin: 0; padding: 0; overflow-x: hidden; font-family: "Alibaba PuHuiTi 2.0", "PingFang SC", sans-serif; }
    
    /* --- UI 样式 --- */
    .careers-hero { position: relative; height: clamp(350px, 50vh, 600px); display: flex; align-items: center; justify-content: center; overflow: hidden; background: #1A3C6C; }
    .bg-media { position: absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:1; opacity: 0.5; }
    .hero-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: linear-gradient(to bottom, rgba(26,60,108,0.2), rgba(26,60,108,0.8)); z-index:2; }
    .hero-text { position: relative; z-index: 3; color: #FFF; text-align: center; padding: 0 20px; }
    .hero-text h1 { font-size: clamp(32px, 5vw, 54px); font-weight: 900; letter-spacing: 4px; margin: 0; }
    
    .query-trigger { margin-top: 30px; display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: #FFF; padding: 12px 30px; border-radius: 50px; cursor: pointer; backdrop-filter: blur(10px); transition: 0.3s; font-weight: 800; }
    .query-trigger:hover { background: #FFF; color: #1A3C6C; }

    .container { max-width: 1100px; margin: -50px auto 60px; padding: 0 20px; position: relative; z-index: 10; min-height: 40vh; }
    .job-card { background: #FFF; padding: 30px 40px; border-radius: 24px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #E2E8F0; transition: 0.3s; }
    .job-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(26,60,108,0.1); }
    .salary { color: #EF4444; font-size: 20px; font-weight: 800; margin-right: 30px; }
    .apply-btn { background: #1A3C6C; color: #FFF; padding: 12px 35px; border-radius: 50px; font-weight: 700; text-decoration: none; }

    /* --- 查询弹窗 --- */
    #queryModal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); z-index: 9999; display: none; align-items: center; justify-content: center; }
    .query-card { background: #FFF; width: 90%; max-width: 550px; border-radius: 28px; padding: clamp(20px, 5vw, 40px); box-shadow: 0 25px 50px rgba(0,0,0,0.4); position: relative; max-height: 90vh; overflow-y: auto; }
    .q-close { position: absolute; top: 25px; right: 25px; font-size: 24px; color: #CBD5E1; cursor: pointer; }
    .q-input-group { display: flex; gap: 10px; margin: 25px 0; }
    .q-input { flex: 1; padding: 15px; border: 2px solid #E2E8F0; border-radius: 16px; font-size: 16px; outline: none; font-family: monospace; font-weight: 800; text-align: center; }
    .q-btn { background: #1A3C6C; color: #FFF; border: none; padding: 0 25px; border-radius: 16px; font-weight: 800; cursor: pointer; }

    /* 状态与详情显示 */
    #qResult { display: none; text-align: left; }
    .detail-card { background: #F0F7FF; border-radius: 18px; padding: 25px; border: 1px solid #CFE5FF; margin-top: 20px; }
    .detail-card h4 { margin: 0 0 15px; color: #1A3C6C; display: flex; align-items: center; gap: 8px; font-size: 16px; }
    .info-row { display: flex; margin-bottom: 10px; font-size: 13px; line-height: 1.4; }
    .info-label { width: 85px; color: #64748B; flex-shrink: 0; font-weight: bold; }
    .info-val { color: #1E293B; font-weight: 800; }

    .status-tracker { background: #F8FAFC; padding: 20px; border-radius: 18px; border: 1px solid #E2E8F0; margin-top: 20px; }
    .status-step { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px; position: relative; }
    .step-dot { width: 14px; height: 14px; background: #CBD5E1; border-radius: 50%; z-index: 2; margin-top: 4px; }
    .status-step.active .step-dot { background: #10B981; box-shadow: 0 0 0 4px rgba(16,185,129,0.2); }
    .status-step.active .step-label { color: #1A3C6C; font-weight: 900; }
    .step-label { font-size: 14px; color: #94A3B8; }

    @media (max-width: 768px) {
        .job-card { flex-direction: column; align-items: flex-start; gap: 20px; padding: 25px; }
    }
</style>

<section class="careers-hero">
    <?php if (!empty($hero_video)): ?><video class="bg-media" autoplay loop muted playsinline><source src="<?= htmlspecialchars($hero_video) ?>" type="video/mp4"></video><?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-text">
        <h1>加入代世集团</h1>
        <p>与其等待未来，不如与我们一起创造未来</p>
        <div class="query-trigger" onclick="toggleQuery(true)"><i class="fa-solid fa-magnifying-glass"></i> 查询应聘进度</div>
    </div>
</section>

<div class="container">
    <div class="job-list">
        <?php foreach($jobs as $j): ?>
        <div class="job-card">
            <div class="job-info">
                <h3 style="margin:0 0 10px;"><?= htmlspecialchars($j['job_title']) ?></h3>
                <span class="tag"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($j['location']) ?></span>
                <span class="tag"><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($j['experience'] ?? '不限') ?></span>
            </div>
            <div style="display:flex; align-items:center; flex-wrap:wrap;">
                <div class="salary"><?= htmlspecialchars($j['salary']) ?></div>
                <a href="/job_apply.php?id=<?= $j['id'] ?>" class="apply-btn">立即申请</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="queryModal">
    <div class="query-card">
        <i class="fa-solid fa-xmark q-close" onclick="toggleQuery(false)"></i>
        <h2 style="color:#1A3C6C; margin:0;">应聘进度查询</h2>
        <div class="q-input-group">
            <input type="text" id="qNoInput" class="q-input" placeholder="输入 DS 开头编号">
            <button class="q-btn" onclick="startQuery()">查询</button>
        </div>

        <div id="qLoader" style="display:none; color:#1A3C6C; text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> 正在检索档案...</div>
        <div id="qResult"></div>
        <div id="qError" style="display:none; color:#EF4444; font-size:14px; font-weight:800; text-align:center; margin-top:15px;"><i class="fa-solid fa-circle-exclamation"></i> 未找到该编号，请核对重试</div>

        <div style="margin-top:30px; padding:15px; background:#FFF7ED; border-radius:16px; border:1px solid #FFEDD5; text-align:center;">
            <p style="color:#C2410C; font-size:12px; margin:0;">编号是您查询进度的唯一凭证，请妥善保管。</p>
        </div>
    </div>
</div>

<script>
function toggleQuery(show) { document.getElementById('queryModal').style.display = show ? 'flex' : 'none'; }

async function startQuery() {
    const qNo = document.getElementById('qNoInput').value.trim();
    if(!qNo) return;
    const loader = document.getElementById('qLoader');
    const resultBox = document.getElementById('qResult');
    const errorBox = document.getElementById('qError');

    loader.style.display = 'block'; resultBox.style.display = 'none'; errorBox.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('action', 'check_status');
        formData.append('query_no', qNo);

        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const res = await response.json();

        loader.style.display = 'none';
        if(res.success) {
            renderStatus(res.data);
        } else { errorBox.style.display = 'block'; }
    } catch (e) { loader.style.display = 'none'; errorBox.style.display = 'block'; }
}

function renderStatus(data) {
    const box = document.getElementById('qResult');
    const steps = [
        { k: '待筛选', l: '简历初筛中' },
        { k: '面试中', l: '面试安排中' },
        { k: '已录取', l: '录用流程中' },
        { k: '不合适', l: '人才库储备' }
    ];

    let html = `<div style="font-weight:900; color:#1A3C6C; font-size:18px; margin-bottom:20px; border-bottom:2px solid #F1F5F9; padding-bottom:15px;">
                ${data.candidate_name} · <span style="color:#64748B; font-size:14px;">${data.job_title}</span></div>`;

    if (data.status === '面试中') {
        html += `
            <div class="detail-card">
                <h4><i class="fa-solid fa-calendar-check"></i> 面试安排详情</h4>
                <div class="info-row"><div class="info-label">面试时间</div><div class="info-val">${data.interview_time || '待定'}</div></div>
                <div class="info-row"><div class="info-label">面试地点</div><div class="info-val">${data.interview_loc || '另行通知'}</div></div>
                <div class="info-row"><div class="info-label">面试官</div><div class="info-val">${data.interviewer || '--'}</div></div>
                <div class="info-row"><div class="info-label">联系方式</div><div class="info-val">${data.interview_phone || '--'}</div></div>
            </div>
        `;
    } else if (data.status === '已录取') {
        html += `
            <div class="detail-card" style="background:#ECFDF5; border-color:#A7F3D0;">
                <h4 style="color:#15803D;"><i class="fa-solid fa-award"></i> 录用指引详情</h4>
                <div class="info-row"><div class="info-label">入职单位</div><div class="info-val">${data.hire_company || '--'}</div></div>
                <div class="info-row"><div class="info-label">所属部门</div><div class="info-val">${data.hire_dept || '--'}</div></div>
                <div class="info-row"><div class="info-label">入职职位</div><div class="info-val">${data.hire_pos || '--'}</div></div>
                <div class="info-row"><div class="info-label">报到地点</div><div class="info-val">${data.hire_loc || '--'}</div></div>
                <div class="info-row"><div class="info-label">报到联系人</div><div class="info-val">${data.hire_contact} (${data.hire_phone})</div></div>
            </div>
        `;
    } else if (data.status === '不合适') {
        const companyName = data.company || '代世集团';
        const jobTitle = data.job_title || '应聘';
        html += `
            <div class="detail-card" style="background:#F8FAFC; border-color:#E2E8F0; color:#64748B;">
                <p style="margin:0; line-height:1.8; text-align:justify; font-size:14px;">
                    <strong>${data.candidate_name}</strong> 您好：<br><br>
                    感谢您参加 <strong>${companyName}</strong> - <strong>${jobTitle}</strong> 岗位的面试。
                    在对您的背景和技能进行了认真评估后，我们暂时无法为您提供与您职业技能所匹配的工作。在此深表歉意，并非常感谢您的理解与付出。<br><br>
                    衷心祝愿您早日找到心仪的工作。我们也欢迎您再次投递本集团的其他岗位。
                </p>
                <div style="text-align:right; margin-top:20px; font-weight:800; color:#1A3C6C;">
                    ${companyName} 招聘团队
                </div>
            </div>
        `;
    }

    html += `<div class="status-tracker">`;
    steps.forEach((s) => {
        if (data.status === '不合适' && s.k !== '不合适') return;
        const isActive = (data.status === s.k);
        html += `<div class="status-step ${isActive ? 'active' : ''}">
                    <div class="step-dot"></div>
                    <div class="step-label">${s.l}</div>
                 </div>`;
    });
    html += `</div>`;

    box.innerHTML = html;
    box.style.display = 'block';
}
</script>

<?php include_once dirname(__DIR__) . '/includes/header.php'; ?>