<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE', 'Reports & Analytics');
$settings = getSettings($pdo);
$sym = $settings['currency_symbol'] ?? '₦';

$period = $_GET['period'] ?? 'month';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');

switch ($period) {
    case 'today': $from = $to = date('Y-m-d'); break;
    case 'week':  $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'month': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'year':  $from = date('Y-01-01'); $to = date('Y-m-d'); break;
}

$salesSum = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue, COALESCE(SUM(paid_amount),0) collected, COALESCE(SUM(balance_due),0) outstanding FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
$salesSum->execute([$from, $to]); $salesSum = $salesSum->fetch();

$cogsSt = $pdo->prepare("SELECT COALESCE(SUM(si.qty*si.cost_price),0) FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'");
$cogsSt->execute([$from, $to]); $cogs = $cogsSt->fetchColumn();
$grossProfit = $salesSum['revenue'] - $cogs;
$grossMargin = $salesSum['revenue'] > 0 ? round($grossProfit / $salesSum['revenue'] * 100, 1) : 0;

$expSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expSt->execute([$from, $to]); $totalExp = $expSt->fetchColumn();
$feedCostSt = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) FROM feed_issuance WHERE issue_date BETWEEN ? AND ?");
$feedCostSt->execute([$from, $to]); $feedCost = $feedCostSt->fetchColumn();
$netProfit = $grossProfit - $totalExp;

$dailySales = $pdo->prepare("SELECT DATE(created_at) dt, SUM(total) rev, COUNT(*) cnt FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY DATE(created_at) ORDER BY dt");
$dailySales->execute([$from, $to]); $dailySales = $dailySales->fetchAll();

$topProducts = $pdo->prepare("SELECT si.product_name, SUM(si.qty) total_qty, SUM(si.total) total_rev, SUM(si.qty*(si.unit_price-si.cost_price)) profit FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY si.product_name ORDER BY total_rev DESC LIMIT 10");
$topProducts->execute([$from, $to]); $topProducts = $topProducts->fetchAll();
$maxProdRev = count($topProducts) > 0 ? max(array_column($topProducts, 'total_rev')) : 1;

$payBreak = $pdo->prepare("SELECT payment_method, COUNT(*) cnt, SUM(total) rev FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed' GROUP BY payment_method ORDER BY rev DESC");
$payBreak->execute([$from, $to]); $payBreak = $payBreak->fetchAll();
$totalPayRev = array_sum(array_column($payBreak, 'rev')) ?: 1;

$topCusts = $pdo->prepare("SELECT c.name, COUNT(s.id) cnt, SUM(s.total) total FROM sales s JOIN customers c ON s.customer_id=c.id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed' GROUP BY c.id ORDER BY total DESC LIMIT 8");
$topCusts->execute([$from, $to]); $topCusts = $topCusts->fetchAll();

$eggSum = $pdo->prepare("SELECT COALESCE(SUM(total_collected),0) total, COALESCE(SUM(sellable),0) sellable, COALESCE(SUM(eggs_cracked),0) cracked, COALESCE(SUM(eggs_grade_a),0) grade_a FROM production_logs WHERE log_date BETWEEN ? AND ?");
$eggSum->execute([$from, $to]); $eggSum = $eggSum->fetch();
$eggWaste = $eggSum['total'] > 0 ? round(($eggSum['total'] - $eggSum['sellable']) / $eggSum['total'] * 100, 1) : 0;
$costPerEgg = $eggSum['sellable'] > 0 ? round(($feedCost + $totalExp) / $eggSum['sellable'], 2) : 0;

