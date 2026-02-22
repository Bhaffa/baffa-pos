<?php
require_once 'config.php'; requireLogin(); define('PAGE_TITLE','Suppliers');
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if(in_array($act,['add','edit'])){
        $name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$email=trim($_POST['email']??'');$address=trim($_POST['address']??'');$id=intval($_POST['id']??0);
        if(!$name){redirect('suppliers.php','Name required.','error');}
        if($act==='add') $pdo->prepare("INSERT INTO suppliers(name,phone,email,address)VALUES(?,?,?,?)")->execute([$name,$phone,$email,$address]);
        else $pdo->prepare("UPDATE suppliers SET name=?,phone=?,email=?,address=? WHERE id=?")->execute([$name,$phone,$email,$address,$id]);
        redirect('suppliers.php','Supplier saved!');
    }
    if($act==='delete'){$pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([intval($_POST['id'])]);redirect('suppliers.php','Supplier deleted.');}
}
$suppliers=$pdo->query("SELECT s.*,COUNT(p.id) purchases FROM suppliers s LEFT JOIN purchases p ON s.id=p.supplier_id GROUP BY s.id ORDER BY s.name")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Suppliers</h2><p><?=count($suppliers)?> suppliers</p></div><button onclick="openModal('supModal')" class="btn btn-primary">+ Add Supplier</button></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Purchases</th><th>Actions</th></tr></thead>
<tbody>
<?php $n=1;foreach($suppliers as $s):?>
<tr><td class="text-muted"><?=$n++?></td><td class="fw-600"><?=clean($s['name'])?></td><td><?=clean($s['phone']??'—')?></td><td><?=clean($s['email']??'—')?></td><td><?=$s['purchases']?></td>
<td><div class="td-actions">
  <button class="btn btn-secondary btn-xs" onclick='editSup(<?=json_encode($s)?>)'>✏️</button>
  <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$s['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete supplier?">🗑</button></form>
</div></td></tr>
<?php endforeach;?></tbody></table></div></div>

<div class="modal-overlay" id="supModal"><div class="modal"><div class="modal-header"><h3 id="supTitle">Add Supplier</h3><button class="btn-close" onclick="closeModal('supModal')">✕</button></div>
<div class="modal-body"><form method="POST" action="suppliers.php">
  <input type="hidden" name="action" id="supAction" value="add"><input type="hidden" name="id" id="supId">
  <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" id="supName" class="form-control" required></div>
  <div class="form-row-2"><div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="supPhone" class="form-control"></div>
  <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="supEmail" class="form-control"></div></div>
  <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="supAddress" class="form-control" rows="2"></textarea></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('supModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
<script>function editSup(s){document.getElementById('supTitle').textContent='Edit Supplier';document.getElementById('supAction').value='edit';document.getElementById('supId').value=s.id;document.getElementById('supName').value=s.name;document.getElementById('supPhone').value=s.phone||'';document.getElementById('supEmail').value=s.email||'';document.getElementById('supAddress').value=s.address||'';openModal('supModal');}</script>
<?php include 'includes/footer.php';?>
