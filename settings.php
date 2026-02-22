<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Settings');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='update_profile'){
        $bizName=trim($_POST['business_name']??'');$bizAddr=trim($_POST['business_address']??'');$bizPhone=trim($_POST['business_phone']??'');$currency=trim($_POST['currency']??'NGN');$sym=trim($_POST['currency_symbol']??'₦');$tax=floatval($_POST['tax_rate']??0);$footer=trim($_POST['receipt_footer']??'');
        $pdo->prepare("UPDATE admin SET business_name=?,business_address=?,business_phone=?,currency=?,currency_symbol=?,tax_rate=?,receipt_footer=? WHERE id=?")->execute([$bizName,$bizAddr,$bizPhone,$currency,$sym,$tax,$footer,$_SESSION['pos_id']]);
        logAction($pdo,'SETTINGS','Business profile updated');
        redirect('settings.php','Settings saved!');
    }
    if($act==='change_password'){
        $cur=$_POST['current_password']??'';$new=$_POST['new_password']??'';$conf=$_POST['confirm_password']??'';
        $admin=$pdo->query("SELECT * FROM admin LIMIT 1")->fetch();
        if(!password_verify($cur,$admin['password'])){redirect('settings.php','Current password incorrect.','error');}
        if(strlen($new)<6){redirect('settings.php','New password must be at least 6 characters.','error');}
        if($new!==$conf){redirect('settings.php','Passwords do not match.','error');}
        $pdo->prepare("UPDATE admin SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$_SESSION['pos_id']]);
        logAction($pdo,'CHANGE_PASSWORD','Password changed');
        redirect('settings.php','Password changed successfully!');
    }
}
$settings=getSettings($pdo);
$admin=$pdo->query("SELECT * FROM admin LIMIT 1")->fetch();
$dbStats=[
    'sales'=>$pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn(),
    'products'=>$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'customers'=>$pdo->query("SELECT COUNT(*) FROM customers WHERE id>1")->fetchColumn(),
    'flocks'=>$pdo->query("SELECT COUNT(*) FROM flocks")->fetchColumn(),
];
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Settings</h2><p>Business configuration and account</p></div></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div>
<div class="card mb-2">
  <div class="card-header"><h3>🏪 Business Profile</h3></div>
  <div class="card-body"><form method="POST" action="settings.php">
    <input type="hidden" name="action" value="update_profile">
    <div class="form-group"><label class="form-label">Business Name</label><input type="text" name="business_name" class="form-control" value="<?=clean($settings['business_name']??'')?>"></div>
    <div class="form-group"><label class="form-label">Business Address</label><textarea name="business_address" class="form-control" rows="2"><?=clean($settings['business_address']??'')?></textarea></div>
    <div class="form-group"><label class="form-label">Phone</label><input type="text" name="business_phone" class="form-control" value="<?=clean($settings['business_phone']??'')?>"></div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Currency Code</label><input type="text" name="currency" class="form-control" value="<?=clean($settings['currency']??'NGN')?>" placeholder="NGN"></div>
      <div class="form-group"><label class="form-label">Currency Symbol</label><input type="text" name="currency_symbol" class="form-control" value="<?=clean($settings['currency_symbol']??'₦')?>" placeholder="₦"></div>
    </div>
    <div class="form-group"><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" class="form-control" value="<?=floatval($settings['tax_rate']??0)?>" step="0.01" min="0" max="100"><small class="text-muted">Set 0 to disable tax</small></div>
    <div class="form-group"><label class="form-label">Receipt Footer Message</label><input type="text" name="receipt_footer" class="form-control" value="<?=clean($settings['receipt_footer']??'')?>" placeholder="e.g. Thank you for your business!"></div>
    <button type="submit" class="btn btn-primary w-100" style="justify-content:center">Save Settings</button>
  </form></div>
</div>
<div class="card">
  <div class="card-header"><h3>🔒 Change Password</h3></div>
  <div class="card-body"><form method="POST" action="settings.php">
    <input type="hidden" name="action" value="change_password">
    <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
    <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
    <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
    <button type="submit" class="btn btn-danger w-100" style="justify-content:center">Change Password</button>
  </form></div>
</div>
</div>
<div>
<div class="card mb-2">
  <div class="card-header"><h3>📊 System Information</h3></div>
  <div class="card-body">
    <table style="width:100%;font-size:13.5px"><tbody>
      <?php foreach($dbStats as $k=>$v):?>
      <tr><td style="padding:7px 0;color:var(--text-secondary);text-transform:capitalize"><?=$k?></td><td style="font-weight:700"><?=number_format($v)?> records</td></tr>
      <?php endforeach;?>
      <tr><td style="padding:7px 0;color:var(--text-secondary)">PHP Version</td><td class="fw-700"><?=phpversion()?></td></tr>
      <tr><td style="padding:7px 0;color:var(--text-secondary)">Last Login</td><td><?=$admin['last_login']?date('d M Y H:i',strtotime($admin['last_login'])):'—'?></td></tr>
      <tr><td style="padding:7px 0;color:var(--text-secondary)">Baffa Agri-Tech Version</td><td class="fw-700"><?=VERSION?></td></tr>
    </tbody></table>
  </div>
</div>
<div class="card mb-2">
  <div class="card-header"><h3>🔗 Quick Links</h3></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
    <a href="backup.php" class="btn btn-secondary btn-sm">💾 Backup & Export Data</a>
    <a href="logs.php" class="btn btn-secondary btn-sm">🔍 View Audit Logs</a>
    <a href="inventory.php" class="btn btn-secondary btn-sm">🗃️ Manage Stock</a>
    <a href="reports.php" class="btn btn-secondary btn-sm">📊 Reports</a>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3>⚠️ Session</h3></div>
  <div class="card-body">
    <p class="text-muted" style="font-size:13px;margin-bottom:12px">Sessions expire after 2 hours of inactivity. You are currently logged in as <strong><?=clean($_SESSION['pos_name']??'Admin')?></strong>.</p>
    <a href="logout.php" class="btn btn-danger btn-sm" data-confirm="Log out?">⏏ Logout</a>
  </div>
</div>
</div>
</div>
<?php include 'includes/footer.php';?>
