<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Dashboard');

$settings = getSettings($pdo);
$sym = $settings['currency_symbol'] ?? '₦';

// ─── KPI Stats ──────────────────────────────────────────
$today  = date('Y-m-d');
$month  = date('Y-m');

$s=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND status='completed'");$s->execute([$today]);$todayRev=$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=? AND status='completed'");$s->execute([$today]);$todayTx=$s->fetchColumn();
$s=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE_FORMAT(created_at,'%Y-%m')=? AND status='completed'");$s->execute([$month]);$monthRev=$s->fetchColumn();
$totalCusts= $pdo->query("SELECT COUNT(*) FROM customers WHERE id>1")->fetchColumn();
$lowStock  = $pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_qty<=reorder_level AND is_active=1")->fetchColumn();

// Farm KPIs
$todayEggs = 0; $activeFlocks = 0; $totalBirds = 0;
try {
    $s=$pdo->prepare("SELECT COALESCE(SUM(total_collected),0) FROM production_logs WHERE log_date=?");$s->execute([$today]);$todayEggs=(int)$s->fetchColumn();
    $activeFlocks= (int)$pdo->query("SELECT COUNT(*) FROM flocks WHERE status='active'")->fetchColumn();
    $totalBirds  = (int)$pdo->query("SELECT COALESCE(SUM(current_count),0) FROM flocks WHERE status='active'")->fetchColumn();
} catch(Exception $e) {}

