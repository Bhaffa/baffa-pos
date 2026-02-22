<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Audit Logs');
$logs=$pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 500")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>📋 Audit Logs</h2><p>System activity trail</p></div></div>
<div class="card">
<div class="table-wrap">
<table>
<thead><tr><th>Time</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
<tbody>
<?php foreach($logs as $l):
  $a = strtoupper($l['action']??'');
  if (strpos($a,'DELETE')!==false || strpos($a,'VOID')!==false) $cls='danger';
  elseif (strpos($a,'LOGIN')!==false) $cls='info';
  elseif (strpos($a,'SALE')!==false) $cls='success';
  elseif (strpos($a,'EXPENSE')!==false || strpos($a,'PURCHASE')!==false) $cls='warning';
  else $cls='secondary';
?>
<tr>
  <td style="font-size:12px;white-space:nowrap"><?=date('d M H:i',strtotime($l['created_at']))?></td>
  <td><span class="badge badge-<?=$cls?>"><?=clean($l['action']??'')?></span></td>
  <td style="font-size:13px"><?=clean($l['description']??'')?></td>
  <td style="font-size:12px;color:var(--text-secondary)"><?=clean($l['ip_address']??'')?></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
<?php include 'includes/footer.php';?>
