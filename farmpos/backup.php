<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Backup & Export');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';

if(isset($_GET['download'])){
    $tables=['admin','categories','products','product_variants','suppliers','customers','sales','sale_items','held_sales','purchases','purchase_items','inventory_adjustments','expenses','flocks','production_logs','feed_issuance','mortality_records','returns','audit_logs'];
    $sql="-- Baffa Precision Agri-Tech Database Backup\n-- Generated: ".date('Y-m-d H:i:s')."\n-- Version: ".VERSION."\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach($tables as $t){
        try{
            $cr=$pdo->query("SHOW CREATE TABLE `$t`")->fetch();
            $sql.="DROP TABLE IF EXISTS `$t`;\n".$cr['Create Table'].";\n\n";
            $rows=$pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_NUM);
            if($rows){
                $cols=$pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll();
                $colNames=implode(',',array_map(fn($c)=>"`{$c['Field']}`",$cols));
                $sql.="INSERT INTO `$t` ($colNames) VALUES\n";
                $vals=[];foreach($rows as $r){$vals[]='('.implode(',',array_map(fn($v)=>$v===null?'NULL':$pdo->quote($v),$r)).')';}
                $sql.=implode(",\n",$vals).";\n\n";
            }
        }catch(Exception $e){}
    }
    $sql.="SET FOREIGN_KEY_CHECKS=1;\n";
    $fname='farmpos_backup_'.date('Ymd_His').'.sql';
    header('Content-Type: application/octet-stream');header("Content-Disposition: attachment; filename=$fname");header('Content-Length: '.strlen($sql));
    logAction($pdo,'BACKUP','SQL backup downloaded');
    echo $sql;exit;
}

// CSV exports
if(isset($_GET['export'])){
    $type=$_GET['export'];
    $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');
    $csv='';$fname='export.csv';
    if($type==='sales'){
        $rows=$pdo->prepare("SELECT s.invoice_no,DATE(s.created_at) date,c.name customer,s.subtotal,s.discount_amount,s.tax_amount,s.total,s.paid_amount,s.balance_due,s.payment_method,s.status FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at DESC");
        $rows->execute([$from,$to]);$rows=$rows->fetchAll();
        $csv="Invoice,Date,Customer,Subtotal,Discount,Tax,Total,Paid,Balance,Method,Status\n";
        foreach($rows as $r) $csv.=implode(',',array_map(fn($v)=>'"'.$v.'"',$r))."\n";
        $fname='sales_'.$from.'_'.$to.'.csv';
    }elseif($type==='inventory'){
        $rows=$pdo->query("SELECT p.name,c.name category,p.unit,p.price_retail,p.price_wholesale,p.cost_price,p.stock_qty,p.reorder_level FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.name")->fetchAll();
        $csv="Product,Category,Unit,Retail Price,Wholesale,Cost,Stock,Reorder\n";
        foreach($rows as $r) $csv.=implode(',',array_map(fn($v)=>'"'.$v.'"',$r))."\n";
        $fname='inventory_'.date('Ymd').'.csv';
    }elseif($type==='production'){
        $rows=$pdo->query("SELECT f.name flock,pl.log_date,pl.eggs_grade_a,pl.eggs_grade_b,pl.eggs_cracked,pl.eggs_dirty,pl.total_collected,pl.sellable FROM production_logs pl JOIN flocks f ON pl.flock_id=f.id ORDER BY pl.log_date DESC")->fetchAll();
        $csv="Flock,Date,Grade A,Grade B,Cracked,Dirty,Total,Sellable\n";
        foreach($rows as $r) $csv.=implode(',',array_map(fn($v)=>'"'.$v.'"',$r))."\n";
        $fname='production_'.date('Ymd').'.csv';
    }
    if($csv){
        header('Content-Type: text/csv');header("Content-Disposition: attachment; filename=$fname");
        echo $csv;exit;
    }
}

$counts=[
    'Sales'=>$pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn(),
    'Products'=>$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(),
    'Customers'=>$pdo->query("SELECT COUNT(*) FROM customers WHERE id>1")->fetchColumn(),
    'Flocks'=>$pdo->query("SELECT COUNT(*) FROM flocks")->fetchColumn(),
    'Egg Logs'=>$pdo->query("SELECT COUNT(*) FROM production_logs")->fetchColumn(),
    'Expenses'=>$pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn(),
];
include 'includes/header.php';
?>
<div class="page-header"><div><h2>Backup & Export</h2><p>Download your data for safekeeping</p></div></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div>
<div class="card mb-2">
  <div class="card-header"><h3>💾 Full Database Backup</h3></div>
  <div class="card-body">
    <p class="text-muted" style="font-size:13.5px;margin-bottom:16px">Downloads a complete SQL file that can be imported into phpMyAdmin to restore all data — products, sales, farm records, settings.</p>
    <a href="backup.php?download=1" class="btn btn-primary w-100" style="justify-content:center">⬇️ Download SQL Backup</a>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3>📊 CSV Exports</h3></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
    <div style="border:1px solid var(--border);border-radius:var(--r-md);padding:12px">
      <div class="fw-600" style="margin-bottom:8px">Sales Export</div>
      <form method="GET" action="backup.php" style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="export" value="sales">
        <input type="date" name="from" class="form-control" value="<?=date('Y-m-01')?>" style="flex:1;min-width:120px">
        <input type="date" name="to" class="form-control" value="<?=date('Y-m-d')?>" style="flex:1;min-width:120px">
        <button type="submit" class="btn btn-success btn-sm">⬇️ Export</button>
      </form>
    </div>
    <a href="backup.php?export=inventory" class="btn btn-secondary btn-sm">⬇️ Export Inventory / Stock List</a>
    <a href="backup.php?export=production" class="btn btn-secondary btn-sm">⬇️ Export Egg Production Log</a>
  </div>
</div>
</div>
<div>
<div class="card mb-2">
  <div class="card-header"><h3>📈 Database Statistics</h3></div>
  <div class="card-body">
    <?php foreach($counts as $k=>$v):?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px">
      <span class="text-muted"><?=$k?></span><span class="fw-700"><?=number_format($v)?> records</span>
    </div>
    <?php endforeach;?>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3>📋 Restore Instructions</h3></div>
  <div class="card-body" style="font-size:13px;color:var(--text-secondary);line-height:1.8">
    <div style="counter-reset:step">
      <?php foreach(['Open phpMyAdmin (http://localhost/phpmyadmin)','Select the <strong>farmpos</strong> database','Click the <strong>Import</strong> tab','Choose your downloaded .sql backup file','Click <strong>Go</strong> — all data will be restored','Visit your site to verify everything is working'] as $i=>$s):?>
      <div style="display:flex;gap:10px;margin-bottom:8px"><span style="width:22px;height:22px;border-radius:50%;background:var(--primary);color:#1c1917;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?=$i+1?></span><span><?=$s?></span></div>
      <?php endforeach;?>
    </div>
  </div>
</div>
</div>
</div>
<?php include 'includes/footer.php';?>
