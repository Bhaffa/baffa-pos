<?php
// pos.php — NO ob_start() here; config.php already starts one.
// In AJAX handlers we nuke ALL buffers before sending JSON.
require_once 'config.php';
requireLogin();
define('PAGE_TITLE', 'POS Checkout');

$settings = getSettings($pdo);
$currSym  = $settings['currency_symbol'] ?? '₦';
$taxRate  = floatval($settings['tax_rate'] ?? 0);

// ═══════════════════════════════════════════════════════════
// AJAX — POST
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear EVERY output-buffer level so nothing contaminates JSON
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');

    $act = $_POST['action'] ?? '';

    // ── Process Sale ──────────────────────────────────────
    if ($act === 'process_sale') {
        try {
            $items = json_decode($_POST['items'] ?? '[]', true);
            if (!is_array($items) || empty($items)) {
                echo json_encode(['ok'=>false,'msg'=>'Cart is empty or invalid item data.']);
                exit;
            }

            $customerId = intval($_POST['customer_id'] ?? 0) ?: null;
            $discountIn = floatval($_POST['discount']     ?? 0);
            $taxAmt     = floatval($_POST['tax_amount']   ?? 0);
            $total      = floatval($_POST['total']        ?? 0);
            $paid       = floatval($_POST['paid_amount']  ?? 0);
            $method     = $_POST['payment_method'] ?? 'cash';
            $notes      = trim($_POST['notes']     ?? '');

            $subtotal   = 0;
            foreach ($items as $it) { $subtotal += floatval($it['total'] ?? 0); }

            $change     = max(0, $paid - $total);
            $balanceDue = max(0, $total - $paid);
            $status     = $balanceDue > 0 ? 'partial' : 'completed';
            $invoiceNo  = generateInvoiceNo($pdo);

            $pdo->beginTransaction();

            $pdo->prepare(
                "INSERT INTO sales
                    (invoice_no,customer_id,subtotal,discount_amount,
                     tax_amount,total,paid_amount,change_amount,
                     balance_due,payment_method,notes,status)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $invoiceNo,$customerId,$subtotal,$discountIn,
                $taxAmt,$total,$paid,$change,
                $balanceDue,$method,$notes,$status
            ]);
            $saleId = (int)$pdo->lastInsertId();

            foreach ($items as $it) {
                $pid       = intval($it['id']    ?? 0) ?: null;
                $prodName  = strval($it['name']  ?? '');
                $unit      = strval($it['unit']  ?? 'piece');
                $qty       = floatval($it['qty'] ?? 0);
                $unitPrice = floatval($it['price'] ?? 0);
                $costPrice = floatval($it['cost']  ?? 0);
                $lineTotal = floatval($it['total'] ?? ($qty * $unitPrice));

                $pdo->prepare(
                    "INSERT INTO sale_items
                        (sale_id,product_id,product_name,unit,qty,unit_price,cost_price,total)
                     VALUES(?,?,?,?,?,?,?,?)"
                )->execute([
                    $saleId,$pid,$prodName,$unit,$qty,$unitPrice,$costPrice,$lineTotal
                ]);

                if ($pid) deductStock($pdo, $pid, $qty);
            }

            if ($customerId && $customerId > 1) {
                $pts = intval($total / 100);
                if ($pts > 0)
                    $pdo->prepare("UPDATE customers SET loyalty_points=loyalty_points+? WHERE id=?")
                        ->execute([$pts,$customerId]);
            }
            if ($balanceDue > 0 && $customerId && $customerId > 1)
                $pdo->prepare("UPDATE customers SET balance=balance+? WHERE id=?")
                    ->execute([$balanceDue,$customerId]);

            $pdo->commit();
            logAction($pdo,'SALE',"Invoice:$invoiceNo Total:$total Method:$method");

            $st = $pdo->prepare(
                "SELECT s.*,c.name cust_name,c.phone cust_phone
                   FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
                  WHERE s.id=?"
            );
            $st->execute([$saleId]);
            $saleData = $st->fetch();

            echo json_encode(['ok'=>true,'invoice_no'=>$invoiceNo,'sale_id'=>$saleId,'change'=>$change,'sale'=>$saleData]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
        }
        exit;
    }

    if ($act === 'hold_cart') {
        try {
            $label = trim($_POST['label'] ?? ('Hold #'.date('H:i')));
            $cid   = intval($_POST['customer_id'] ?? 0) ?: null;
            $pdo->prepare("INSERT INTO held_sales(label,cart_data,customer_id)VALUES(?,?,?)")
                ->execute([$label,$_POST['items']??'[]',$cid]);
            echo json_encode(['ok'=>true,'msg'=>'Cart saved as "'.htmlspecialchars($label).'"']);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    if ($act === 'delete_held') {
        $pdo->prepare("DELETE FROM held_sales WHERE id=?")->execute([intval($_POST['id']??0)]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']);
    exit;
}

// ═══════════════════════════════════════════════════════════
// AJAX — GET
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');

    if ($_GET['action'] === 'get_held') {
        echo json_encode($pdo->query("SELECT * FROM held_sales ORDER BY created_at DESC LIMIT 20")->fetchAll());
        exit;
    }
    echo json_encode([]);
    exit;
}

// ═══════════════════════════════════════════════════════════
// PAGE RENDER
// ═══════════════════════════════════════════════════════════
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order,name")->fetchAll();
$customers  = $pdo->query("SELECT id,name,type,loyalty_points,balance FROM customers ORDER BY name")->fetchAll();
$products   = $pdo->query(
    "SELECT p.*,c.name cat_name,c.icon cat_icon
       FROM products p LEFT JOIN categories c ON p.category_id=c.id
      WHERE p.is_active=1 ORDER BY p.name LIMIT 200"
)->fetchAll();
$todayStats = getTodayStats($pdo);
$bizName    = $settings['business_name'] ?? 'Baffa Precision Agri-Tech';
$bizAddr    = addslashes(clean($settings['business_address'] ?? ''));
$bizFooter  = addslashes(clean($settings['receipt_footer']  ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS — <?= clean($bizName) ?></title>
<link rel="icon" type="image/png" href="assets/logo.png">
<link rel="apple-touch-icon" href="assets/logo.png">
<link rel="stylesheet" href="assets/style.css">
<style>
body{overflow:hidden}
.pos-wrap{display:flex;height:100vh}
.pos-sidebar{width:220px;background:var(--sidebar);display:flex;flex-direction:column;flex-shrink:0}
.pos-sidebar .logo{padding:16px;border-bottom:1px solid rgba(255,255,255,.08)}
.pos-sidebar .logo span{font-size:20px;font-weight:800;color:#fff}
.pos-sidebar .logo small{display:block;font-size:11px;color:rgba(255,255,255,.35);margin-top:2px}
.pos-nav{flex:1;padding:8px;overflow-y:auto}
.pos-nav a{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:var(--r-md);color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:500;margin-bottom:2px}
.pos-nav a:hover{background:rgba(255,255,255,.07);color:#fff}
.pos-nav a.active{background:var(--sidebar-active);color:var(--text-sidebar-active)}
.pos-nav .nav-sect{font-size:9.5px;font-weight:700;color:rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:1px;padding:10px 12px 4px}
.pos-stats{padding:10px 8px;border-top:1px solid rgba(255,255,255,.08)}
.stat-pill{background:rgba(255,255,255,.06);border-radius:var(--r-md);padding:8px 12px;margin-bottom:6px}
.stat-pill .sp-val{font-size:16px;font-weight:800;color:var(--accent)}
.stat-pill .sp-lbl{font-size:10px;color:rgba(255,255,255,.35)}
.user-bar{padding:10px 8px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:8px}
.user-av{width:30px;height:30px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.user-nm{font-size:12px;color:#fff;font-weight:600}
.user-out{margin-left:auto;color:rgba(255,255,255,.3);text-decoration:none;font-size:15px}
.user-out:hover{color:var(--danger)}
.kb-hint{font-size:9.5px;color:rgba(255,255,255,.2);padding:0 12px 8px}
</style>
</head>
<body>
<div class="pos-wrap">

<!-- SIDEBAR -->
<div class="pos-sidebar">
  <div class="logo">
    <span>🌿 <?= clean($bizName) ?></span>
    <small><?= date('D, d M Y') ?></small>
  </div>
  <nav class="pos-nav">
    <a href="pos.php" class="active">🛒 POS Checkout</a>
    <div class="nav-sect">Sales</div>
    <a href="sales.php">📑 Sales History</a>
    <a href="customers.php">👥 Customers</a>
    <a href="returns.php">↩️ Returns</a>
    <div class="nav-sect">Inventory</div>
    <a href="products.php">📦 Products</a>
    <a href="inventory.php">🗃️ Stock</a>
    <a href="purchases.php">🚚 Purchases</a>
    <div class="nav-sect">Farm</div>
    <a href="flocks.php">🐔 Flocks</a>
    <a href="production.php">🥚 Egg Log</a>
    <a href="feed.php">🌾 Feed</a>
    <div class="nav-sect">System</div>
    <a href="reports.php">📊 Reports</a>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="settings.php">⚙️ Settings</a>
  </nav>
  <div class="pos-stats">
    <div class="stat-pill">
      <div class="sp-val"><?= $currSym ?><?= number_format($todayStats['revenue'],0) ?></div>
      <div class="sp-lbl">Today's Revenue</div>
    </div>
    <div class="stat-pill">
      <div class="sp-val"><?= $todayStats['transactions'] ?></div>
      <div class="sp-lbl">Transactions Today</div>
    </div>
  </div>
  <div class="kb-hint">Alt+P: search &nbsp;·&nbsp; Alt+C: checkout</div>
  <div class="user-bar">
    <div class="user-av"><?= strtoupper(substr($_SESSION['pos_name']??'A',0,1)) ?></div>
    <div class="user-nm"><?= clean($_SESSION['pos_name']??'Admin') ?></div>
    <a href="logout.php" class="user-out" title="Logout">⏏</a>
  </div>
</div>

<!-- MAIN -->
<div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
  <div class="pos-search-bar">
    <input type="text" id="pos-search" placeholder="🔍  Search products by name, SKU or barcode... (Alt+P)" autocomplete="off">
    <div class="pos-cats" style="flex:none;padding:0;border:none;background:transparent">
      <button class="cat-btn active" data-cat="0">All</button>
      <?php foreach($categories as $cat): ?>
      <button class="cat-btn" data-cat="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= clean($cat['name']??'') ?></button>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-secondary btn-sm btn-hold" onclick="holdCart()" title="Alt+H">📌 Hold</button>
    <button class="btn btn-outline btn-sm" onclick="loadHeld()">📋 Recall</button>
  </div>

  <div style="flex:1;display:grid;grid-template-columns:1fr 390px;overflow:hidden">

    <!-- Product Grid -->
    <div class="product-grid" id="productGrid">
      <?php foreach($products as $p):
        $outOfStock = $p['track_stock'] && $p['stock_qty'] <= 0;
        $lowStock   = $p['track_stock'] && $p['stock_qty'] <= $p['reorder_level'] && $p['stock_qty'] > 0;
      ?>
      <div class="product-btn <?= $outOfStock?'out-of-stock':'' ?>"
           data-id="<?= (int)$p['id'] ?>"
           data-name="<?= htmlspecialchars($p['name']??'',ENT_QUOTES) ?>"
           data-price="<?= floatval($p['price_retail']) ?>"
           data-price-w="<?= floatval($p['price_wholesale']) ?>"
           data-cost="<?= floatval($p['cost_price']) ?>"
           data-unit="<?= htmlspecialchars($p['unit']??'piece',ENT_QUOTES) ?>"
           data-stock="<?= floatval($p['stock_qty']) ?>"
           data-cat="<?= (int)$p['category_id'] ?>"
           data-track="<?= $p['track_stock']?'1':'0' ?>"
           onclick="addToCart(this)">
        <?php if($lowStock): ?><div class="stock-badge">Low</div><?php endif; ?>
        <div class="prod-icon"><?= $p['cat_icon']??'📦' ?></div>
        <div class="prod-name"><?= clean($p['name']??'') ?></div>
        <div class="prod-price"><?= $currSym ?><?= number_format(floatval($p['price_retail']),2) ?></div>
        <div class="prod-stock"><?= $p['track_stock']?($outOfStock?'Out of stock':'Qty: '.number_format($p['stock_qty'])):'Unlimited' ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Cart -->
    <div class="pos-cart">
      <div class="cart-header">
        <h3>🛒 Cart <span id="cartCount" class="badge badge-primary" style="margin-left:6px">0</span></h3>
        <button class="btn btn-danger btn-sm" onclick="clearCart()">Clear</button>
      </div>

      <div class="cart-customer">
        <span style="font-size:14px;flex-shrink:0">👤</span>
        <select id="customerSelect" onchange="onCustomerChange()">
          <?php foreach($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
                  data-type="<?= htmlspecialchars($c['type']??'retail',ENT_QUOTES) ?>"
                  data-balance="<?= floatval($c['balance']) ?>"
                  data-points="<?= intval($c['loyalty_points']) ?>">
            <?= clean($c['name']??'') ?><?= ($c['type']??'')==='wholesale'?' [WS]':'' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <a href="customers.php?action=add" class="btn btn-secondary btn-xs" title="New Customer">+</a>
      </div>

      <div class="cart-items" id="cartItems">
        <div class="cart-empty">
          <div class="empty-icon">🛒</div>
          <div style="font-size:13px;font-weight:600">Cart is empty</div>
          <div style="font-size:12px;color:var(--text-secondary)">Click products to add</div>
        </div>
      </div>

      <div class="cart-totals">
        <div class="totals-row"><span class="text-muted">Subtotal</span><span id="subtotalDisplay"><?= $currSym ?>0.00</span></div>
        <div class="totals-row">
          <span class="text-muted">Discount</span>
          <div style="display:flex;align-items:center;gap:6px">
            <input type="number" id="discountInput" class="discount-input" placeholder="0" min="0" oninput="recalculate()">
            <select id="discountType" class="discount-input" style="width:48px;padding:4px" onchange="recalculate()">
              <option value="fixed"><?= $currSym ?></option><option value="pct">%</option>
            </select>
          </div>
        </div>
        <?php if($taxRate>0): ?>
        <div class="totals-row"><span class="text-muted">Tax (<?= $taxRate ?>%)</span><span id="taxDisplay"><?= $currSym ?>0.00</span></div>
        <?php endif; ?>
        <div class="totals-row grand-total"><span>Total</span><span id="totalDisplay"><?= $currSym ?>0.00</span></div>
      </div>

      <div class="cart-checkout">
        <div style="font-size:11px;color:var(--text-secondary);font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">Payment Method</div>
        <div class="payment-methods">
          <button class="pay-btn active" data-method="cash">💵 Cash</button>
          <button class="pay-btn" data-method="card">💳 Card</button>
          <button class="pay-btn" data-method="mobile_money">📱 MoMo</button>
          <button class="pay-btn" data-method="credit">📒 Credit</button>
        </div>
        <div id="cashRow" style="display:flex;align-items:center;gap:8px;margin-top:6px">
          <span style="font-size:12.5px;color:var(--text-secondary);flex-shrink:0">Tendered</span>
          <input type="number" id="tenderedInput" class="form-control" placeholder="0.00" min="0" step="0.01" oninput="calcChange()" style="padding:7px 12px;text-align:center">
          <div style="text-align:right;min-width:80px">
            <div style="font-size:10px;color:var(--text-secondary)">Change</div>
            <div id="changeDisplay" style="font-size:15px;font-weight:800;color:var(--accent)"><?= $currSym ?>0.00</div>
          </div>
        </div>
        <input type="text" id="saleNotes" class="form-control" placeholder="Notes (optional)" style="font-size:12.5px;padding:8px 12px">
        <button class="btn btn-primary btn-lg w-100 btn-checkout" id="chargeBtn" onclick="processCheckout()" style="justify-content:center;font-size:16px">
          ✓ Charge <span id="chargeTotalBtn"><?= $currSym ?>0.00</span>
        </button>
      </div>
    </div>
  </div>
</div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal-overlay" id="receiptModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header"><h3>✅ Sale Complete</h3><button class="btn-close" onclick="newSale()">✕</button></div>
    <div class="modal-body" style="padding:16px"><div class="receipt" id="receiptContent"></div></div>
    <div style="padding:16px;display:flex;gap:8px;border-top:1px solid var(--border)">
      <button class="btn btn-secondary flex-1" onclick="doPrintReceipt()">🖨️ Print</button>
      <button class="btn btn-primary flex-1" onclick="newSale()">🛒 New Sale</button>
    </div>
  </div>
</div>

<!-- HELD MODAL -->
<div class="modal-overlay" id="heldModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><h3>📌 Held Carts</h3><button class="btn-close" onclick="closeModal('heldModal')">✕</button></div>
    <div class="modal-body" id="heldList" style="padding:12px;max-height:400px;overflow-y:auto"></div>
  </div>
</div>

<script>
const CURRENCY   = '<?= $currSym ?>';
const TAX_RATE   = <?= $taxRate ?>;
const BIZ_NAME   = '<?= addslashes(clean($bizName)) ?>';
const BIZ_ADDR   = '<?= $bizAddr ?>';
const BIZ_FOOTER = '<?= $bizFooter ?>';
let cart = [], payMethod = 'cash';

// MODALS
function openModal(id)  { const m=document.getElementById(id); if(m) m.classList.add('open'); }
function closeModal(id) { const m=document.getElementById(id); if(m) m.classList.remove('open'); }
document.addEventListener('click', e=>{ if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>m.classList.remove('open')); });

// CART
function addToCart(el) {
  const id    = +el.dataset.id;
  const name  = el.dataset.name;
  const ctype = document.getElementById('customerSelect').selectedOptions[0].dataset.type;
  const price = ctype==='wholesale' ? (+el.dataset.priceW||+el.dataset.price) : +el.dataset.price;
  const cost  = +el.dataset.cost||0;
  const unit  = el.dataset.unit||'piece';
  const stock = +el.dataset.stock;
  const track = el.dataset.track==='1';
  const ex    = cart.find(i=>i.id===id);
  if (ex) {
    if (track && ex.qty>=stock) { showAlert('Not enough stock!'); return; }
    ex.qty++; ex.total=+(ex.qty*ex.price).toFixed(2);
  } else {
    cart.push({id,name,price,cost,unit,qty:1,total:+price.toFixed(2),stock,track});
  }
  renderCart();
  el.style.transform='scale(.95)'; setTimeout(()=>el.style.transform='',120);
}

function renderCart() {
  const el=document.getElementById('cartItems');
  document.getElementById('cartCount').textContent=cart.reduce((s,i)=>s+i.qty,0);
  if (!cart.length) {
    el.innerHTML=`<div class="cart-empty"><div class="empty-icon">🛒</div><div style="font-size:13px;font-weight:600">Cart is empty</div><div style="font-size:12px;color:var(--text-secondary)">Click products to add</div></div>`;
    recalculate(); return;
  }
  el.innerHTML=cart.map((item,idx)=>`
    <div class="cart-item">
      <div><div class="cart-item-name">${item.name}</div><div class="cart-item-price">${CURRENCY}${item.price.toFixed(2)} / ${item.unit}</div></div>
      <div class="qty-control">
        <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
        <input class="qty-input" type="number" value="${item.qty}" min="1" onchange="setQty(${idx},this.value)">
        <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
      </div>
      <div class="cart-item-total">${CURRENCY}${item.total.toFixed(2)}</div>
      <div class="cart-remove" onclick="removeItem(${idx})">✕</div>
    </div>`).join('');
  recalculate();
}

function changeQty(idx,delta) {
  const item=cart[idx], nq=item.qty+delta;
  if (nq<1) { removeItem(idx); return; }
  if (item.track&&nq>item.stock) { showAlert('Not enough stock!'); return; }
  item.qty=nq; item.total=+(nq*item.price).toFixed(2); renderCart();
}
function setQty(idx,val) {
  const item=cart[idx], q=parseFloat(val)||1;
  if (item.track&&q>item.stock) { showAlert('Not enough stock!'); return; }
  item.qty=q; item.total=+(q*item.price).toFixed(2); renderCart();
}
function removeItem(idx) { cart.splice(idx,1); renderCart(); }
function clearCart() { if(cart.length&&!confirm('Clear cart?')) return; cart=[]; renderCart(); }

function recalculate() {
  const sub=cart.reduce((s,i)=>s+i.total,0);
  const dv=parseFloat(document.getElementById('discountInput').value)||0;
  const dt=document.getElementById('discountType').value;
  const disc=dt==='pct'?sub*dv/100:dv;
  const aft=Math.max(0,sub-disc);
  const tax=TAX_RATE>0?aft*TAX_RATE/100:0;
  const tot=aft+tax;
  document.getElementById('subtotalDisplay').textContent=CURRENCY+sub.toFixed(2);
  const te=document.getElementById('taxDisplay'); if(te) te.textContent=CURRENCY+tax.toFixed(2);
  document.getElementById('totalDisplay').textContent=CURRENCY+tot.toFixed(2);
  document.getElementById('chargeTotalBtn').textContent=CURRENCY+tot.toFixed(2);
  calcChange();
}
function calcChange() {
  const tot=parseFloat(document.getElementById('totalDisplay').textContent.replace(/[^0-9.]/g,''))||0;
  const ten=parseFloat(document.getElementById('tenderedInput').value)||0;
  document.getElementById('changeDisplay').textContent=CURRENCY+Math.max(0,ten-tot).toFixed(2);
}

document.querySelectorAll('.pay-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.pay-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); payMethod=btn.dataset.method;
    document.getElementById('cashRow').style.display=payMethod==='cash'?'flex':'none';
  });
});

function onCustomerChange() {
  const opt=document.getElementById('customerSelect').selectedOptions[0];
  const type=opt.dataset.type;
  cart.forEach(item=>{
    const btn=document.querySelector(`[data-id="${item.id}"]`);
    if(btn){item.price=type==='wholesale'?(+btn.dataset.priceW||+btn.dataset.price):+btn.dataset.price;item.total=+(item.qty*item.price).toFixed(2);}
  });
  renderCart();
  const bal=parseFloat(opt.dataset.balance)||0;
  if(bal>0) showAlert(`⚠️ Customer outstanding balance: ${CURRENCY}${bal.toFixed(2)}`,'warning');
}

// ═══ CHECKOUT — XMLHttpRequest for max XAMPP compatibility ════════════════
function processCheckout() {
  if (!cart.length) { showAlert('Cart is empty'); return; }

  const sub   = cart.reduce((s,i)=>s+i.total,0);
  const dv    = parseFloat(document.getElementById('discountInput').value)||0;
  const dt    = document.getElementById('discountType').value;
  const disc  = dt==='pct'?sub*dv/100:dv;
  const aft   = Math.max(0,sub-disc);
  const tax   = TAX_RATE>0?aft*TAX_RATE/100:0;
  const total = +(aft+tax).toFixed(2);
  const ten   = parseFloat(document.getElementById('tenderedInput').value)||total;
  const cid   = document.getElementById('customerSelect').value;
  const notes = document.getElementById('saleNotes').value;

  // Snapshot: explicit keys only (no stock/track noise)
  const snap = cart.map(i=>({id:i.id, name:i.name, price:i.price, cost:i.cost, unit:i.unit, qty:i.qty, total:i.total}));

  const btn = document.getElementById('chargeBtn');
  btn.disabled=true; btn.innerHTML='⏳ Processing...';

  const body = 'action=process_sale'
    + '&items='          + encodeURIComponent(JSON.stringify(snap))
    + '&customer_id='   + encodeURIComponent(cid)
    + '&discount='      + encodeURIComponent(dv)
    + '&tax_amount='    + encodeURIComponent(tax.toFixed(2))
    + '&total='         + encodeURIComponent(total)
    + '&paid_amount='   + encodeURIComponent(payMethod==='credit'?0:ten)
    + '&payment_method='+ encodeURIComponent(payMethod)
    + '&notes='         + encodeURIComponent(notes);

  const xhr = new XMLHttpRequest();
  xhr.open('POST','pos.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

  xhr.onload = function() {
    btn.disabled=false;
    btn.innerHTML='✓ Charge <span id="chargeTotalBtn">'+CURRENCY+total.toFixed(2)+'</span>';
    if (xhr.status!==200) { showAlert('Server HTTP '+xhr.status,'error'); return; }
    let data;
    try { data=JSON.parse(xhr.responseText); }
    catch(e) {
      console.error('RAW RESPONSE:', xhr.responseText);
      showAlert('Server response error — see browser console (F12) for details.','error');
      return;
    }
    if (data.ok) {
      const change = payMethod==='cash'?Math.max(0,ten-total):0;
      showReceipt(data,total,change,snap);
      cart=[]; renderCart();
      document.getElementById('discountInput').value='';
      document.getElementById('tenderedInput').value='';
      document.getElementById('saleNotes').value='';
      recalculate();
    } else if (data.session_expired) {
      showAlert('Session expired — redirecting...','error');
      setTimeout(()=>window.location.href='login.php',2000);
    } else {
      showAlert('Error: '+(data.msg||'Unknown error'),'error');
    }
  };
  xhr.onerror = function() {
    btn.disabled=false;
    btn.innerHTML='✓ Charge <span id="chargeTotalBtn">'+CURRENCY+total.toFixed(2)+'</span>';
    showAlert('Cannot connect — is XAMPP/Apache running?','error');
  };
  xhr.send(body);
}

// RECEIPT
function showReceipt(data,total,change,items) {
  const now=new Date().toLocaleString();
  let html=`
    <div class="receipt-logo">🌿</div>
    <div class="receipt-biz">${BIZ_NAME}</div>
    ${BIZ_ADDR?`<div class="receipt-sub">${BIZ_ADDR}</div>`:''}
    <div class="receipt-sub">${now}</div>
    <hr class="receipt-divider">
    <div class="receipt-row"><span>Invoice</span><span>${data.invoice_no}</span></div>
    <div class="receipt-row"><span>Customer</span><span>${(data.sale&&data.sale.cust_name)||'Walk-in'}</span></div>
    <div class="receipt-row"><span>Payment</span><span>${payMethod.replace(/_/g,' ').toUpperCase()}</span></div>
    <hr class="receipt-divider">
  `;
  (items||[]).forEach(i=>{
    html+=`<div class="receipt-row"><span>${i.name} × ${i.qty} ${i.unit}</span><span>${CURRENCY}${i.total.toFixed(2)}</span></div>`;
  });
  const st=(data.sale&&data.sale.total)?parseFloat(data.sale.total):total;
  html+=`
    <hr class="receipt-divider">
    <div class="receipt-row receipt-total"><span>TOTAL</span><span>${CURRENCY}${st.toFixed(2)}</span></div>
    ${change>0?`<div class="receipt-row"><span>Change</span><span>${CURRENCY}${change.toFixed(2)}</span></div>`:''}
    <div class="receipt-footer">${BIZ_FOOTER||'Thank you for your purchase!'}</div>
  `;
  document.getElementById('receiptContent').innerHTML=html;
  openModal('receiptModal');
}

function doPrintReceipt() {
  const el=document.getElementById('receiptContent');
  if(!el) return;
  const w=window.open('','_blank','width=380,height=620');
  w.document.write(`<!DOCTYPE html><html><head><title>Receipt</title><style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Courier New',monospace;font-size:12px;padding:12px;background:#fff;color:#000}
    .receipt-logo{font-size:24px;text-align:center;margin:4px 0}
    .receipt-biz{font-weight:bold;font-size:14px;text-align:center;margin:2px 0}
    .receipt-sub{font-size:11px;text-align:center;color:#555;margin:2px 0}
    .receipt-divider{border:none;border-top:1px dashed #999;margin:6px 0}
    .receipt-row{display:flex;justify-content:space-between;gap:8px;margin:3px 0;font-size:12px}
    .receipt-row span:last-child{white-space:nowrap}
    .receipt-total{font-weight:bold;font-size:13px;border-top:1px solid #000;padding-top:4px;margin-top:4px}
    .receipt-footer{text-align:center;margin-top:10px;font-size:11px;color:#555;border-top:1px dashed #ccc;padding-top:6px}
    @media print{@page{size:80mm auto;margin:4mm}body{padding:0}}
  </style></head><body>`+el.innerHTML+`</body></html>`);
  w.document.close();
  setTimeout(()=>{w.focus();w.print();},350);
}

function newSale() {
  closeModal('receiptModal');
  document.getElementById('discountInput').value='';
  document.getElementById('tenderedInput').value='';
  document.getElementById('saleNotes').value='';
  document.getElementById('changeDisplay').textContent=CURRENCY+'0.00';
  const b=document.getElementById('chargeBtn');
  if(b) b.innerHTML='✓ Charge <span id="chargeTotalBtn">'+CURRENCY+'0.00</span>';
  recalculate();
}

// HOLD / RECALL
function holdCart() {
  if(!cart.length){showAlert('Cart is empty');return;}
  const label=prompt('Label for this hold:','Table '+(Math.floor(Math.random()*10)+1))||'Hold';
  const body='action=hold_cart&items='+encodeURIComponent(JSON.stringify(cart))+'&label='+encodeURIComponent(label)+'&customer_id='+encodeURIComponent(document.getElementById('customerSelect').value);
  const xhr=new XMLHttpRequest(); xhr.open('POST','pos.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=function(){try{const d=JSON.parse(xhr.responseText);if(d.ok){showAlert(d.msg,'success');cart=[];renderCart();}}catch(e){}};
  xhr.send(body);
}

function loadHeld() {
  const xhr=new XMLHttpRequest(); xhr.open('GET','pos.php?action=get_held',true);
  xhr.onload=function(){
    try{
      const list=JSON.parse(xhr.responseText);
      const el=document.getElementById('heldList');
      if(!list.length){el.innerHTML='<div style="text-align:center;padding:30px;color:var(--text-secondary)">No held carts</div>';}
      else{el.innerHTML=list.map(h=>`
        <div style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:var(--r-md);border:1px solid var(--border);margin-bottom:8px">
          <div style="flex:1"><div style="font-weight:600">${h.label}</div><div style="font-size:12px;color:var(--text-secondary)">${new Date(h.created_at).toLocaleTimeString()}</div></div>
          <button class="btn btn-primary btn-sm" onclick="recallHeld(${h.id},'${encodeURIComponent(h.cart_data)}')">Recall</button>
          <button class="btn btn-danger btn-xs" onclick="deleteHeld(${h.id})">✕</button>
        </div>`).join('');}
      openModal('heldModal');
    }catch(e){showAlert('Could not load held carts','error');}
  };
  xhr.send();
}

function recallHeld(id,data) {
  try{
    cart=JSON.parse(decodeURIComponent(data)); renderCart(); closeModal('heldModal');
    const xhr=new XMLHttpRequest(); xhr.open('POST','pos.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.send('action=delete_held&id='+encodeURIComponent(id));
  }catch(e){showAlert('Failed to recall cart','error');}
}

function deleteHeld(id) {
  const xhr=new XMLHttpRequest(); xhr.open('POST','pos.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onload=()=>loadHeld(); xhr.send('action=delete_held&id='+encodeURIComponent(id));
}

// SEARCH / FILTER
const searchEl=document.getElementById('pos-search');
let searchTimer, activeCat=0;
searchEl.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(filterProducts,200);});
document.querySelectorAll('.cat-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.cat-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); activeCat=+btn.dataset.cat; filterProducts();
  });
});
function filterProducts() {
  const q=searchEl.value.toLowerCase();
  document.querySelectorAll('.product-btn').forEach(btn=>{
    btn.style.display=(!q||(btn.dataset.name||'').toLowerCase().includes(q))&&(!activeCat||+btn.dataset.cat===activeCat)?'':'none';
  });
}

// ALERTS
function showAlert(msg,type='error') {
  const el=document.createElement('div');
  el.className='flash flash-'+type;
  el.style.cssText='position:fixed;top:16px;right:16px;z-index:9999;max-width:340px';
  el.textContent=msg;
  document.body.appendChild(el);
  setTimeout(()=>{el.style.opacity='0';setTimeout(()=>el.remove(),400);},4000);
}

// KEYBOARD
document.addEventListener('keydown',e=>{
  if(e.altKey){
    if(e.key==='p'||e.key==='P'){e.preventDefault();searchEl.focus();}
    if(e.key==='c'||e.key==='C'){e.preventDefault();document.getElementById('chargeBtn').click();}
    if(e.key==='h'||e.key==='H'){e.preventDefault();holdCart();}
  }
});
window.onload=()=>searchEl.focus();
</script>
</body>
</html>
