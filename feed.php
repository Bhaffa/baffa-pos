<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Feed & Inputs');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';

if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='log'){
    $fid=intval($_POST['flock_id']);$date=$_POST['issue_date']??date('Y-m-d');$type=trim($_POST['feed_type']??'');$qty=floatval($_POST['qty_kg']??0);$cost=floatval($_POST['unit_cost']??0);$total=$qty*$cost;$notes=trim($_POST['notes']??'');
    if(!$fid||!$type||$qty<=0){redirect('feed.php','All fields required.','error');}
    $pdo->prepare("INSERT INTO feed_issuance(flock_id,issue_date,feed_type,qty_kg,unit_cost,total_cost,notes)VALUES(?,?,?,?,?,?,?)")->execute([$fid,$date,$type,$qty,$cost,$total,$notes]);
    logAction($pdo,'FEED_LOG',"Flock:$fid Type:$type Qty:{$qty}kg Cost:$total");
    redirect('feed.php','Feed log saved!');
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='delete'){$pdo->prepare("DELETE FROM feed_issuance WHERE id=?")->execute([intval($_POST['id'])]);redirect('feed.php','Deleted.');}

$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');
$flocks=$pdo->query("SELECT * FROM flocks WHERE status='active' ORDER BY name")->fetchAll();
$logStmt=$pdo->prepare("SELECT fi.*,f.name flock_name FROM feed_issuance fi JOIN flocks f ON fi.flock_id=f.id WHERE fi.issue_date BETWEEN ? AND ? ORDER BY fi.issue_date DESC");$logStmt->execute([$from,$to]);$logs=$logStmt->fetchAll();
$totalCost=array_sum(array_column($logs,'total_cost'));$totalKg=array_sum(array_column($logs,'qty_kg'));

include 'includes/header.php';
?>
<div class="page-header"><div><h2>🌾 Feed & Inputs</h2></div><button onclick="openModal('feedModal')" class="btn btn-primary">+ Log Feed</button></div>
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi-card"><div class="kpi-icon">🌾</div><div class="kpi-value"><?=number_format($totalKg,1)?> kg</div><div class="kpi-label">Feed Used (period)</div></div>
  <div class="kpi-card"><div class="kpi-icon">💸</div><div class="kpi-value"><?=$sym?><?=number_format($totalCost,0)?></div><div class="kpi-label">Feed Cost (period)</div></div>
  <div class="kpi-card"><div class="kpi-icon">📊</div><div class="kpi-value"><?=$totalKg>0?$sym.number_format($totalCost/$totalKg,2):$sym.'0.00'?></div><div class="kpi-label">Avg Cost per kg</div></div>
</div>

<div class="card mb-2"><div class="card-body" style="padding:12px 18px"><form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
  <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=$from?>" style="width:140px"></div>
  <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=$to?>" style="width:140px"></div>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
</form></div></div>

<div class="card"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Flock</th><th>Feed Type</th><th>Qty (kg)</th><th>Unit Cost</th><th>Total Cost</th><th>Notes</th><th></th></tr></thead>
<tbody>
<?php foreach($logs as $l):?>
<tr><td><?=date('d M Y',strtotime($l['issue_date']))?></td><td class="fw-600"><?=clean($l['flock_name']??'')?></td><td><?=clean($l['feed_type']??'')?></td>
<td><?=number_format($l['qty_kg'],2)?></td><td><?=$sym?><?=number_format($l['unit_cost'],2)?></td><td class="fw-700"><?=$sym?><?=number_format($l['total_cost'],2)?></td>
<td style="font-size:12px;color:var(--text-secondary)"><?=clean($l['notes']??'')?></td>
<td><form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete?">🗑</button></form></td></tr>
<?php endforeach;?><?php if(!$logs):?><tr><td colspan="8"><div class="empty-state"><div class="empty-icon">🌾</div><h3>No feed logs yet</h3></div></td></tr><?php endif;?>
</tbody></table></div></div>

<div class="modal-overlay" id="feedModal"><div class="modal"><div class="modal-header"><h3>Log Feed Issuance</h3><button class="btn-close" onclick="closeModal('feedModal')">✕</button></div>
<div class="modal-body"><form method="POST" action="feed.php">
  <input type="hidden" name="action" value="log">
  <div class="form-row-2">
    <div class="form-group"><label class="form-label">Flock *</label><select name="flock_id" class="form-control" required><option value="">-- Select --</option><?php foreach($flocks as $f):?><option value="<?=$f['id']?>"><?=clean($f['name']??'')?></option><?php endforeach;?></select></div>
    <div class="form-group"><label class="form-label">Date</label><input type="date" name="issue_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
  </div>
  <div class="form-group"><label class="form-label">Feed Type *</label><input type="text" name="feed_type" class="form-control" placeholder="e.g. Layers Mash, Concentrate" list="feedTypes" required><datalist id="feedTypes"><option value="Layers Mash"><option value="Layers Pellets"><option value="Chick Mash"><option value="Concentrate"><option value="Maize"><option value="Soybean Meal"></datalist></div>
  <div class="form-row-2">
    <div class="form-group"><label class="form-label">Quantity (kg) *</label><input type="number" name="qty_kg" class="form-control" step="0.01" min="0.01" required></div>
    <div class="form-group"><label class="form-label">Unit Cost per kg</label><input type="number" name="unit_cost" class="form-control" step="0.01" min="0" value="0"></div>
  </div>
  <div class="form-group"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional"></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('feedModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
<?php include 'includes/footer.php';?>
