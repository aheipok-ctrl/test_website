<?php
/**
 * 代世集团 VMS - 数据概览 (手机端全适配增强版)
 * 变动：保持原有功能逻辑，仅优化 CSS 响应式布局
 */
if (!can('admin')) die("权限不足");

// --- 1. 数据预警查询 ---
$probation_alerts = [];
try {
    $stmt_exp = $pdo->query("SELECT id, name, department, probation_end, 'probation' as type FROM employees WHERE status = 'probation' AND probation_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY probation_end ASC");
    $probation_alerts = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$resume_alerts = [];
try {
    $stmt_res = $pdo->query("SELECT id, candidate_name as name, '简历中心' as department, created_at, 'resume' as type FROM candidate_resumes WHERE status = '待筛选' ORDER BY created_at DESC LIMIT 10");
    $resume_alerts = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$total_todo_count = count($probation_alerts) + count($resume_alerts);

// --- 2. 实时统计数据 ---
$stats = ['employees' => 0, 'jobs' => 0, 'resumes' => 0, 'assets' => 0];
try {
    $stats['employees'] = $pdo->query("SELECT COUNT(*) FROM employees WHERE status != 'resigned'")->fetchColumn() ?: 0;
    $stats['jobs']      = $pdo->query("SELECT COUNT(*) FROM job_postings")->fetchColumn() ?: 0;
    $stats['resumes']   = $pdo->query("SELECT COUNT(*) FROM candidate_resumes WHERE status = '待筛选'")->fetchColumn() ?: 0;
    $stats['assets']    = $pdo->query("SELECT COUNT(*) FROM fixed_assets")->fetchColumn() ?: 0;
} catch (Exception $e) {}

?>

<style>
    /* --- 1. 基础布局适配 --- */
    .stats-grid { 
        display: grid; 
        /* 针对手机端优化：最小宽度调小，允许更灵活的并排或堆叠 */
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); 
        gap: clamp(10px, 2vw, 15px); 
        margin-bottom: 25px; 
    }
    
    .stat-card { 
        background: #FFF; 
        padding: clamp(12px, 3vw, 20px); 
        border-radius: 16px; 
        border: 1px solid #E2E8F0; 
        display: flex; 
        align-items: center; 
        gap: clamp(8px, 2vw, 15px); 
        transition: 0.3s; 
        text-decoration: none;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    
    .stat-icon { 
        width: clamp(36px, 8vw, 48px); 
        height: clamp(36px, 8vw, 48px); 
        border-radius: 10px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: clamp(14px, 4vw, 18px); 
        flex-shrink: 0; 
    }
    .icon-blue { background: #EEF2FF; color: #4F46E5; }
    .icon-purple { background: #F5F3FF; color: #8B5CF6; }
    .icon-orange { background: #FFF7ED; color: #F59E0B; }
    
    .stat-info h3 { 
        margin: 0; 
        font-size: 10px; 
        color: #94A3B8; 
        text-transform: uppercase; 
        font-weight: 800; 
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .stat-info .number { 
        margin: 2px 0 0; 
        font-size: clamp(18px, 5vw, 24px); 
        font-weight: 900; 
        color: #1E293B; 
    }

    /* --- 2. 待办面板适配 --- */
    .todo-panel {
        grid-column: 1 / -1; /* 默认全宽 */
        background: #FFF; border-radius: 16px; border: 1px solid #E2E8F0;
        display: flex; flex-direction: column; overflow: hidden;
    }

    .todo-header { 
        padding: 15px 20px; 
        border-bottom: 1px solid #F1F5F9; 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        background: #F8FAFC; 
    }
    .todo-title { font-size: 13px; font-weight: 900; color: #1A3C6C; display: flex; align-items: center; gap: 8px; }
    
    .todo-list { padding: 0; margin: 0; list-style: none; max-height: 450px; overflow-y: auto; }
    
    .todo-item { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        padding: 15px 20px; 
        border-bottom: 1px solid #F8FAFC; 
        transition: 0.2s;
    }
    .todo-item:hover { background: #F0F7FF; }
    .todo-item:last-child { border-bottom: none; }
    
    .todo-main { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
    .todo-type-icon { 
        width: 32px; height: 32px; border-radius: 8px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 14px; flex-shrink: 0;
    }
    .type-probation { background: #FEF2F2; color: #EF4444; }
    .type-resume { background: #F5F3FF; color: #8B5CF6; }

    .todo-content { display: flex; flex-direction: column; min-width: 0; }
    .todo-name { font-size: 14px; font-weight: 800; color: #1E293B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .todo-desc { font-size: 11px; color: #94A3B8; margin-top: 2px; }
    
    .todo-right { 
        display: flex; 
        align-items: center; 
        gap: clamp(8px, 3vw, 15px); 
        margin-left: 10px;
    }
    
    .todo-date { 
        font-size: 11px; 
        color: #94A3B8; 
        white-space: nowrap; 
    }

    .btn-todo-go { 
        font-size: 11px; font-weight: 800; color: #1A3C6C; background: #EEF2FF; 
        padding: 6px 12px; border-radius: 6px; text-decoration: none; transition: 0.2s;
        white-space: nowrap;
    }
    .btn-todo-go:hover { background: #1A3C6C; color: #FFF; }
    
    .todo-empty { padding: 60px 20px; text-align: center; color: #CBD5E1; font-size: 13px; font-weight: bold; }

    /* --- 3. 移动端专用微调 --- */
    @media (max-width: 480px) {
        .todo-item { padding: 12px 15px; }
        .todo-right { flex-direction: column; align-items: flex-end; gap: 5px; }
        .todo-date { font-size: 10px; }
        .btn-todo-go { padding: 4px 10px; }
        .stat-card { flex-direction: column; text-align: center; justify-content: center; }
        .stat-info h3 { font-size: 9px; }
    }
</style>

<div class="stats-grid">
    <a href="?tab=hr" class="stat-card">
        <div class="stat-icon icon-blue"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info"><h3>在职员工</h3><p class="number"><?= (int)$stats['employees'] ?></p></div>
    </a>
    
    <a href="?tab=resumes" class="stat-card">
        <div class="stat-icon icon-purple"><i class="fa-solid fa-file-invoice"></i></div>
        <div class="stat-info"><h3>待选简历</h3><p class="number"><?= (int)$stats['resumes'] ?></p></div>
    </a>

    <a href="?tab=fixed_assets" class="stat-card">
        <div class="stat-icon icon-orange"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div class="stat-info"><h3>资产总数</h3><p class="number"><?= (int)$stats['assets'] ?></p></div>
    </a>

    <div class="todo-panel">
        <div class="todo-header">
            <div class="todo-title">
                <i class="fa-solid fa-bell-concierge" style="color:#EF4444;"></i> 人事待办中心
            </div>
            <?php if($total_todo_count > 0): ?>
                <span style="font-size:10px; background:#EF4444; color:#FFF; padding:2px 8px; border-radius:10px; font-weight:900;">
                    <?= $total_todo_count ?>
                </span>
            <?php endif; ?>
        </div>
        <ul class="todo-list">
            <?php if($total_todo_count === 0): ?>
                <div class="todo-empty">
                    <i class="fa-solid fa-mug-hot" style="font-size:32px; margin-bottom:15px; display:block; color:#ECFDF5;"></i>
                    暂无待办事项
                </div>
            <?php else: ?>
                <?php foreach($probation_alerts as $alert): ?>
                    <li class="todo-item">
                        <div class="todo-main">
                            <div class="todo-type-icon type-probation"><i class="fa-solid fa-user-clock"></i></div>
                            <div class="todo-content">
                                <span class="todo-name"><?= htmlspecialchars($alert['name']) ?></span>
                                <span class="todo-desc"><?= htmlspecialchars($alert['department']) ?> · 转正</span>
                            </div>
                        </div>
                        <div class="todo-right">
                            <span class="todo-date" style="color:#EF4444; font-weight:bold;"><?= date('m-d', strtotime($alert['probation_end'])) ?></span>
                            <a href="?tab=hr" class="btn-todo-go">办理</a>
                        </div>
                    </li>
                <?php endforeach; ?>

                <?php foreach($resume_alerts as $alert): ?>
                    <li class="todo-item">
                        <div class="todo-main">
                            <div class="todo-type-icon type-resume"><i class="fa-solid fa-file-signature"></i></div>
                            <div class="todo-content">
                                <span class="todo-name"><?= htmlspecialchars($alert['name']) ?></span>
                                <span class="todo-desc">新简历投递</span>
                            </div>
                        </div>
                        <div class="todo-right">
                            <span class="todo-date"><?= date('m-d', strtotime($alert['created_at'])) ?></span>
                            <a href="?tab=resumes" class="btn-todo-go" style="background:#F5F3FF; color:#8B5CF6;">筛选</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>