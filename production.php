<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Egg Production Log');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='log'){
        $fid=intval($_POST['flock_id']);$date=$_POST['log_date']??date('Y-m-d');
        $gA=intval($_POST['eggs_grade_a']??0);$gB=intval($_POST['eggs_grade_b']??0);$cracked=intval($_POST['eggs_cracked']??0);$dirty=intval($_POST['eggs_dirty']??0);
        $total=$gA+$gB+$cracked+$dirty;$sellable=$gA+$gB;$notes=trim($_POST['notes']??'');
        try{
            $pdo->prepare("INSERT INTO production_logs(flock_id,log_date,eggs_grade_a,eggs_grade_b,eggs_cracked,eggs_dirty,total_collected,sellable,notes)VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE eggs_grade_a=?,eggs_grade_b=?,eggs_cracked=?,eggs_dirty=?,total_collected=?,sellable=?,notes=?")
                ->execute([$fid,$date,$gA,$gB,$cracked,$dirty,$total,$sellable,$notes,$gA,$gB,$cracked,$dirty,$total,$sellable,$notes]);
            // Update egg product stock if linked
            logAction($pdo,'EGG_LOG',"Flock:$fid Date:$date Total:$total Sellable:$sellable");
            redirect('production.php','Production logged: '.$total.' eggs ('.$sellable.' sellable)');
        }catch(Exception $e){redirect('production.php','Error: '.$e->getMessage(),'error');}
    }
    if($act==='delete'){
        $pdo->prepare("DELETE FROM production_logs WHERE id=?")->execute([intval($_POST['id'])]);
        redirect('production.php','Log deleted.');
    }
}

$selFlock=intval($_GET['flock_id']??0);
$from=($_GET['from']??date('Y-m-01'));$to=($_GET['to']??date('Y-m-d'));
$flocks=$pdo->query("SELECT * FROM flocks WHERE status='active' ORDER BY name")->fetchAll();

$sql="SELECT pl.*,f.name flock_name,f.current_count FROM production_logs pl JOIN flocks f ON pl.flock_id=f.id WHERE pl.log_date BETWEEN '$from' AND '$to'";
if($selFlock) $sql.=" AND pl.flock_id=$selFlock";
$sql.=" ORDER BY pl.log_date DESC,f.name";
$logs=$pdo->query($sql)->fetchAll();

$totals=['total'=>array_sum(array_column($logs,'total_collected')),'sellable'=>array_sum(array_column($logs,'sellable')),'cracked'=>array_sum(array_column($logs,'eggs_cracked')),'grade_a'=>array_sum(array_column($logs,'eggs_grade_a'))];

include 'includes/header.php';
?>
<div class="page-header"><div><h2>🥚 Egg Production Log</h2><p>Daily collection records</p></div><button onclick="openModal('logModal')" class="btn btn-primary">+ Log Today's Eggs</button></div>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi-card"><div class="kpi-icon">🥚</div><div class="kpi-value"><?=number_format($totals['total'])?></div><div class="kpi-label">Total Collected</div></div>
  <div class="kpi-card"><div class="kpi-icon">✅</div><div class="kpi-value" style="color:var(--accent)"><?=number_format($totals['sellable'])?></div><div class="kpi-label">Sellable Eggs</div></div>
  <div class="kpi-card"><div class="kpi-icon">⭐</div><div class="kpi-value"><?=number_format($totals['grade_a'])?></div><div class="kpi-label">Grade A</div></div>
  <div class="kpi-card"><div class="kpi-icon">💔</div><div class="kpi-value" style="color:var(--danger)"><?=number_format($totals['cracked'])?></div><div class="kpi-label">Cracked/Waste</div></div>
</div>

<!-- Filter -->
<div class="card mb-2"><div class="card-body" style="padding:12px 18px"><form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
  <div><label class="form-label">Flock</label><select name="flock_id" class="form-control" style="width:160px" onchange="this.form.submit()"><option value="">All Flocks</option><?php foreach($flocks as $f):?><option value="<?=$f['id']?>" <?=$selFlock==$f['id']?'selected':''?>><?=clean($f['name']??'')?></option><?php endforeach;?></select></div>
  <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=$from?>" style="width:140px"></div>
  <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=$to?>" style="width:140px"></div>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <button onclick="exportCSV('prodTable','egg_production.csv')" type="button" class="btn btn-secondary btn-sm">📥 CSV</button>
