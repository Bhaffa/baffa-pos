<?php
require_once 'config.php';requireLogin();define('PAGE_TITLE','Farm Expenses');
$settings=getSettings($pdo);$sym=$settings['currency_symbol']??'₦';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['action']??'';
    if($act==='add'){
        $cat=trim($_POST['category']??'');$desc=trim($_POST['description']??'');$amount=floatval($_POST['amount']??0);$paidTo=trim($_POST['paid_to']??'');$date=$_POST['expense_date']??date('Y-m-d');
        if(!$cat||$amount<=0){redirect('expenses.php','Category and amount required.','error');}
        $pdo->prepare("INSERT INTO expenses(category,description,amount,paid_to,expense_date)VALUES(?,?,?,?,?)")->execute([$cat,$desc,$amount,$paidTo,$date]);
        logAction($pdo,'EXPENSE',"Cat:$cat Amount:$amount");redirect('expenses.php','Expense recorded!');
    }
    if($act==='delete'){$pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([intval($_POST['id'])]);redirect('expenses.php','Deleted.');}
}
$from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');
$expStmt=$pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC");$expStmt->execute([$from,$to]);$expenses=$expStmt->fetchAll();
$total=array_sum(array_column($expenses,'amount'));
$byCat=[];foreach($expenses as $e){$byCat[$e['category']]=($byCat[$e['category']]??0)+$e['amount'];}arsort($byCat);
include 'includes/header.php';
?>
<div class="page-header">
  <div><h2>💸 Farm Expenses</h2><p>Period: <?=fmtDate($from)?> — <?=fmtDate($to)?></p></div>
  <button onclick="openModal('expModal')" class="btn btn-primary">+ Add Expense</button>
</div>
<div style="display:grid;grid-template-columns:1fr 280px;gap:18px">
<div>
  <div class="card mb-2"><div class="card-body" style="padding:12px 18px"><form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?=$from?>" style="width:140px"></div>
    <div><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?=$to?>" style="width:140px"></div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <button onclick="exportCSV('expTable','expenses.csv')" type="button" class="btn btn-secondary btn-sm">📥 CSV</button>
  </form></div></div>
  <div class="card"><div class="card-header"><h3>Expenses — <?=$sym?><?=number_format($total,2)?> total</h3></div>
  <div class="table-wrap"><table id="expTable"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Paid To</th><th data-sort="4">Amount</th><th></th></tr></thead>
  <tbody>
    <?php foreach($expenses as $e):?>
    <tr><td><?=date('d M Y',strtotime($e['expense_date']))?></td>
    <td><span class="badge badge-secondary"><?=clean($e['category']??'')?></span></td>
    <td><?=clean($e['description']??'—')?></td>
    <td class="text-muted"><?=clean($e['paid_to']??'—')?></td>
    <td class="fw-700"><?=$sym?><?=number_format($e['amount'],2)?></td>
    <td><form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$e['id']?>"><button class="btn btn-danger btn-xs" data-confirm="Delete expense?">🗑</button></form></td></tr>
    <?php endforeach;?>
    <?php if(!$expenses):?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon">💸</div><h3>No expenses recorded</h3></div></td></tr><?php endif;?>
  </tbody></table></div></div>
</div>
<div>
  <div class="kpi-card mb-2"><div class="kpi-icon">💸</div><div class="kpi-value"><?=$sym?><?=number_format($total,0)?></div><div class="kpi-label">Total Expenses</div></div>
  <div class="card"><div class="card-header"><h3>By Category</h3></div>
  <div style="padding:16px">
    <?php foreach($byCat as $cat=>$amt):$pct=$total>0?round($amt/$total*100):0;?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
        <span class="fw-600"><?=clean($cat)?></span><span class="fw-700"><?=$sym?><?=number_format($amt,0)?> <span class="text-muted">(<?=$pct?>%)</span></span>
      </div>
      <div style="background:var(--border);border-radius:var(--r-pill);height:6px"><div style="background:var(--primary);border-radius:var(--r-pill);height:6px;width:<?=$pct?>%"></div></div>
    </div>
    <?php endforeach;?>
    <?php if(!$byCat):?><div style="text-align:center;color:var(--text-secondary);padding:20px;font-size:13px">No data</div><?php endif;?>
  </div></div>
</div>
</div>

<div class="modal-overlay" id="expModal"><div class="modal"><div class="modal-header"><h3>Add Expense</h3><button class="btn-close" onclick="closeModal('expModal')">✕</button></div>
<div class="modal-body"><form method="POST" action="expenses.php">
  <input type="hidden" name="action" value="add">
  <div class="form-row-2">
    <div class="form-group"><label class="form-label">Category *</label>
      <input type="text" name="category" class="form-control" required list="catList" placeholder="e.g. Feed, Vet, Utilities">
      <datalist id="catList"><option value="Feed"><option value="Medication/Vet"><option value="Labor/Wages"><option value="Utilities (Power/Water)"><option value="Transport"><option value="Equipment"><option value="Packaging"><option value="Repairs"><option value="Other"></datalist>
    </div>
    <div class="form-group"><label class="form-label">Amount (<?=$sym?>) *</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
  </div>
  <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-control" placeholder="Details about this expense"></div>
  <div class="form-row-2">
    <div class="form-group"><label class="form-label">Paid To</label><input type="text" name="paid_to" class="form-control" placeholder="Vendor / Person"></div>
    <div class="form-group"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('expModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Expense</button></div>
</form></div></div></div>
<?php include 'includes/footer.php';?>
