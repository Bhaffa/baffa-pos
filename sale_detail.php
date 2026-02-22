<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Sale Detail');
$settings = getSettings($pdo);
$sym = $settings['currency_symbol']??'₦';
$id = intval($_GET['id']??0);
$sale=$pdo->prepare("SELECT s.*,c.name cust_name,c.phone cust_phone FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.id=?");
$sale->execute([$id]);$sale=$sale->fetch();
if(!$sale){redirect('sales.php','Sale not found.','error');}
$itemsStmt=$pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");$itemsStmt->execute([$id]);$saleItems=$itemsStmt->fetchAll();
include 'includes/header.php';
?>
<div class="page-header">
  <div><h2>Invoice <?=clean($sale['invoice_no'])?></h2><p><?=date('d F Y, h:i A',strtotime($sale['created_at']))?></p></div>
  <div class="d-flex gap-1">
    <button onclick="window.print()" class="btn btn-secondary btn-sm">🖨️ Print</button>
    <a href="sales.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 300px;gap:18px">
<div class="card">
  <div class="card-header"><h3>Items</h3></div>
  <div class="table-wrap">
    <table><thead><tr><th>#</th><th>Product</th><th>Unit</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
    <tbody>
      <?php $n=1;foreach($saleItems as $it):?>
      <tr><td><?=$n++?></td><td class="fw-600"><?=clean($it['product_name']??'')?></td><td><?=clean($it['unit']??'')?></td>
      <td><?=number_format(floatval($it['qty']??0),2)?></td><td><?=$sym?><?=number_format(floatval($it['unit_price']??0),2)?></td>
      <td class="fw-700"><?=$sym?><?=number_format(floatval($it['total']??0),2)?></td></tr>
      <?php endforeach;?>
      <?php if(empty($saleItems)):?><tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-secondary)">No item records found for this sale.</td></tr><?php endif;?>
    </tbody>
    <tfoot>
      <tr><td colspan="5" style="text-align:right;padding:10px 14px;font-weight:700">Subtotal</td><td style="padding:10px 14px;font-weight:700"><?=$sym?><?=number_format($sale['subtotal'],2)?></td></tr>
      <?php if($sale['discount_amount']>0):?><tr><td colspan="5" style="text-align:right;padding:4px 14px">Discount</td><td style="padding:4px 14px;color:var(--danger)">−<?=$sym?><?=number_format($sale['discount_amount'],2)?></td></tr><?php endif;?>
      <?php if($sale['tax_amount']>0):?><tr><td colspan="5" style="text-align:right;padding:4px 14px">Tax</td><td style="padding:4px 14px"><?=$sym?><?=number_format($sale['tax_amount'],2)?></td></tr><?php endif;?>
      <tr style="background:var(--bg)"><td colspan="5" style="text-align:right;padding:12px 14px;font-size:16px;font-weight:800">TOTAL</td><td style="padding:12px 14px;font-size:16px;font-weight:800"><?=$sym?><?=number_format($sale['total'],2)?></td></tr>
    </tfoot>
    </table>
  </div>
</div>
<div>
<div class="card mb-2">
  <div class="card-header"><h3>Sale Info</h3></div>
  <div class="card-body">
    <table style="font-size:13.5px;width:100%"><tbody>
      <tr><td class="text-muted" style="padding:5px 0;width:50%">Invoice</td><td class="fw-700 mono"><?=clean($sale['invoice_no'])?></td></tr>
      <tr><td class="text-muted" style="padding:5px 0">Customer</td><td><?=clean($sale['cust_name']??'Walk-in')?></td></tr>
      <tr><td class="text-muted" style="padding:5px 0">Payment</td><td><span class="badge badge-secondary"><?=str_replace('_',' ',ucfirst($sale['payment_method']))?></span></td></tr>
      <tr><td class="text-muted" style="padding:5px 0">Status</td><td><span class="badge badge-<?=['completed'=>'success','voided'=>'danger','partial'=>'warning'][$sale['status']]??'secondary'?>"><?=ucfirst($sale['status'])?></span></td></tr>
      <tr><td class="text-muted" style="padding:5px 0">Paid</td><td class="fw-600 text-success"><?=$sym?><?=number_format($sale['paid_amount'],2)?></td></tr>
      <?php if($sale['change_amount']>0):?><tr><td class="text-muted" style="padding:5px 0">Change</td><td><?=$sym?><?=number_format($sale['change_amount'],2)?></td></tr><?php endif;?>
      <?php if($sale['balance_due']>0):?><tr><td class="text-muted" style="padding:5px 0">Balance Due</td><td class="fw-700 text-danger"><?=$sym?><?=number_format($sale['balance_due'],2)?></td></tr><?php endif;?>
    </tbody></table>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3>Actions</h3></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
    <a href="returns.php?sale_id=<?=$sale['id']?>" class="btn btn-warning btn-sm">↩️ Process Return</a>
  </div>
</div>
</div>
</div>
<?php include 'includes/footer.php';?>
