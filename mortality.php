<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Mortality Records');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='log'){
        $fid=intval($_POST['flock_id']);$date=$_POST['record_date']??date('Y-m-d');$count=intval($_POST['count']??0);$cause=trim($_POST['cause']??'');$notes=trim($_POST['notes']??'');
        $pdo->prepare("INSERT INTO mortality_records(flock_id,record_date,count,cause,notes)VALUES(?,?,?,?,?)")->execute([$fid,$date,$count,$cause,$notes]);
        $pdo->prepare("UPDATE flocks SET current_count=GREATEST(current_count-?,0) WHERE id=?")->execute([$count,$fid]);
        logAction($pdo,'MORTALITY',"Flock:$fid Count:$count Cause:$cause");
        redirect('mortality.php','Mortality recorded. Flock count updated.');
    }
    if($act==='delete'){$pdo->prepare("DELETE FROM mortality_records WHERE id=?")->execute([intval($_POST['id'])]);redirect('mortality.php','Deleted.');}
}
$flocks=$pdo->query("SELECT * FROM flocks WHERE status='active' ORDER BY name")->fetchAll();
$logs=$pdo->query("SELECT mr.*,f.name flock_name FROM mortality_records mr JOIN flocks f ON mr.flock_id=f.id ORDER BY mr.record_date DESC LIMIT 60")->fetchAll();
$totalDeaths=(int)$pdo->query("SELECT COALESCE(SUM(count),0) FROM mortality_records")->fetchColumn();
include 'includes/header.php';
?>
<div class="page-header">
  <div><h2>Mortality Records</h2><p>All-time losses: <strong style="color:var(--danger)"><?=number_format($totalDeaths)?></strong> birds</p></div>
  <button onclick="openModal('mortModal')" class="btn btn-danger">+ Log Mortality</button>
</div>
<div class="card"><div class="table-wrap">
  <table><thead><tr><th>Date</th><th>Flock</th><th>Count</th><th>Cause</th><th>Notes</th><th></th></tr></thead>
  <tbody>
    <?php foreach($logs as $l):?>
    <tr><td class="fw-600"><?=date('d M Y',strtotime($l['record_date']))?></td><td><?=clean($l['flock_name']??'')?></td>
    <td class="fw-700 text-danger"><?=number_format($l['count'])?></td>
    <td><?=clean($l['cause']??'Unknown')?></td>
    <td style="font-size:12px;color:var(--text-secondary)"><?=clean($l['notes']??'')?></td>
    <td><form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete record?">🗑</button></form></td>
    </tr>
    <?php endforeach;?><?php if(!$logs):?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon">✅</div><h3>No mortality recorded</h3><p>Great news — all birds accounted for!</p></div></td></tr><?php endif;?>
  </tbody></table>
</div></div>

<div class="modal-overlay" id="mortModal"><div class="modal">
  <div class="modal-header"><h3>Log Bird Mortality</h3><button class="btn-close" onclick="closeModal('mortModal')">✕</button></div>
  <div class="modal-body"><form method="POST" action="mortality.php">
    <input type="hidden" name="action" value="log">
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Flock *</label><select name="flock_id" class="form-control" required><option value="">-- Select --</option><?php foreach($flocks as $f):?><option value="<?=$f['id']?>"><?=clean($f['name']??'')?> (<?=number_format($f['current_count'])?> birds)</option><?php endforeach;?></select></div>
      <div class="form-group"><label class="form-label">Date</label><input type="date" name="record_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Number of Deaths *</label><input type="number" name="count" class="form-control" value="1" min="1" required></div>
      <div class="form-group"><label class="form-label">Cause</label><input type="text" name="cause" class="form-control" placeholder="Disease, Predator, Unknown..." list="causes"><datalist id="causes"><option value="Disease"><option value="Predator Attack"><option value="Unknown"><option value="Injury"><option value="Heat Stress"><option value="Cold Stress"></datalist></div>
    </div>
    <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('mortModal')">Cancel</button><button type="submit" class="btn btn-danger">Record Mortality</button></div>
  </form></div>
</div></div>
<?php include 'includes/footer.php';?>
