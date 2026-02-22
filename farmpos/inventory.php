<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Stock Management');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='adjust'){
        $pid=intval($_POST['product_id']);$change=floatval($_POST['qty_change']);$type=$_POST['type']??'add';$reason=trim($_POST['reason']??'');
        $cur=$pdo->prepare("SELECT stock_qty FROM products WHERE id=?");$cur->execute([$pid]);$cur=floatval($cur->fetchColumn());
        $final=$type==='remove'?$cur-abs($change):$cur+abs($change);
        if($final<0)$final=0;
        $pdo->prepare("UPDATE products SET stock_qty=? WHERE id=?")->execute([$final,$pid]);
        $pdo->prepare("INSERT INTO inventory_adjustments(product_id,qty_before,qty_change,qty_after,type,reason)VALUES(?,?,?,?,?,?)")->execute([$pid,$cur,$change,$final,$type,$reason]);
        logAction($pdo,'STOCK_ADJUST',"PID:$pid Change:$change Type:$type");
        redirect('inventory.php','Stock adjusted successfully!');
    }
}

$filter=$_GET['filter']??'all';
$sql="SELECT p.*,c.name cat_name,c.icon cat_icon FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1";
if($filter==='low') $sql.=" AND p.track_stock=1 AND p.stock_qty<=p.reorder_level";
if($filter==='out') $sql.=" AND p.track_stock=1 AND p.stock_qty<=0";
$sql.=" ORDER BY p.name";
$products=$pdo->query($sql)->fetchAll();
$totalValue=$pdo->query("SELECT COALESCE(SUM(stock_qty*cost_price),0) FROM products WHERE track_stock=1 AND is_active=1")->fetchColumn();
$lowCount=$pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_qty<=reorder_level AND is_active=1")->fetchColumn();
$outCount=$pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_qty<=0 AND is_active=1")->fetchColumn();
$adjLog=$pdo->query("SELECT a.*,p.name pname FROM inventory_adjustments a LEFT JOIN products p ON a.product_id=p.id ORDER BY a.created_at DESC LIMIT 15")->fetchAll();
include 'includes/header.php';
?>
<div class="page-header" id="low">
  <div><h2>Stock Management</h2><p>Inventory levels and adjustments</p></div>
  <button onclick="openModal('adjustModal')" class="btn btn-primary">⚖️ Adjust Stock</button>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="kpi-card"><div class="kpi-icon">📦</div><div class="kpi-value"><?=count($pdo->query("SELECT id FROM products WHERE is_active=1")->fetchAll())?></div><div class="kpi-label">Total Products</div></div>
  <div class="kpi-card"><div class="kpi-icon">💰</div><div class="kpi-value"><?=$sym?><?=number_format($totalValue,0)?></div><div class="kpi-label">Inventory Value</div></div>
  <div class="kpi-card"><div class="kpi-icon">⚠️</div><div class="kpi-value" style="color:var(--warning)"><?=$lowCount?></div><div class="kpi-label">Low Stock</div></div>
  <div class="kpi-card"><div class="kpi-icon">❌</div><div class="kpi-value" style="color:var(--danger)"><?=$outCount?></div><div class="kpi-label">Out of Stock</div></div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px">
  <a href="inventory.php" class="cat-btn <?=!$filter||$filter==='all'?'active':''?>">All Stock</a>
  <a href="?filter=low" class="cat-btn <?=$filter==='low'?'active':''?>">⚠️ Low Stock (<?=$lowCount?>)</a>
  <a href="?filter=out" class="cat-btn <?=$filter==='out'?'active':''?>">❌ Out of Stock (<?=$outCount?>)</a>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:18px">
