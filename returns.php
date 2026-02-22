<?php
require_once 'config.php'; requireLogin(); define('PAGE_TITLE','Returns & Refunds');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';

if($_SERVER['REQUEST_METHOD']==='POST'&&$_POST['action']==='return'){
    $invoiceNo=trim($_POST['invoice_no']??'');
    // Allow lookup by invoice number OR by raw ID
    $saleId=0;
    if($invoiceNo){
        $si=$pdo->prepare("SELECT id FROM sales WHERE invoice_no=?");$si->execute([$invoiceNo]);$row=$si->fetch();
        if($row) $saleId=$row['id'];
    }
    if(!$saleId) $saleId=intval($_POST['sale_id']??0);
    $productName=trim($_POST['product_name']??'');$qty=floatval($_POST['qty']??1);$refund=floatval($_POST['refund_amount']??0);$reason=trim($_POST['reason']??'');$restock=isset($_POST['restock'])?1:0;$pid=intval($_POST['product_id']??0)?:null;
    $pdo->prepare("INSERT INTO returns(sale_id,product_id,product_name,qty,refund_amount,reason,restock)VALUES(?,?,?,?,?,?,?)")->execute([$saleId,$pid,$productName,$qty,$refund,$reason,$restock]);
    if($restock&&$pid) addStock($pdo,$pid,$qty);
    if($saleId){$pdo->prepare("UPDATE sales SET paid_amount=paid_amount-? WHERE id=?")->execute([$refund,$saleId]);}
    logAction($pdo,'RETURN',"Sale:$saleId Product:$productName Qty:$qty Refund:$refund");
    redirect('returns.php','Return processed. Refund: '.$sym.number_format($refund,2));
}

$saleId=intval($_GET['sale_id']??0);
$sale=null;$saleItems=[];
if($saleId){
    $st=$pdo->prepare("SELECT * FROM sales WHERE id=?");$st->execute([$saleId]);$sale=$st->fetch();
    $si=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");$si->execute([$saleId]);$saleItems=$si->fetchAll();
}

$returns=$pdo->query("SELECT r.*,s.invoice_no FROM returns r LEFT JOIN sales s ON r.sale_id=s.id ORDER BY r.created_at DESC LIMIT 50")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Returns & Refunds</h2></div><button onclick="openModal('returnModal')" class="btn btn-warning">↩️ New Return</button></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Product</th><th>Qty</th><th>Refund</th><th>Reason</th><th>Restocked</th><th>Date</th></tr></thead>
<tbody>
<?php foreach($returns as $r):?>
<tr><td class="fw-600 mono"><?=clean($r['invoice_no']??'—')?></td><td><?=clean($r['product_name'])?></td><td><?=number_format($r['qty'],2)?></td>
<td class="fw-700 text-danger"><?=$sym?><?=number_format($r['refund_amount'],2)?></td><td><?=clean($r['reason'])?></td>
<td><?=$r['restock']?'<span class="badge badge-success">Yes</span>':'<span class="badge badge-secondary">No</span>'?></td>
<td class="text-muted" style="font-size:12px"><?=date('d M Y',strtotime($r['created_at']))?></td></tr>
<?php endforeach;?><?php if(!$returns):?><tr><td colspan="7"><div class="empty-state"><div class="empty-icon">↩️</div><h3>No returns yet</h3></div></td></tr><?php endif;?>
</tbody></table></div></div>

<div class="modal-overlay" id="returnModal"><div class="modal"><div class="modal-header"><h3>Process Return</h3><button class="btn-close" onclick="closeModal('returnModal')">✕</button></div>
<div class="modal-body"><form method="POST" action="returns.php">
  <input type="hidden" name="action" value="return">
  <input type="hidden" name="sale_id" value="<?=$saleId?>">
  <div class="form-group"><label class="form-label">Invoice # (Optional)</label><input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV202602220001" value="<?=clean($sale['invoice_no']??'')?>"></div>
  <div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="product_name" class="form-control" required value="<?=clean($saleItems[0]['product_name']??'')?>"></div>
  <input type="hidden" name="product_id" value="<?=clean($saleItems[0]['product_id']??'')?>">
  <div class="form-row-2">
    <div class="form-group"><label class="form-label">Qty *</label><input type="number" name="qty" class="form-control" value="1" min="0.01" step="0.01" required></div>
    <div class="form-group"><label class="form-label">Refund Amount *</label><input type="number" name="refund_amount" class="form-control" value="0" min="0" step="0.01" required></div>
  </div>
  <div class="form-group"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control" placeholder="e.g. Damaged, wrong item"></div>
  <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="restock" value="1" checked> Restock returned item</label></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('returnModal')">Cancel</button><button type="submit" class="btn btn-warning">Process Return</button></div>
</form></div></div></div>
<?php include 'includes/footer.php';?>
