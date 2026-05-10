<?php
/**
 * 代世集团官网 - 职位详情与简历投递 (流水号弹窗版)
 */
require_once 'config/db.php';
$active_page = 'careers'; 

// --- 1. 数据库加固 (增加查询编号字段) ---
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
$pdo->exec("CREATE TABLE IF NOT EXISTS candidate_resumes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT DEFAULT 0,
    query_no VARCHAR(50) UNIQUE,
    candidate_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    resume_file VARCHAR(255),
    status VARCHAR(50) DEFAULT '待筛选',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 补齐 query_no 字段 (针对已存在的表)
$cols = $pdo->query("SHOW COLUMNS FROM candidate_resumes")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('query_no', $cols)) { $pdo->exec("ALTER TABLE candidate_resumes ADD COLUMN query_no VARCHAR(50) AFTER job_id"); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 2. 获取职位信息 ---
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM job_postings WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("<div style='text-align:center; padding:100px;'><h3>职位不存在</h3><a href='careers.php'>返回</a></div>");
}

$success_data = null; // 用于存放弹窗显示的数据
$msg = '';

// --- 3. 处理投递逻辑 ---
if (isset($_POST['submit_application'])) {
    $c_name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // 生成唯一查询流水号: DS + 年月日 + 4位随机数
    $query_no = "DS" . date('Ymd') . strtoupper(substr(uniqid(), -4));

    if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['pdf', 'doc', 'docx'])) {
            $upload_dir = 'uploads/resumes/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $destination = $upload_dir . 'RES_' . $phone . '_' . time() . '.' . $file_ext;
            
            if (move_uploaded_file($_FILES['resume_file']['tmp_name'], $destination)) {
                try {
                    $sql = "INSERT INTO candidate_resumes (job_id, query_no, candidate_name, phone, email, resume_file, status) VALUES (?, ?, ?, ?, ?, ?, '待筛选')";
                    $pdo->prepare($sql)->execute([$job_id, $query_no, $c_name, $phone, $email, $destination]);
                    
                    // 标记成功并准备弹窗数据
                    $success_data = [
                        'name' => $c_name,
                        'no' => $query_no
                    ];
                } catch (Exception $e) { $msg = "<div class='error-msg'>提交失败，请稍后再试。</div>"; }
            }
        } else { $msg = "<div class='error-msg'>格式错误：仅支持 PDF, DOC, DOCX。</div>"; }
    } else { $msg = "<div class='error-msg'>请上传个人简历。</div>"; }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['job_title']) ?> - 代世集团招聘</title>
    <?php include_once 'includes/header.php'; ?>
    <style>
        body { background: #F8FAFC; color: #334155; font-family: "PingFang SC", sans-serif; }
        .container { max-width: 1100px; margin: 40px auto; display: grid; grid-template-columns: 1.8fr 1fr; gap: 30px; padding: 0 20px; }
        .box { background: #FFF; padding: 35px; border-radius: 20px; border: 1px solid #E2E8F0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .job-title { font-size: 32px; color: #1A3C6C; margin: 0 0 15px; font-weight: 800; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #F1F5F9; }
        .meta-tag { background: #F1F5F9; color: #64748B; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .content-html { line-height: 1.8; color: #475569; font-size: 15px; }
        .form-title { font-size: 20px; color: #1A3C6C; margin: 0 0 20px; font-weight: 800; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: #64748B; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 13px; border-radius: 10px; border: 1px solid #CBD5E1; box-sizing: border-box; font-size: 15px; outline: none; }
        .form-control:focus { border-color: #1A3C6C; box-shadow: 0 0 0 4px rgba(26,60,108,0.05); }
        .submit-btn { width: 100%; background: #1A3C6C; color: #FFF; border: none; padding: 15px; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .error-msg { background: #FEF2F2; color: #DC2626; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #FECACA; text-align: center; font-size: 14px; }
        
        /* 投递成功弹窗样式 */
        #successModal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); z-index: 9999; display: flex; align-items: center; justify-content: center; }
        .modal-card { background: #FFF; width: 90%; max-width: 480px; border-radius: 24px; padding: 40px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.2); animation: popScale 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.28); }
        @keyframes popScale { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        .success-icon { width: 80px; height: 80px; background: #DCFCE7; color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .query-box { background: #F8FAFC; border: 2px dashed #E2E8F0; padding: 20px; border-radius: 16px; margin: 25px 0; position: relative; }
        .query-no { font-family: "Monaco", monospace; font-size: 24px; color: #1A3C6C; font-weight: 900; letter-spacing: 2px; }
        .copy-tip { font-size: 11px; color: #94A3B8; margin-top: 8px; font-weight: bold; }
        .btn-close { width: 100%; background: #1A3C6C; color: #FFF; border: none; padding: 14px; border-radius: 12px; font-weight: 800; cursor: pointer; }

        @media (max-width: 991px) { .container { grid-template-columns: 1fr; } .sidebar-box { position: static !important; } }
    </style>
</head>
<body>

<div class="container">
    <div class="box">
        <h1 class="job-title"><?= htmlspecialchars($job['job_title']) ?></h1>
        <div class="job-meta">
            <span class="meta-tag"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($job['company'] ?? '代世集团') ?></span>
            <span class="meta-tag"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($job['location']) ?></span>
            <span class="meta-tag" style="color:#EF4444; background:#FEF2F2;"><i class="fa-solid fa-sack-dollar"></i> <?= htmlspecialchars($job['salary']) ?></span>
            <span class="meta-tag"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($job['education']) ?></span>
        </div>
        <div class="content-html"><?= $job['job_description'] ?></div>
    </div>

    <div class="sidebar-box">
        <div class="box" style="position: sticky; top: 100px;">
            <h3 class="form-title">立即投递简历</h3>
            <?= $msg ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group"><label>姓名 *</label><input type="text" name="name" class="form-control" required placeholder="真实姓名"></div>
                <div class="form-group"><label>手机号 *</label><input type="tel" name="phone" class="form-control" required placeholder="您的联系电话"></div>
                <div class="form-group"><label>邮箱</label><input type="email" name="email" class="form-control" placeholder="选填"></div>
                <div class="form-group">
                    <label>简历附件 *</label>
                    <input type="file" name="resume_file" class="form-control" accept=".pdf,.doc,.docx" required>
                    <small style="color:#94A3B8; display:block; margin-top:8px;">支持 PDF / Word 格式</small>
                </div>
                <button type="submit" name="submit_application" class="submit-btn"><i class="fa-solid fa-paper-plane"></i> 确认提交</button>
            </form>
        </div>
    </div>
</div>

<?php if ($success_data): ?>
<div id="successModal">
    <div class="modal-card">
        <div class="success-icon"><i class="fa-solid fa-check"></i></div>
        <h2 style="color:#1E293B; margin:0;">简历投递成功！</h2>
        <p style="color:#64748B; font-size:14px; margin-top:10px;">感谢 <?= htmlspecialchars($success_data['name']) ?> 投递代世集团，您的简历已进入初筛环节。</p>
        
        <div class="query-box">
            <div style="font-size:12px; color:#94A3B8; margin-bottom:5px; font-weight:800;">应聘状态查询编号</div>
            <div class="query-no" id="qNo"><?= $success_data['no'] ?></div>
            <div class="copy-tip">请截图或记录，这是您查询录用进度的唯一凭证</div>
        </div>

        <div style="background:#FFF7ED; padding:15px; border-radius:12px; border:1px solid #FFEDD5; margin-bottom:25px;">
            <p style="color:#C2410C; font-size:12px; margin:0; font-weight:800;">
                <i class="fa-solid fa-triangle-exclamation"></i> 重要提示：请妥善保管此编号
            </p>
        </div>

        <button class="btn-close" onclick="document.getElementById('successModal').style.display='none'">我知道了</button>
    </div>
</div>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>
</body>
</html>