<div class="card"><div class="table-wrap">
  <table><thead><tr><th>Product</th><th>Category</th><th>Unit</th><th data-sort="3">Stock</th><th>Reorder</th><th>Value</th><th>Status</th><th>Adjust</th></tr></thead>
  <tbody>
    <?php foreach($products as $p):$lowS=$p['track_stock']&&$p['stock_qty']<=$p['reorder_level'];?>
    <tr>
      <td><div class="fw-600"><?=clean($p['name']??'')?></div><?php if($p['sku']):?><div class="text-muted" style="font-size:11px"><?=clean($p['sku']??'')?></div><?php endif;?></td>
      <td><?=clean($p['cat_icon']??'📦')?> <?=clean($p['cat_name']??'—')?></td>
      <td><?=clean($p['unit']??'')?></td>
      <td class="fw-700 <?=$p['stock_qty']<=0?'text-danger':($lowS?'text-warning':'text-success')?>"><?=$p['track_stock']?number_format($p['stock_qty'],2):'∞'?></td>
      <td class="text-muted"><?=$p['track_stock']?number_format($p['reorder_level'],0):'—'?></td>
      <td><?=$sym?><?=number_format($p['stock_qty']*$p['cost_price'],0)?></td>
      <td><span class="badge badge-<?=$p['stock_qty']<=0?'danger':($lowS?'warning':'success')?>"><?=$p['stock_qty']<=0?'Out':($lowS?'Low':'OK')?></span></td>
      <td><button class="btn btn-outline btn-xs" onclick="quickAdjust(<?=$p['id']?>,<?=json_encode($p['name'])?>)">⚖️</button></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$products):?><tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📦</div><h3>No products</h3><p><a href="products.php">Add products →</a></p></div></td></tr><?php endif;?>
  </tbody>
  </table>
</div></div>

<div class="card"><div class="card-header"><h3>Recent Adjustments</h3></div><div class="table-wrap">
  <table><thead><tr><th>Product</th><th>Change</th><th>Type</th><th>Date</th></tr></thead>
  <tbody>
    <?php foreach($adjLog as $a):?>
    <tr>
      <td style="font-size:12px"><?=clean($a['pname']??'—')?></td>
      <td class="fw-600 <?=$a['type']==='remove'?'text-danger':'text-success'?>"><?=$a['type']==='remove'?'−':'+'?><?=number_format(abs($a['qty_change']),2)?></td>
      <td><span class="badge badge-secondary" style="font-size:10px"><?=ucfirst($a['type'])?></span></td>
      <td style="font-size:11px;color:var(--text-secondary)"><?=date('d M H:i',strtotime($a['created_at']))?></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$adjLog):?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-secondary)">No adjustments yet</td></tr><?php endif;?>
  </tbody>
  </table>
</div></div>
</div>

<!-- Adjust Modal -->
<div class="modal-overlay" id="adjustModal">
  <div class="modal">
    <div class="modal-header"><h3>⚖️ Adjust Stock</h3><button class="btn-close" onclick="closeModal('adjustModal')">✕</button></div>
    <div class="modal-body">
      <form method="POST" action="inventory.php">
        <input type="hidden" name="action" value="adjust">
        <div class="form-group"><label class="form-label">Product *</label>
          <select name="product_id" id="adjProduct" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach($pdo->query("SELECT id,name,stock_qty,unit FROM products WHERE track_stock=1 AND is_active=1 ORDER BY name") as $p):?>
            <option value="<?=$p['id']?>"><?=clean($p['name']??'')?> (<?=number_format($p['stock_qty'],2)?> <?=$p['unit']?>)</option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label class="form-label">Qty Change *</label><input type="number" name="qty_change" class="form-control" step="0.01" min="0.01" required></div>
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="add">➕ Add Stock</option>
              <option value="remove">➖ Remove</option>
              <option value="damage">💔 Damage/Loss</option>
              <option value="correction">✏️ Correction</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control" placeholder="e.g. Received new delivery"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button><button type="submit" class="btn btn-primary">Apply Adjustment</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function quickAdjust(id, name) {
  document.getElementById('adjProduct').value = id;
  openModal('adjustModal');
}
</script>
<?php include 'includes/footer.php';?>
