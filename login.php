<?php
require_once 'config.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user && $pass) {
        $st = $pdo->prepare("SELECT * FROM admin LIMIT 1");
        $st->execute(); $admin = $st->fetch();
        if ($admin && password_verify($pass, $admin['password']) && $admin['username'] === $user) {
            session_regenerate_id(true);
            $_SESSION['pos_id']   = $admin['id'];
            $_SESSION['pos_name'] = $admin['full_name'];
            $_SESSION['pos_last_active'] = time();
            $pdo->prepare("UPDATE admin SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);
            logAction($pdo, 'LOGIN', 'User: ' . $user);
            header('Location: dashboard.php'); exit;
        } else {
            $error = 'Incorrect username or password.';
        }
    } else {
        $error = 'Please enter your username and password.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Baffa Precision Agri-Tech</title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="apple-touch-icon" href="assets/logo.png">
<link rel="stylesheet" href="assets/style.css">
  <div class="login-box">
    <img src="assets/logo.png" alt="Baffa Logo" style="width:90px;height:90px;object-fit:contain;display:block;margin:0 auto 8px">
    <div class="login-title">Baffa Precision Agri-Tech</div>
    <div class="login-sub">Poultry · Eggs · Aquaculture POS</div>

    <?php if ($error): ?><div class="flash flash-error" style="margin-bottom:20px">❌ <?= clean($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?><div class="flash flash-warning" style="margin-bottom:20px">⏰ Session expired. Please login again.</div><?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="Enter username"
               value="<?= clean($_POST['username'] ?? '') ?>" autofocus required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" class="form-control" id="pwField" placeholder="Enter password" required>
          <button type="button" class="pw-toggle" onclick="togglePw()">👁</button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100" style="margin-top:8px;border-radius:14px;justify-content:center">
        Sign In →
      </button>
    </form>
    <p style="text-align:center;margin-top:20px;font-size:12px;color:rgba(255,255,255,.25)">Baffa Agri-Tech POS v<?= VERSION ?> &nbsp;·&nbsp; Single-operator system</p>
  </div>
</div>
<script>
function togglePw() {
  const f = document.getElementById('pwField');
  f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
</body></html>
