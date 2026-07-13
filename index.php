<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
Auth::requireLogin();
$user = Auth::user();

$roleLabels = [
    'admin'      => 'ผู้ดูแลระบบ',
    'director'   => 'ผู้อำนวยการ',
    'curriculum' => 'งานหลักสูตร',
    'teacher'    => 'ครูผู้สอน',
    'accounting' => 'งานบัญชี',
];
$roleLabel  = $roleLabels[$user['role']] ?? $user['role'];
$initial    = mb_substr($user['full_name'], 0, 1);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div id="app" data-theme="light">

  <!-- ══ SIDEBAR ══ -->
  <aside id="sidebar">
    <div class="sb-brand">
      <div class="sb-logo">🎓</div>
      <div>
        <div class="sb-title">ระบบเบิกค่าตอบแทน</div>
        <div class="sb-sub">การสอนเกินภาระงาน</div>
      </div>
    </div>
    <nav class="sb-nav" id="nav-menu"></nav>
  </aside>

  <!-- ══ MAIN ══ -->
  <div id="main">
    <!-- Header -->
    <header id="topbar">
      <div id="toggle-sidebar" style="cursor:pointer;width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--muted)">☰</div>
      <div class="flex-1" style="min-width:0">
        <div class="topbar-title" id="page-title">ภาพรวมระบบ</div>
        <div class="topbar-sub">วิทยาลัยเทคนิคสัตหีบ • ภาคเรียนที่ 1/2568</div>
      </div>
      <div class="d-flex align-center gap-10">
        <span class="role-badge"><?= htmlspecialchars($roleLabel) ?></span>
        <!-- Theme switcher -->
        <div class="theme-switcher topbar-theme" id="theme-switcher">
          <div class="theme-btn active" data-theme="light" title="สว่าง">☀️</div>
          <div class="theme-btn" data-theme="dark"  title="มืด">🌙</div>
          <div class="theme-btn" data-theme="system" title="ตามระบบ">💻</div>
        </div>
        <!-- Notification -->
        <div style="position:relative" id="notif-root">
          <div class="notif-btn" id="notif-btn">🔔<div class="notif-dot" id="notif-dot" style="display:none"></div></div>
          <div class="dropdown" id="notif-dropdown" style="display:none;right:0;top:42px;width:320px">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
              <div style="font-weight:700;font-size:13px">การแจ้งเตือน</div>
              <div id="mark-all-read" style="font-size:11px;color:#7B1F32;cursor:pointer;font-weight:500">อ่านทั้งหมด</div>
            </div>
            <div id="notif-list" style="max-height:320px;overflow-y:auto"></div>
            <div style="padding:10px 16px;text-align:center;font-size:12px;color:#7B1F32;cursor:pointer;font-weight:600;border-top:1px solid var(--border)">ดูทั้งหมด →</div>
          </div>
        </div>
        <!-- Avatar menu -->
        <div style="position:relative" id="avatar-root">
          <div class="avatar" id="avatar-btn" style="cursor:pointer"><?= htmlspecialchars($initial) ?></div>
          <div class="dropdown" id="avatar-dropdown" style="display:none;right:0;top:46px;width:230px">
            <div style="display:flex;align-items:center;gap:11px;padding:14px 16px;border-bottom:1px solid var(--border)">
              <div class="avatar" style="flex-shrink:0"><?= htmlspecialchars($initial) ?></div>
              <div style="min-width:0">
                <div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($user['full_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= htmlspecialchars($roleLabel) ?></div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" id="avatar-logout" style="display:flex;align-items:center;gap:9px;padding:12px 16px;color:#EF4444;font-size:13px;font-weight:600;text-decoration:none">
              <span style="font-size:15px">🚪</span> ออกจากระบบ
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Content -->
    <div id="content">
      <div id="page-content" style="animation:fadeUp .22s ease both"></div>
    </div>
  </div>

</div>

<!-- ══ TOAST CONTAINER ══ -->
<div id="toast-container"></div>

<!-- ══ MODAL CONTAINER ══ -->
<div id="modal-container"></div>

<!-- ══ CONFIG ══ -->
<script>
const APP_CONFIG = {
  baseUrl: '<?= BASE_URL ?>',
  role: '<?= $user['role'] ?>',
  userId: <?= $user['id'] ?>,
  userName: <?= json_encode($user['full_name']) ?>,
  deptId: <?= $user['dept_id'] ?? 'null' ?>,
};
</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
