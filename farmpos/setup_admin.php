<?php
require_once 'config.php';

// If admin already exists, only allow access if logged in (for re-setup)
$adminExists = false;
try {
    $adminExists = (int)$pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn() > 0;
} catch (Exception $e) {}

// If admin exists and user is not logged in, redirect to login
if ($adminExists && !isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$done = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['full_name'] ?? '');
    $user    = trim($_POST['username'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $pass2   = $_POST['confirm_password'] ?? '';
    $bizName = trim($_POST['business_name'] ?? 'Baffa Precision Agri-Tech');

    if (!$name || !$user || strlen($pass) < 6) {
        $error = 'All fields required. Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->exec("DELETE FROM admin");
        $pdo->prepare("INSERT INTO admin(full_name,username,password,business_name)VALUES(?,?,?,?)")
            ->execute([$name, $user, $hash, $bizName]);
        logAction($pdo, 'SETUP', 'Admin account created/updated for user: ' . $user);
        $done = 'Account configured successfully! <a href="login.php" style="color:#F59E0B;text-decoration:underline">Login now →</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — Baffa Precision Agri-Tech</title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="apple-touch-icon" href="assets/logo.png">
<link rel="stylesheet" href="assets/style.css"> { width: 100px; height: 100px; object-fit: contain; margin: 0 auto 8px; display: block; }
  .setup-title { font-size: 22px; font-weight: 800; color: #fff; text-align: center; line-height: 1.2; }
  .setup-sub   { font-size: 13px; color: rgba(255,255,255,.5); text-align: center; margin-bottom: 28px; }
  .pw-strength { height: 4px; border-radius: 4px; margin-top: 4px; transition: all .3s; }
</style>
</head>
<body>
<div class="login-page">
  <div class="login-box" style="max-width:440px">
    <img src="assets/logo.png" alt="Baffa Logo" class="setup-logo">
    <div class="setup-title">Baffa Precision Agri-Tech</div>
    <div class="setup-sub"><?= $adminExists ? '🔧 Reconfigure Account' : '🚀 First-Time Setup — Create Your Account' ?></div>

    <?php if ($done): ?>
      <div class="flash flash-success" style="margin-bottom:20px">✅ <?= $done ?></div>
    <?php elseif ($error): ?>
      <div class="flash flash-error" style="margin-bottom:20px">❌ <?= clean($error) ?></div>
    <?php endif; ?>

    <?php if (!$done): ?>
    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Business Name</label>
        <input type="text" name="business_name" class="form-control"
               value="<?= clean($_POST['business_name'] ?? 'Baffa Precision Agri-Tech') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Your Full Name</label>
        <input type="text" name="full_name" class="form-control"
               value="<?= clean($_POST['full_name'] ?? '') ?>" required autocomplete="name">
      </div>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               value="<?= clean($_POST['username'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Password <small style="color:rgba(255,255,255,.4)">(min 6 characters)</small></label>
        <div class="pw-wrap">
          <input type="password" name="password" class="form-control" id="pwField"
                 required minlength="6" autocomplete="new-password" oninput="checkStrength(this.value)">
          <button type="button" class="pw-toggle" onclick="togglePw('pwField')">👁</button>
        </div>
        <div class="pw-strength" id="pwBar"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" class="form-control" id="pwField2"
                 required minlength="6" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('pwField2')">👁</button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100" style="justify-content:center;margin-top:12px">
        <?= $adminExists ? '🔄 Update Account' : '🚀 Create Account & Get Started' ?>
      </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:20px;font-size:11px;color:rgba(255,255,255,.2)">
      Baffa Precision Agri-Tech POS &nbsp;·&nbsp; v<?= VERSION ?>
    </p>
  </div>
</div>
<script>
function togglePw(id) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
}
function checkStrength(v) {
  const bar = document.getElementById('pwBar');
  let score = 0;
  if (v.length >= 6) score++;
  if (v.length >= 10) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const colors = ['#EF4444','#F97316','#F59E0B','#10B981','#059669'];
  bar.style.width = (score * 20) + '%';
  bar.style.background = colors[score - 1] || '#444';
}
</script>
</body>
</html>