// Weekly sales chart (last 7 days)
$weekly = $pdo->query("SELECT DATE(created_at) dt, COALESCE(SUM(total),0) rev FROM sales WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND status='completed' GROUP BY DATE(created_at) ORDER BY dt")->fetchAll();
$wData  = []; for($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-$i days")); $wData[$d]=0; }
foreach($weekly as $w) $wData[$w['dt']] = floatval($w['rev']);
$maxW = max(array_values($wData)) ?: 1;

// Top products today
$s=$pdo->prepare("SELECT si.product_name, SUM(si.qty) qty, SUM(si.total) rev FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at)=? AND s.status='completed' GROUP BY si.product_name ORDER BY rev DESC LIMIT 6");$s->execute([$today]);$topProds=$s->fetchAll();

// Recent sales
$recent = $pdo->query("SELECT s.*,c.name cust_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.created_at DESC LIMIT 8")->fetchAll();

// Low stock products
$lowProds = $pdo->query("SELECT * FROM products WHERE track_stock=1 AND stock_qty<=reorder_level AND is_active=1 ORDER BY stock_qty ASC LIMIT 6")->fetchAll();

// Monthly cost (expenses + purchases)
$s=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE_FORMAT(expense_date,'%Y-%m')=?");$s->execute([$month]);$monthExp=$s->fetchColumn();
$s=$pdo->prepare("SELECT COALESCE(SUM(si.qty*(si.unit_price-si.cost_price)),0) FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE_FORMAT(s.created_at,'%Y-%m')=? AND s.status='completed'");$s->execute([$month]);$grossProfit=$s->fetchColumn();

include 'includes/header.php';
?>

<div class="page-header">
  <div><h2>Dashboard</h2><p><?= date('l, d F Y') ?></p></div>
  <a href="pos.php" class="btn btn-primary btn-lg">🛒 Open POS</a>
</div>

<!-- KPI Grid -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-icon">💰</div>
    <div class="kpi-value"><?= $sym ?><?= number_format($todayRev, 0) ?></div>
    <div class="kpi-label">Today's Revenue</div>
    <div class="kpi-change up">↑ <?= $todayTx ?> transactions</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">📅</div>
    <div class="kpi-value"><?= $sym ?><?= number_format($monthRev, 0) ?></div>
    <div class="kpi-label">This Month</div>
    <div class="kpi-change up">Gross Profit: <?= $sym ?><?= number_format($grossProfit, 0) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">🥚</div>
    <div class="kpi-value"><?= number_format($todayEggs) ?></div>
    <div class="kpi-label">Eggs Collected Today</div>
    <div class="kpi-change"><?= $activeFlocks ?> active flock<?= $activeFlocks!=1?'s':'' ?> · <?= number_format($totalBirds) ?> birds</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">👥</div>
    <div class="kpi-value"><?= number_format($totalCusts) ?></div>
    <div class="kpi-label">Customers</div>
    <div class="kpi-change"><a href="customers.php">View all →</a></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">📦</div>
    <div class="kpi-value" style="color:<?= $lowStock>0?'var(--danger)':'var(--accent)' ?>"><?= $lowStock ?></div>
    <div class="kpi-label">Low Stock Alerts</div>
    <div class="kpi-change <?= $lowStock>0?'down':'up' ?>"><?= $lowStock>0?'⚠️ Needs restocking':'✓ All stocked' ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon">💸</div>
    <div class="kpi-value"><?= $sym ?><?= number_format($monthExp, 0) ?></div>
    <div class="kpi-label">Expenses This Month</div>
    <div class="kpi-change"><a href="expenses.php">View expenses →</a></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:18px;margin-bottom:18px">

  <!-- Weekly Chart -->
  <div class="card">
    <div class="card-header">
      <h3>📈 Sales — Last 7 Days</h3>
      <a href="reports.php" class="btn btn-secondary btn-sm">Full Report</a>
    </div>
    <div class="card-body">
      <div class="bar-chart" style="height:120px;align-items:flex-end">
        <?php foreach($wData as $d => $rev): $pct = round($rev/$maxW*100); ?>
        <div class="bar-item">
          <div style="font-size:10px;color:var(--text-secondary);margin-bottom:3px"><?= $sym ?><?= $rev>999?round($rev/1000).'k':number_format($rev,0) ?></div>
          <div class="bar" style="height:<?= max(4,$pct) ?>px;background:<?= date('Y-m-d')==$d?'var(--primary)':'rgba(245,158,11,.35)' ?>"></div>
          <div class="bar-label"><?= date('D',strtotime($d)) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Top Products Today -->
  <div class="card">
    <div class="card-header"><h3>🏆 Top Products Today</h3></div>
    <?php if($topProds): ?>
    <div class="table-wrap">
      <table>
        <tbody>
          <?php foreach($topProds as $p): ?>
          <tr>
            <td class="fw-600"><?= clean($p['product_name']??'') ?></td>
            <td class="text-muted" style="text-align:right"><?= number_format($p['qty'],1) ?></td>
            <td class="fw-700 text-primary" style="text-align:right"><?= $sym ?><?= number_format($p['rev'],0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="card-body"><div class="empty-state" style="padding:20px"><div class="empty-icon">🛒</div><p>No sales yet today</p></div></div>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:18px">

  <!-- Recent Sales -->
  <div class="card">
    <div class="card-header">
      <h3>🧾 Recent Sales</h3>
      <a href="sales.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Method</th><th>Status</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach($recent as $s): ?>
          <tr>
            <td class="fw-600 mono"><?= clean($s['invoice_no']??'') ?></td>
            <td><?= clean($s['cust_name']??'Walk-in') ?></td>
            <td class="fw-700"><?= $sym ?><?= number_format($s['total'],2) ?></td>
            <td><span class="badge badge-secondary"><?= str_replace('_',' ',ucfirst($s['payment_method'])) ?></span></td>
            <td>
              <span class="badge badge-<?= ['completed'=>'success','voided'=>'danger','partial'=>'warning','held'=>'secondary'][$s['status']]??'secondary' ?>">
                <?= ucfirst($s['status']) ?>
              </span>
            </td>
            <td class="text-muted" style="font-size:12px"><?= date('h:i A',strtotime($s['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$recent): ?><tr><td colspan="6"><div class="empty-state" style="padding:20px"><div class="empty-icon">📑</div><p>No sales yet</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="card">
    <div class="card-header"><h3>⚠️ Low Stock</h3><a href="inventory.php" class="btn btn-secondary btn-sm">Manage</a></div>
    <?php if($lowProds): ?>
    <div class="table-wrap">
      <table>
        <tbody>
          <?php foreach($lowProds as $p): ?>
          <tr>
            <td class="fw-600"><?= clean($p['name']??'') ?></td>
            <td class="text-right">
              <span class="badge badge-<?= $p['stock_qty']<=0?'danger':'warning' ?>"><?= number_format($p['stock_qty'],1) ?> <?= clean($p['unit']??'') ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:10px 16px"><a href="purchases.php" class="btn btn-primary btn-sm w-100" style="justify-content:center">+ Add Stock</a></div>
    <?php else: ?>
    <div class="card-body"><div class="empty-state" style="padding:20px"><div class="empty-icon">✅</div><p>All products well stocked</p></div></div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
