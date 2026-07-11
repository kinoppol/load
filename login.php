<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

Auth::start();
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::login(
        trim($_POST['username'] ?? ''),
        $_POST['password'] ?? ''
    );
    if ($result['success']) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#3A0E1C 0%,#4A1425 50%,#6B1E35 100%)}
.pattern{position:fixed;inset:0;opacity:.04;background-image:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:20px 20px;pointer-events:none}
.card{position:relative;width:100%;max-width:420px;padding:20px}
.logo-wrap{text-align:center;margin-bottom:32px}
.logo-box{width:72px;height:72px;border-radius:18px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 16px}
.title{font-size:22px;font-weight:700;color:#fff;margin-bottom:6px}
.subtitle{font-size:13px;color:rgba(255,255,255,.6)}
.form-card{background:rgba(255,255,255,.08);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:28px}
label{font-size:12px;font-weight:600;color:rgba(255,255,255,.7);display:block;margin-bottom:7px}
.form-group{margin-bottom:18px}
input,select{width:100%;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2);color:#fff;padding:11px 14px;border-radius:9px;font-size:14px;font-family:inherit}
input::placeholder{color:rgba(255,255,255,.35)}
select option{background:#3A0E1C;color:#fff}
.hint{font-size:11px;color:rgba(255,255,255,.4);margin-top:5px}
.error{background:#EF444420;border:1px solid #EF444440;border-radius:7px;padding:9px 12px;margin-bottom:14px;font-size:12px;color:#EF4444}
button[type=submit]{width:100%;background:#fff;color:#4A1425;border:none;padding:13px;border-radius:9px;cursor:pointer;font-size:15px;font-weight:700;transition:opacity .2s;font-family:inherit}
button[type=submit]:hover{opacity:.9}
.footer{text-align:center;margin-top:16px;font-size:11px;color:rgba(255,255,255,.35)}
</style>
</head>
<body>
<div class="pattern"></div>
<div class="card">
  <div class="logo-wrap">
    <div class="logo-box">🎓</div>
    <div class="title">ระบบเบิกค่าตอบแทนการสอน</div>
    <div class="subtitle">วิทยาลัยเทคนิคสัตหีบ • สำนักงานคณะกรรมการการอาชีวศึกษา</div>
  </div>
  <div class="form-card">
    <form method="POST" action="">
      <div class="form-group">
        <label>ชื่อผู้ใช้งาน</label>
        <input type="text" name="username" placeholder="กรอกชื่อผู้ใช้งาน"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group" style="margin-bottom:22px">
        <label>รหัสผ่าน</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        <div class="hint">Demo: ใช้รหัสผ่าน "password" ทุก account</div>
      </div>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <button type="submit">เข้าสู่ระบบ</button>
    </form>
  </div>
  <div class="footer">ระบบต้นแบบ v<?= APP_VERSION ?> • <?= date('Y') + 543 ?></div>
</div>
</body>
</html>
