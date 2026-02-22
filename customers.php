<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Customers');
$settings = getSettings($pdo);
$sym = $settings['currency_symbol']??'₦';
$act = $_REQUEST['action']??'';

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(in_array($act,['add','edit'])){
        $name=trim($_POST['name']??'');
        $phone=trim($_POST['phone']??'');
        $email=trim($_POST['email']??'');
        $address=trim($_POST['address']??'');
        $type=$_POST['type']??'retail';
        $notes=trim($_POST['notes']??'');
        $id=intval($_POST['id']??0);
        if(!$name){redirect('customers.php','Name required.','error');}
        if($act==='add'){
            $pdo->prepare("INSERT INTO customers(name,phone,email,address,type,notes)VALUES(?,?,?,?,?,?)")->execute([$name,$phone,$email,$address,$type,$notes]);
            logAction($pdo,'ADD_CUSTOMER',"Name:$name");
            redirect('customers.php','Customer added!');
        }else{
            $pdo->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,type=?,notes=? WHERE id=?")->execute([$name,$phone,$email,$address,$type,$notes,$id]);
            redirect('customers.php','Customer updated!');
        }
    }
    if($act==='delete'){
        $id=intval($_POST['id']);
        if($id>1){$pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);redirect('customers.php','Customer deleted.');}
    }
    if($act==='clear_balance'){
        $id=intval($_POST['id']);
        $pdo->prepare("UPDATE customers SET balance=0 WHERE id=?")->execute([$id]);
        redirect('customers.php','Balance cleared.');
    }
}

$customers=$pdo->query("SELECT c.*,COUNT(s.id) total_sales,COALESCE(SUM(s.total),0) total_spent FROM customers c LEFT JOIN sales s ON c.id=s.customer_id AND s.status='completed' GROUP BY c.id ORDER BY c.name")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Customers</h2><p><?=count($customers)-1?> customers</p></div><button onclick="openModal('custModal')" class="btn btn-primary">+ New Customer</button></div>
<div class="card"><div class="table-wrap">
  <table id="custTable">
    <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Type</th><th>Purchases</th><th data-sort="4">Total Spent</th><th>Balance Due</th><th>Points</th><th>Actions</th></tr></thead>
    <tbody>
      <?php $n=0;foreach($customers as $c):if($c['id']==1)continue;$n++;?>
      <tr>
        <td class="text-muted"><?=$n?></td>
        <td><div class="fw-600"><?=clean($c['name'])?></div><?php if($c['email']):?><div class="text-muted" style="font-size:11px"><?=clean($c['email'])?></div><?php endif;?></td>
        <td><?=clean($c['phone']??'—')?></td>
        <td><span class="badge badge-<?=$c['type']==='wholesale'?'purple':'info'?>"><?=ucfirst($c['type'])?></span></td>
        <td><?=number_format($c['total_sales'])?></td>
        <td class="fw-600"><?=$sym?><?=number_format($c['total_spent'],2)?></td>
        <td><?php if($c['balance']>0):?><span class="badge badge-danger"><?=$sym?><?=number_format($c['balance'],2)?></span><?php else:?><span class="text-success">Clear</span><?php endif;?></td>
        <td><?=number_format($c['loyalty_points'])?> pts</td>
        <td><div class="td-actions">
          <button class="btn btn-secondary btn-xs" onclick='editCust(<?=json_encode($c)?>)'>✏️</button>
          <?php if($c['balance']>0):?><form method="POST" style="display:inline"><input type="hidden" name="action" value="clear_balance"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-warning btn-xs" data-confirm="Clear balance for <?=clean($c['name'])?>?">✓ Paid</button></form><?php endif;?>
          <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete <?=clean($c['name'])?>?">🗑</button></form>
        </div></td>
      </tr>
      <?php endforeach;?>
      <?php if($n==0):?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon">👥</div><h3>No customers yet</h3></div></td></tr><?php endif;?>
    </tbody>
  </table>
</div></div>

<div class="modal-overlay" id="custModal">
  <div class="modal">
    <div class="modal-header"><h3 id="custTitle">Add Customer</h3><button class="btn-close" onclick="closeModal('custModal')">✕</button></div>
    <div class="modal-body">
      <form method="POST" action="customers.php">
        <input type="hidden" name="action" id="custAction" value="add">
        <input type="hidden" name="id" id="custId">
        <div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" id="custName" class="form-control" required></div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" id="custPhone" class="form-control"></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="custEmail" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Customer Type</label>
          <select name="type" id="custType" class="form-control">
            <option value="retail">Retail</option>
            <option value="wholesale">Wholesale</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Address</label><textarea name="address" id="custAddress" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Notes</label><input type="text" name="notes" id="custNotes" class="form-control"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('custModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function editCust(c){
  document.getElementById('custTitle').textContent='Edit Customer';
  document.getElementById('custAction').value='edit';
  document.getElementById('custId').value=c.id;
  document.getElementById('custName').value=c.name;
  document.getElementById('custPhone').value=c.phone||'';
  document.getElementById('custEmail').value=c.email||'';
  document.getElementById('custType').value=c.type;
  document.getElementById('custAddress').value=c.address||'';
  document.getElementById('custNotes').value=c.notes||'';
  openModal('custModal');
}
</script>
<?php include 'includes/footer.php';?>
