<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Purchases / Stock In');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='add_purchase'){
        $supId=intval($_POST['supplier_id']??0)?:null;
        $ref=trim($_POST['reference']??'PO-'.date('YmdHis'));
        $notes=trim($_POST['notes']??'');
        $products_=json_decode($_POST['purchase_items']??'[]',true);
        if(empty($products_)){redirect('purchases.php','No items added.','error');}
        $total=array_sum(array_column($products_,'total'));
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO purchases(reference,supplier_id,total,status,notes)VALUES(?,?,?,'received',?)")->execute([$ref,$supId,$total,$notes]);
        $pid=$pdo->lastInsertId();
        foreach($products_ as $item){
            $pdo->prepare("INSERT INTO purchase_items(purchase_id,product_id,product_name,qty,unit_cost,total)VALUES(?,?,?,?,?,?)")->execute([$pid,$item['id'],$item['name'],$item['qty'],$item['cost'],$item['total']]);
            if($item['id']) addStock($pdo,intval($item['id']),$item['qty']);
        }
        $pdo->commit();
        logAction($pdo,'PURCHASE',"Ref:$ref Total:$total");
        redirect('purchases.php','Purchase recorded and stock updated!');
    }
}

$purchases=$pdo->query("SELECT p.*,s.name sup_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.created_at DESC LIMIT 80")->fetchAll();
$suppliers=$pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();
$prodList=$pdo->query("SELECT id,name,unit,cost_price FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Purchases & Stock In</h2><p>Record incoming stock</p></div><button onclick="openModal('purchModal')" class="btn btn-primary">+ New Purchase</button></div>
<div class="card"><div class="table-wrap">
  <table><thead><tr><th>Reference</th><th>Supplier</th><th data-sort="2">Total</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
    <?php foreach($purchases as $p):?>
    <tr>
      <td class="fw-600 mono"><?=clean($p['reference']??'')?></td>
      <td><?=clean($p['sup_name']??'Unknown')?></td>
      <td class="fw-700"><?=$sym?><?=number_format($p['total'],2)?></td>
      <td><span class="badge badge-success">Received</span></td>
      <td class="text-muted" style="font-size:12px"><?=date('d M Y',strtotime($p['created_at']))?></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$purchases):?><tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🚚</div><h3>No purchases yet</h3></div></td></tr><?php endif;?>
  </tbody>
  </table>
</div></div>

<div class="modal-overlay" id="purchModal"><div class="modal modal-lg">
  <div class="modal-header"><h3>New Purchase / Stock In</h3><button class="btn-close" onclick="closeModal('purchModal')">✕</button></div>
  <div class="modal-body">
    <div class="form-row-2 mb-2">
      <div class="form-group"><label class="form-label">Reference #</label><input type="text" id="purchRef" class="form-control" value="PO-<?=date('Ymd')?>"></div>
      <div class="form-group"><label class="form-label">Supplier</label>
        <select id="purchSup" class="form-control"><option value="">-- Walk-in / Direct --</option><?php foreach($suppliers as $s):?><option value="<?=$s['id']?>"><?=clean($s['name']??'')?></option><?php endforeach;?></select>
      </div>
    </div>
    <div style="margin-bottom:12px">
      <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:10px">
        <div style="flex:1"><label class="form-label">Product</label><select id="addProdSel" class="form-control"><option value="">-- Select --</option><?php foreach($prodList as $p):?><option value="<?=$p['id']?>" data-unit="<?=clean($p['unit']??'')?>" data-cost="<?=$p['cost_price']?>"><?=clean($p['name']??'')?> (<?=clean($p['unit']??'')?>)</option><?php endforeach;?></select></div>
        <div style="width:90px"><label class="form-label">Qty</label><input type="number" id="addQty" class="form-control" value="1" min="0.01" step="0.01"></div>
        <div style="width:110px"><label class="form-label">Unit Cost</label><input type="number" id="addCost" class="form-control" value="0" min="0" step="0.01"></div>
        <button type="button" class="btn btn-primary btn-sm" onclick="addPurchLine()" style="margin-bottom:0;height:40px">Add</button>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px" id="purchLines">
        <thead><tr style="background:var(--bg)"><th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Product</th><th style="padding:8px;text-align:right;border-bottom:1px solid var(--border)">Qty</th><th style="padding:8px;text-align:right;border-bottom:1px solid var(--border)">Cost</th><th style="padding:8px;text-align:right;border-bottom:1px solid var(--border)">Total</th><th style="padding:8px;border-bottom:1px solid var(--border)"></th></tr></thead>
        <tbody id="purchLineBody"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">No items yet</td></tr></tbody>
        <tfoot><tr><td colspan="3" style="padding:8px;text-align:right;font-weight:700;border-top:2px solid var(--border)">Total</td><td id="purchTotal" style="padding:8px;text-align:right;font-weight:800;border-top:2px solid var(--border)"><?=$sym?>0.00</td><td></td></tr></tfoot>
      </table>
    </div>
    <div class="form-group"><label class="form-label">Notes</label><input type="text" id="purchNotes" class="form-control"></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('purchModal')">Cancel</button><button type="button" class="btn btn-primary" onclick="savePurchase()">Save Purchase</button></div>
  </div>
</div></div>

<script>
const SYM='<?=$sym?>';
let purchLines=[];
function addPurchLine(){
  const sel=document.getElementById('addProdSel');
  const opt=sel.selectedOptions[0];
  if(!opt.value)return;
  const qty=parseFloat(document.getElementById('addQty').value)||1;
  const cost=parseFloat(document.getElementById('addCost').value)||0;
  purchLines.push({id:opt.value,name:opt.text.split(' (')[0],qty,cost,total:qty*cost});
  renderLines();
}
function removeLine(i){purchLines.splice(i,1);renderLines();}
function renderLines(){
  const tb=document.getElementById('purchLineBody');
  if(!purchLines.length){tb.innerHTML='<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">No items yet</td></tr>';document.getElementById('purchTotal').textContent=SYM+'0.00';return;}
  tb.innerHTML=purchLines.map((l,i)=>`<tr><td style="padding:8px">${l.name}</td><td style="padding:8px;text-align:right">${l.qty}</td><td style="padding:8px;text-align:right">${SYM}${l.cost.toFixed(2)}</td><td style="padding:8px;text-align:right;font-weight:700">${SYM}${l.total.toFixed(2)}</td><td style="padding:8px"><button onclick="removeLine(${i})" style="border:none;background:none;color:var(--danger);cursor:pointer">✕</button></td></tr>`).join('');
  const tot=purchLines.reduce((s,l)=>s+l.total,0);
  document.getElementById('purchTotal').textContent=SYM+tot.toFixed(2);
}
async function savePurchase(){
  if(!purchLines.length){alert('Add at least one item');return;}
  const fd=new FormData();
  fd.append('action','add_purchase');
  fd.append('supplier_id',document.getElementById('purchSup').value);
  fd.append('reference',document.getElementById('purchRef').value);
  fd.append('notes',document.getElementById('purchNotes').value);
  fd.append('purchase_items',JSON.stringify(purchLines));
  const r=await fetch('purchases.php',{method:'POST',body:fd});
  window.location='purchases.php';
}
</script>
<?php include 'includes/footer.php';?>
