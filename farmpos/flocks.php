<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Flock Management');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if(in_array($act,['add','edit'])){
        $name=trim($_POST['name']??'');$house=trim($_POST['house']??'');$breed=trim($_POST['breed']??'');$source=trim($_POST['source']??'');$start=trim($_POST['start_date']??date('Y-m-d'));$count=intval($_POST['initial_count']??0);$notes=trim($_POST['notes']??'');$id=intval($_POST['id']??0);
        if(!$name){redirect('flocks.php','Name required.','error');}
        if($act==='add') $pdo->prepare("INSERT INTO flocks(name,house,breed,source,start_date,initial_count,current_count,notes)VALUES(?,?,?,?,?,?,?,?)")->execute([$name,$house,$breed,$source,$start,$count,$count,$notes]);
        else $pdo->prepare("UPDATE flocks SET name=?,house=?,breed=?,source=?,start_date=?,notes=? WHERE id=?")->execute([$name,$house,$breed,$source,$start,$notes,$id]);
        logAction($pdo,'FLOCK',($act==='add'?'Added':'Edited').":$name");
        redirect('flocks.php','Flock saved!');
    }
    if($act==='close'){$id=intval($_POST['id']);$pdo->prepare("UPDATE flocks SET status='closed' WHERE id=?")->execute([$id]);redirect('flocks.php','Flock closed.');}
}
$flocks=$pdo->query("SELECT f.*,COALESCE(SUM(pl.total_collected),0) total_eggs,COALESCE(SUM(mr.count),0) total_deaths FROM flocks f LEFT JOIN production_logs pl ON f.id=pl.flock_id LEFT JOIN mortality_records mr ON f.id=mr.flock_id GROUP BY f.id ORDER BY f.status,f.name")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Flock Management</h2><p><?=count(array_filter($flocks,fn($f)=>$f['status']==='active'))?> active flocks</p></div><button onclick="openModal('flockModal')" class="btn btn-primary">+ Add Flock</button></div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
<?php foreach($flocks as $f):
  $ageDays=round((time()-strtotime($f['start_date']))/86400);
  $ageWks=round($ageDays/7);
  $layRate=$f['current_count']>0&&$f['total_eggs']>0?round($f['total_eggs']/$f['current_count']/$ageDays*100,1):0;
?>
<div class="flock-card <?=$f['status']==='active'?'active':''?>">
  <div class="flock-status"><span class="badge badge-<?=$f['status']==='active'?'success':($f['status']==='closed'?'danger':'secondary')?>"><?=ucfirst($f['status'])?></span></div>
  <div style="font-size:24px;margin-bottom:8px">🐔</div>
  <div style="font-size:17px;font-weight:700"><?=clean($f['name']??'')?></div>
  <?php if($f['house']):?><div class="text-muted" style="font-size:12px">🏠 <?=clean($f['house']??'')?></div><?php endif;?>
  <div class="text-muted" style="font-size:12px">🧬 <?=clean($f['breed']?:'Unknown breed')?></div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px">
    <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:18px;font-weight:800"><?=number_format($f['current_count'])?></div>
      <div style="font-size:10px;color:var(--text-secondary)">Birds</div>
    </div>
    <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:18px;font-weight:800"><?=number_format($f['total_eggs'])?></div>
      <div style="font-size:10px;color:var(--text-secondary)">Total Eggs</div>
    </div>
    <div style="background:var(--bg);border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:18px;font-weight:800"><?=$ageWks?>w</div>
      <div style="font-size:10px;color:var(--text-secondary)">Age</div>
    </div>
  </div>
  <div style="margin-top:12px;font-size:13px;color:var(--text-secondary)">
    Lay Rate: <strong><?=$layRate?>%</strong> &nbsp;·&nbsp; Deaths: <strong style="color:<?=$f['total_deaths']>0?'var(--danger)':'var(--accent)' ?>"><?=$f['total_deaths']?></strong>
  </div>
  <div style="display:flex;gap:6px;margin-top:12px">
    <a href="production.php?flock_id=<?=$f['id']?>" class="btn btn-primary btn-sm">🥚 Log Eggs</a>
    <button class="btn btn-secondary btn-sm" onclick='editFlock(<?=json_encode($f)?>)'>✏️</button>
    <?php if($f['status']==='active'):?><form method="POST" style="display:inline"><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?=$f['id']?>"><button class="btn btn-danger btn-sm" data-confirm="Close flock?">Close</button></form><?php endif;?>
  </div>
</div>
<?php endforeach;?>
<?php if(!$flocks):?><div class="card" style="grid-column:1/-1"><div class="card-body"><div class="empty-state"><div class="empty-icon">🐔</div><h3>No flocks yet</h3><p>Add your first flock to start tracking egg production</p></div></div></div><?php endif;?>
</div>

<div class="modal-overlay" id="flockModal"><div class="modal">
  <div class="modal-header"><h3 id="flockTitle">Add Flock</h3><button class="btn-close" onclick="closeModal('flockModal')">✕</button></div>
  <div class="modal-body"><form method="POST" action="flocks.php">
    <input type="hidden" name="action" id="flockAction" value="add"><input type="hidden" name="id" id="flockId">
    <div class="form-group"><label class="form-label">Flock Name *</label><input type="text" name="name" id="flockName" class="form-control" placeholder="e.g. Batch A — Layer Reds" required></div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">House / Pen</label><input type="text" name="house" id="flockHouse" class="form-control" placeholder="e.g. House 1"></div>
      <div class="form-group"><label class="form-label">Breed</label><input type="text" name="breed" id="flockBreed" class="form-control" placeholder="e.g. Isa Brown"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label class="form-label">Source</label><input type="text" name="source" id="flockSource" class="form-control" placeholder="e.g. Agrited Farm"></div>
      <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" id="flockStart" class="form-control" value="<?=date('Y-m-d')?>"></div>
    </div>
    <div class="form-group"><label class="form-label">Number of Birds</label><input type="number" name="initial_count" id="flockCount" class="form-control" value="0" min="0"></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('flockModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Flock</button></div>
  </form></div>
</div></div>
<script>
function editFlock(f){
  document.getElementById('flockTitle').textContent='Edit Flock';
  document.getElementById('flockAction').value='edit';
  document.getElementById('flockId').value=f.id;
  document.getElementById('flockName').value=f.name;
  document.getElementById('flockHouse').value=f.house||'';
  document.getElementById('flockBreed').value=f.breed||'';
  document.getElementById('flockSource').value=f.source||'';
  document.getElementById('flockStart').value=f.start_date||'';
  document.getElementById('flockCount').value=f.initial_count||0;
  openModal('flockModal');
}
</script>
<?php include 'includes/footer.php';?>