</form></div></div>

<div class="card"><div class="table-wrap">
  <table id="prodTable"><thead><tr><th>Date</th><th>Flock</th><th>Birds</th><th data-sort="3">Total</th><th>Grade A</th><th>Grade B</th><th>Cracked</th><th>Dirty</th><th>Sellable</th><th>Lay Rate</th><th>Notes</th><th>Del</th></tr></thead>
  <tbody>
    <?php foreach($logs as $l):$lr=$l['current_count']>0?round($l['total_collected']/$l['current_count']*100,1):0;?>
    <tr>
      <td class="fw-600"><?=date('d M Y',strtotime($l['log_date']))?></td>
      <td><?=clean($l['flock_name']??'')?></td>
      <td class="text-muted"><?=number_format($l['current_count'])?></td>
      <td class="fw-700"><?=number_format($l['total_collected'])?></td>
      <td class="grade-A"><?=number_format($l['eggs_grade_a'])?></td>
      <td class="text-muted"><?=number_format($l['eggs_grade_b'])?></td>
      <td class="text-danger"><?=number_format($l['eggs_cracked'])?></td>
      <td class="text-muted"><?=number_format($l['eggs_dirty'])?></td>
      <td class="fw-600 text-success"><?=number_format($l['sellable'])?></td>
      <td><span class="badge badge-<?=$lr>=70?'success':($lr>=50?'warning':'danger')?>"><?=$lr?>%</span></td>
      <td style="font-size:12px;color:var(--text-secondary)"><?=clean($l['notes']??'')?></td>
      <td><form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete log?">🗑</button></form></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$logs):?><tr><td colspan="12"><div class="empty-state"><div class="empty-icon">🥚</div><h3>No production logs</h3><p>Start logging daily egg collection</p></div></td></tr><?php endif;?>
  </tbody></table>
</div></div>

<div class="modal-overlay" id="logModal"><div class="modal">
  <div class="modal-header"><h3>Log Egg Collection</h3><button class="btn-close" onclick="closeModal('logModal')">✕</button></div>
  <div class="modal-body"><form method="POST" action="production.php">
    <input type="hidden" name="action" value="log">
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Flock *</label><select name="flock_id" class="form-control" required><option value="">-- Select --</option><?php foreach($flocks as $f):?><option value="<?=$f['id']?>" <?=$selFlock==$f['id']?'selected':''?>><?=clean($f['name']??'')?></option><?php endforeach;?></select></div>
      <div class="form-group"><label class="form-label">Date</label><input type="date" name="log_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
    </div>
    <div class="egg-grade-grid">
      <div class="egg-grade-item"><div class="egg-grade-label">Grade A ⭐</div><input type="number" name="eggs_grade_a" class="form-control" value="0" min="0" style="margin-top:6px;text-align:center;font-size:18px;font-weight:700"></div>
      <div class="egg-grade-item"><div class="egg-grade-label">Grade B</div><input type="number" name="eggs_grade_b" class="form-control" value="0" min="0" style="margin-top:6px;text-align:center;font-size:18px;font-weight:700"></div>
      <div class="egg-grade-item"><div class="egg-grade-label">Cracked 💔</div><input type="number" name="eggs_cracked" class="form-control" value="0" min="0" style="margin-top:6px;text-align:center;font-size:18px;font-weight:700"></div>
      <div class="egg-grade-item"><div class="egg-grade-label">Dirty 🟤</div><input type="number" name="eggs_dirty" class="form-control" value="0" min="0" style="margin-top:6px;text-align:center;font-size:18px;font-weight:700"></div>
    </div>
    <div class="form-group" style="margin-top:14px"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional observations..."></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('logModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Log</button></div>
  </form></div>
</div></div>
<?php include 'includes/footer.php';?>
