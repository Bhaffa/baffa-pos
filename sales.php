<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Sales History');

$settings = getSettings($pdo);
$sym = $settings['currency_symbol']??'₦';

// Void sale
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action']??'';
    if ($act==='void') {
        $id = intval($_POST['id']);
        $s = $pdo->prepare("SELECT * FROM sales WHERE id=?"); $s->execute([$id]); $sale = $s->fetch();
        if ($sale && $sale['status']!=='voided') {
            // Restore stock
            $items = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?"); $items->execute([$id]); $items=$items->fetchAll();
            foreach($items as $item) { if($item['product_id']) addStock($pdo,$item['product_id'],$item['qty']); }
            $pdo->prepare("UPDATE sales SET status='voided' WHERE id=?")->execute([$id]);
            logAction($pdo,'VOID_SALE',"ID:$id Invoice:{$sale['invoice_no']}");
            redirect('sales.php','Sale '.$sale['invoice_no'].' voided and stock restored.');
        }
    }
}

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to']   ?? date('Y-m-d');
$method = $_GET['method'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT s.*, c.name cust_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE DATE(s.created_at) BETWEEN ? AND ?";
$par = [$from, $to];
if ($method) { $sql.=" AND s.payment_method=?"; $par[]=$method; }
if ($status) { $sql.=" AND s.status=?"; $par[]=$status; }
$sql .= " ORDER BY s.created_at DESC";
$st = $pdo->prepare($sql); $st->execute($par); $sales = $st->fetchAll();

$totals = array_reduce($sales, fn($c,$s)=>['rev'=>$c['rev']+$s['total'],'tx'=>$c['tx']+1], ['rev'=>0,'tx'=>0]);

include 'includes/header.php';
?>

<div class="page-header">
  <div><h2>Sales History</h2><p><?= count($sales) ?> transactions · <?= $sym ?><?= number_format($totals['rev'],2) ?> total</p></div>
  <div class="d-flex gap-1">
    <button onclick="exportCSV('salesTable','sales.csv')" class="btn btn-secondary btn-sm">📥 CSV</button>
    <a href="pos.php" class="btn btn-primary btn-sm">🛒 New Sale</a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card mb-2">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:140px"></div>
      <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:140px"></div>
      <div><label class="form-label">Method</label>
        <select name="method" class="form-control" style="width:130px">
          <option value="">All</option>
          <option value="cash" <?= $method=='cash'?'selected':'' ?>>Cash</option>
          <option value="card" <?= $method=='card'?'selected':'' ?>>Card</option>
          <option value="mobile_money" <?= $method=='mobile_money'?'selected':'' ?>>MoMo</option>
          <option value="credit" <?= $method=='credit'?'selected':'' ?>>Credit</option>
        </select>
      </div>
      <div><label class="form-label">Status</label>
        <select name="status" class="form-control" style="width:120px">
          <option value="">All</option>
          <option value="completed" <?= $status=='completed'?'selected':'' ?>>Completed</option>
          <option value="partial"   <?= $status=='partial'?'selected':''   ?>>Partial</option>
          <option value="voided"    <?= $status=='voided'?'selected':''    ?>>Voided</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="sales.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="salesTable">
      <thead><tr>
        <th>Invoice</th><th>Date</th><th>Customer</th>
        <th data-sort="3">Total</th><th data-sort="4">Paid</th><th data-sort="5">Balance</th>
        <th>Method</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach($sales as $s): ?>
        <tr>
          <td class="fw-700 mono"><a href="sale_detail.php?id=<?= $s['id'] ?>" style="color:var(--primary);text-decoration:none"><?= clean($s['invoice_no']) ?></a></td>
          <td style="font-size:12px"><?= date('d M Y H:i',strtotime($s['created_at'])) ?></td>
          <td><?= clean($s['cust_name']??'Walk-in') ?></td>
          <td class="fw-600"><?= $sym ?><?= number_format($s['total'],2) ?></td>
          <td><?= $sym ?><?= number_format($s['paid_amount'],2) ?></td>
          <td class="<?= $s['balance_due']>0?'text-danger fw-600':'' ?>"><?= $sym ?><?= number_format($s['balance_due'],2) ?></td>
          <td><span class="badge badge-secondary"><?= str_replace('_',' ',ucfirst($s['payment_method'])) ?></span></td>
          <td><span class="badge badge-<?= ['completed'=>'success','voided'=>'danger','partial'=>'warning','held'=>'secondary'][$s['status']]??'secondary' ?>"><?= ucfirst($s['status']) ?></span></td>
          <td>
            <div class="td-actions">
              <a href="sale_detail.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-xs">👁</a>
              <?php if($s['status']!=='voided'): ?>
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-danger btn-xs" data-confirm="Void sale <?= clean($s['invoice_no']) ?>? Stock will be restored.">Void</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$sales): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📑</div><h3>No sales found</h3></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