$expByCat = $pdo->prepare("SELECT category, SUM(amount) total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$expByCat->execute([$from, $to]); $expByCat = $expByCat->fetchAll();
$maxExp = count($expByCat) > 0 ? max(array_column($expByCat, 'total')) : 1;

include 'includes/header.php';
?>

<div class="page-header">
  <div><h2>Reports & Analytics</h2><p><?= date('d M Y',strtotime($from)) ?> &ndash; <?= date('d M Y',strtotime($to)) ?></p></div>
  <div class="d-flex gap-1">
    <button onclick="window.print()" class="btn btn-secondary btn-sm">Print</button>
    <button onclick="exportCSV('topProdTable','products.csv')" class="btn btn-secondary btn-sm">CSV</button>
  </div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
  <?php foreach(['today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year'] as $k=>$l): ?>
  <a href="?period=<?=$k?>" class="cat-btn <?=$period===$k?'active':''?>"><?=$l?></a>
  <?php endforeach; ?>
  <form method="GET" style="display:flex;gap:8px;align-items:center;margin-left:8px">
    <input type="date" name="from" class="form-control" value="<?=$from?>" style="width:140px">
    <span class="text-muted">to</span>
    <input type="date" name="to" class="form-control" value="<?=$to?>" style="width:140px">
    <button type="submit" class="btn btn-primary btn-sm">Go</button>
  </form>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));margin-bottom:20px">
  <div class="kpi-card"><div class="kpi-icon">💰</div><div class="kpi-value"><?=$sym?><?=number_format($salesSum['revenue'],0)?></div><div class="kpi-label">Revenue</div><div class="kpi-change"><?=$salesSum['cnt']?> transactions</div></div>
  <div class="kpi-card"><div class="kpi-icon">📦</div><div class="kpi-value"><?=$sym?><?=number_format($cogs,0)?></div><div class="kpi-label">Cost of Goods</div></div>
  <div class="kpi-card"><div class="kpi-icon">📈</div><div class="kpi-value" style="color:<?=$grossProfit>=0?'var(--accent)':'var(--danger)'?>"><?=$sym?><?=number_format($grossProfit,0)?></div><div class="kpi-label">Gross Profit</div><div class="kpi-change <?=$grossMargin>=30?'up':'down'?>"><?=$grossMargin?>% margin</div></div>
  <div class="kpi-card"><div class="kpi-icon">💸</div><div class="kpi-value"><?=$sym?><?=number_format($totalExp,0)?></div><div class="kpi-label">Expenses</div></div>
  <div class="kpi-card"><div class="kpi-icon">🏆</div><div class="kpi-value" style="color:<?=$netProfit>=0?'var(--accent)':'var(--danger)'?>"><?=$sym?><?=number_format($netProfit,0)?></div><div class="kpi-label">Net Profit</div></div>
  <div class="kpi-card"><div class="kpi-icon">🥚</div><div class="kpi-value"><?=number_format($eggSum['sellable'])?></div><div class="kpi-label">Sellable Eggs</div><div class="kpi-change"><?=$eggWaste?>% waste</div></div>
  <div class="kpi-card"><div class="kpi-icon">🧮</div><div class="kpi-value"><?=$sym?><?=number_format($costPerEgg,2)?></div><div class="kpi-label">Cost / Egg</div></div>
  <div class="kpi-card"><div class="kpi-icon">⚠️</div><div class="kpi-value" style="color:var(--warning)"><?=$sym?><?=number_format($salesSum['outstanding'],0)?></div><div class="kpi-label">Outstanding</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;margin-bottom:18px">
  <div class="card">
    <div class="card-header"><h3>Daily Revenue Trend</h3></div>
    <div class="card-body">
      <?php if($dailySales): $maxRev=max(array_column($dailySales,'rev'))?:1; ?>
      <div style="overflow-x:auto"><div class="bar-chart" style="height:130px;min-width:<?=max(400,count($dailySales)*28)?>px">
        <?php foreach($dailySales as $d): $pct=round($d['rev']/$maxRev*100); ?>
        <div class="bar-item" title="<?=date('d M',strtotime($d['dt']))?> · <?=$sym?><?=number_format($d['rev'],2)?>">
          <div style="font-size:9px;color:var(--text-secondary)"><?=$d['rev']>=1000?round($d['rev']/1000).'k':number_format($d['rev'],0)?></div>
          <div class="bar" style="height:<?=max(4,$pct)?>px;background:<?=date('Y-m-d')===$d['dt']?'var(--primary)':'rgba(245,158,11,.5)'?>"></div>
          <div class="bar-label"><?=date('d',strtotime($d['dt']))?></div>
        </div>
        <?php endforeach; ?>
      </div></div>
      <?php else: ?><div class="empty-state" style="padding:30px"><div class="empty-icon">📊</div><p>No sales data</p></div><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Payment Breakdown</h3></div>
    <div class="card-body">
      <?php foreach($payBreak as $pm): $pct=round($pm['rev']/$totalPayRev*100); ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
          <span class="fw-600"><?=str_replace('_',' ',ucfirst($pm['payment_method']))?></span>
          <span class="fw-700"><?=$sym?><?=number_format($pm['rev'],0)?> <span class="text-muted">(<?=$pct?>%)</span></span>
        </div>
        <div style="background:var(--border);border-radius:4px;height:8px"><div style="width:<?=$pct?>%;background:var(--primary);border-radius:4px;height:8px"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if(!$payBreak): ?><p class="text-muted" style="text-align:center;padding:20px">No data</p><?php endif; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px">
  <div class="card">
    <div class="card-header"><h3>Top Products</h3><button onclick="exportCSV('topProdTable','products.csv')" class="btn btn-secondary btn-xs">CSV</button></div>
    <div class="table-wrap">
      <table id="topProdTable"><thead><tr><th>#</th><th>Product</th><th>Revenue</th><th>Qty</th><th>Profit</th></tr></thead>
      <tbody>
        <?php $n=1;foreach($topProducts as $p):$pct=round($p['total_rev']/$maxProdRev*100);?>
        <tr><td class="text-muted"><?=$n++?></td>
        <td><div class="fw-600"><?=clean($p['product_name']??'')?></div><div style="background:var(--border);border-radius:2px;height:4px;margin-top:4px"><div style="width:<?=$pct?>%;background:var(--primary);border-radius:2px;height:4px"></div></div></td>
        <td class="fw-700"><?=$sym?><?=number_format($p['total_rev'],2)?></td>
        <td class="text-muted"><?=number_format($p['total_qty'],1)?></td>
        <td class="<?=$p['profit']>=0?'text-success':'text-danger'?> fw-600"><?=$sym?><?=number_format($p['profit'],2)?></td></tr>
        <?php endforeach;?>
        <?php if(!$topProducts):?><tr><td colspan="5"><div class="empty-state" style="padding:20px"><p>No sales data</p></div></td></tr><?php endif;?>
      </tbody></table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Top Customers</h3></div>
    <div class="table-wrap"><table><thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Spent</th></tr></thead>
    <tbody><?php $n=1;foreach($topCusts as $c):?>
    <tr><td class="text-muted"><?=$n++?></td><td class="fw-600"><?=clean($c['name']??'')?></td><td><?=$c['cnt']?></td><td class="fw-700"><?=$sym?><?=number_format($c['total'],2)?></td></tr>
    <?php endforeach;?><?php if(!$topCusts):?><tr><td colspan="4"><div class="empty-state" style="padding:20px"><p>No data</p></div></td></tr><?php endif;?>
    </tbody></table></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
  <div class="card">
    <div class="card-header"><h3>Expense Breakdown</h3><a href="expenses.php" class="btn btn-secondary btn-xs">View All</a></div>
    <div class="card-body">
      <?php if($expByCat):foreach($expByCat as $e):$pct=round($e['total']/$maxExp*100);?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span class="fw-600"><?=clean($e['category']??'')?></span><span class="fw-700"><?=$sym?><?=number_format($e['total'],2)?></span></div>
        <div style="background:var(--border);border-radius:4px;height:6px"><div style="width:<?=$pct?>%;background:var(--danger);border-radius:4px;height:6px;opacity:.7"></div></div>
      </div>
      <?php endforeach;else:?><div class="empty-state" style="padding:20px"><div class="empty-icon">💸</div><p>No expenses this period</p></div><?php endif;?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Farm Performance</h3><a href="production.php" class="btn btn-secondary btn-xs">Egg Log</a></div>
    <div class="card-body">
      <?php $flockStmt=$pdo->prepare("SELECT f.name,f.current_count,COALESCE(SUM(pl.total_collected),0) eggs,COALESCE(SUM(fi.total_cost),0) feed_cost FROM flocks f LEFT JOIN production_logs pl ON f.id=pl.flock_id AND pl.log_date BETWEEN ? AND ? LEFT JOIN feed_issuance fi ON f.id=fi.flock_id AND fi.issue_date BETWEEN ? AND ? WHERE f.status='active' GROUP BY f.id ORDER BY eggs DESC");
      $flockStmt->execute([$from,$to,$from,$to]);
      $flockStats=$flockStmt->fetchAll();
      if($flockStats):foreach($flockStats as $fl):
        $days=max(1,(strtotime($to)-strtotime($from))/86400+1);
        $lr=$fl['current_count']>0&&$fl['eggs']>0?round($fl['eggs']/$fl['current_count']/$days*100,1):0;
        $cpe=$fl['eggs']>0?$sym.number_format($fl['feed_cost']/$fl['eggs'],2):'—';
      ?>
      <div style="padding:12px;background:var(--bg);border-radius:var(--r-md);margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div class="fw-600">🐔 <?=clean($fl['name'])?></div>
          <span class="badge badge-<?=$lr>=70?'success':($lr>=50?'warning':'danger')?>"><?=$lr?>% lay rate</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px;font-size:12px">
          <div><div class="text-muted">Birds</div><div class="fw-700"><?=number_format($fl['current_count'])?></div></div>
          <div><div class="text-muted">Eggs</div><div class="fw-700"><?=number_format($fl['eggs'])?></div></div>
          <div><div class="text-muted">Cost/Egg</div><div class="fw-700"><?=$cpe?></div></div>
        </div>
      </div>
      <?php endforeach;else:?><div class="empty-state" style="padding:20px"><div class="empty-icon">🐔</div><p>No active flocks. <a href="flocks.php">Add flock →</a></p></div><?php endif;?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php';?>
