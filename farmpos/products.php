<?php
require_once 'config.php';
requireLogin();
define('PAGE_TITLE','Products');

$act = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save product
    if (in_array($act, ['add','edit'])) {
        $name       = trim($_POST['name'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0) ?: null;
        $sku        = trim($_POST['sku'] ?? '') ?: null;
        $barcode    = trim($_POST['barcode'] ?? '') ?: null;
        $unit       = trim($_POST['unit'] ?? 'piece');
        $priceR     = floatval($_POST['price_retail'] ?? 0);
        $priceW     = floatval($_POST['price_wholesale'] ?? 0);
        $cost       = floatval($_POST['cost_price'] ?? 0);
        $stock      = floatval($_POST['stock_qty'] ?? 0);
        $reorder    = floatval($_POST['reorder_level'] ?? 5);
        $track      = isset($_POST['track_stock']) ? 1 : 0;
        $notes      = trim($_POST['notes'] ?? '');
        $id         = intval($_POST['id'] ?? 0);

        // Image upload
        $image = $_POST['existing_image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                $filename = 'prod_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename);
                $image = $filename;
            }
        }

        if (!$name) { redirect('products.php', 'Product name is required.', 'error'); }
        if ($priceW <= 0) $priceW = $priceR;

        if ($act === 'add') {
            try {
                $pdo->prepare("INSERT INTO products(category_id,name,sku,barcode,unit,price_retail,price_wholesale,cost_price,stock_qty,reorder_level,track_stock,image,notes)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$categoryId,$name,$sku,$barcode,$unit,$priceR,$priceW,$cost,$stock,$reorder,$track,$image,$notes]);
                logAction($pdo,'ADD_PRODUCT',"Name:$name");
                redirect('products.php','Product "' . $name . '" added successfully!');
            } catch(Exception $e) { redirect('products.php','Error: '.$e->getMessage(),'error'); }
        } else {
            $pdo->prepare("UPDATE products SET category_id=?,name=?,sku=?,barcode=?,unit=?,price_retail=?,price_wholesale=?,cost_price=?,stock_qty=?,reorder_level=?,track_stock=?,image=?,notes=?,updated_at=NOW() WHERE id=?")
                ->execute([$categoryId,$name,$sku,$barcode,$unit,$priceR,$priceW,$cost,$stock,$reorder,$track,$image,$notes,$id]);
            logAction($pdo,'EDIT_PRODUCT',"ID:$id Name:$name");
            redirect('products.php','Product updated successfully!');
        }
    }

    if ($act === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
        logAction($pdo,'DELETE_PRODUCT',"ID:$id");
        redirect('products.php','Product deleted.');
    }

    if ($act === 'toggle') {
        $id = intval($_POST['id']);
        $pdo->prepare("UPDATE products SET is_active=1-is_active WHERE id=?")->execute([$id]);
        redirect('products.php','Product status toggled.');
    }
}

$editProduct = null;
if ($act === 'edit' && isset($_GET['id'])) {
    $st = $pdo->prepare("SELECT * FROM products WHERE id=?"); $st->execute([$_GET['id']]);
    $editProduct = $st->fetch();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order,name")->fetchAll();
$filterCat  = intval($_GET['cat'] ?? 0);
$filterLow  = isset($_GET['low']);

$sql = "SELECT p.*, c.name cat_name, c.icon cat_icon, c.color cat_color FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE 1=1";
$par = [];
if ($filterCat) { $sql .= " AND p.category_id=?"; $par[] = $filterCat; }
if ($filterLow) { $sql .= " AND p.track_stock=1 AND p.stock_qty<=p.reorder_level"; }
$sql .= " ORDER BY p.is_active DESC, p.name";
$st = $pdo->prepare($sql); $st->execute($par);
$products = $st->fetchAll();

$units = ['piece','carton','crate','kg','g','litre','pack','bag','dozen','tray'];

include 'includes/header.php';
?>

<div class="page-header">
  <div><h2>Products</h2><p><?= count($products) ?> product<?= count($products)!=1?'s':'' ?> <?= $filterLow?'with low stock':'' ?></p></div>
  <div class="d-flex gap-1">
    <?php if($filterLow): ?><a href="products.php" class="btn btn-secondary btn-sm">✕ Clear Filter</a><?php endif; ?>
    <button onclick="openModal('productModal')" class="btn btn-primary">+ Add Product</button>
  </div>
</div>

<!-- Category Filter -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <a href="products.php" class="cat-btn <?= !$filterCat?'active':'' ?>">All (<?= $pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn() ?>)</a>
  <?php foreach($categories as $c): ?>
  <?php $cnt=$pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=? AND is_active=1");$cnt->execute([$c['id']]);$cnt=$cnt->fetchColumn(); ?>
  <a href="?cat=<?= $c['id'] ?>" class="cat-btn <?= $filterCat==$c['id']?'active':'' ?>"><?= $c['icon'] ?> <?= clean($c['name']??'') ?> (<?= $cnt ?>)</a>
  <?php endforeach; ?>
  <a href="?low=1" class="cat-btn <?= $filterLow?'active':'' ?>">⚠️ Low Stock</a>
</div>

<div class="card">
  <div class="card-header">
    <h3>Product Catalog</h3>
    <button onclick="exportCSV('productTable','products.csv')" class="btn btn-secondary btn-sm">📥 Export CSV</button>
  </div>
  <div class="table-wrap">
    <table id="productTable">
      <thead><tr>
        <th>Product</th><th>Category</th><th>Unit</th>
        <th data-sort="3">Retail Price</th><th data-sort="4">Wholesale</th><th data-sort="5">Cost</th>
        <th data-sort="6">Stock</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach($products as $p):
          $sym2 = $settings['currency_symbol']??'₦';
          $lowS = $p['track_stock'] && $p['stock_qty'] <= $p['reorder_level'];
        ?>
        <tr style="opacity:<?= $p['is_active']?1:.4 ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if($p['image']&&file_exists(UPLOAD_DIR.$p['image'])): ?>
              <img src="<?= UPLOAD_URL.$p['image'] ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover">
              <?php else: ?>
              <div style="width:36px;height:36px;border-radius:8px;background:var(--input-bg);display:flex;align-items:center;justify-content:center;font-size:18px"><?= $p['cat_icon']??'📦' ?></div>
              <?php endif; ?>
              <div>
                <div class="fw-600"><?= clean($p['name']??'') ?></div>
                <?php if($p['sku']): ?><div class="text-muted" style="font-size:11px">SKU: <?= clean($p['sku']??'') ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><?php if($p['cat_name']): ?><span class="badge badge-secondary"><?= $p['cat_icon']??' ' ?> <?= clean($p['cat_name']) ?></span><?php else: ?>—<?php endif; ?></td>
          <td><?= clean($p['unit']??'') ?></td>
          <td class="fw-600"><?= $sym2 ?><?= number_format($p['price_retail'],2) ?></td>
          <td class="text-muted"><?= $sym2 ?><?= number_format($p['price_wholesale'],2) ?></td>
          <td class="text-muted"><?= $sym2 ?><?= number_format($p['cost_price'],2) ?></td>
          <td>
            <?php if($p['track_stock']): ?>
            <span class="badge badge-<?= $p['stock_qty']<=0?'danger':($lowS?'warning':'success') ?>">
              <?= number_format($p['stock_qty'],1) ?> <?= clean($p['unit']??'') ?>
            </span>
            <?php else: ?><span class="badge badge-secondary">Unlimited</span><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $p['is_active']?'success':'danger' ?>"><?= $p['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <div class="td-actions">
              <button class="btn btn-secondary btn-xs" onclick='editProduct(<?= json_encode($p) ?>)'>✏️</button>
              <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-danger btn-xs" data-confirm="Delete <?= clean($p['name']??'') ?>?">🗑</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$products): ?><tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📦</div><h3>No products yet</h3><p>Add your first product to get started</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 id="productModalTitle">Add Product</h3>
      <button class="btn-close" onclick="closeModal('productModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="products.php" enctype="multipart/form-data">
        <input type="hidden" name="action" id="productAction" value="add">
        <input type="hidden" name="id" id="productId">
        <input type="hidden" name="existing_image" id="existingImage">
        <div class="form-row-2">
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Product Name *</label>
            <input type="text" name="name" id="prodName" class="form-control" required placeholder="e.g. Egg Grade A">
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="prodCat" class="form-control">
              <option value="">-- None --</option>
              <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['icon'] ?> <?= clean($c['name']??'') ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Unit</label>
            <select name="unit" id="prodUnit" class="form-control">
              <?php foreach($units as $u): ?><option value="<?= $u ?>"><?= $u ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">SKU</label>
            <input type="text" name="sku" id="prodSku" class="form-control" placeholder="Auto-generated if empty">
          </div>
          <div class="form-group">
            <label class="form-label">Barcode</label>
            <input type="text" name="barcode" id="prodBarcode" class="form-control" placeholder="Optional">
          </div>
          <div class="form-group">
            <label class="form-label">Retail Price *</label>
            <input type="number" name="price_retail" id="prodPriceR" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Wholesale Price</label>
            <input type="number" name="price_wholesale" id="prodPriceW" class="form-control" step="0.01" min="0" placeholder="Same as retail if empty">
          </div>
          <div class="form-group">
            <label class="form-label">Cost Price</label>
            <input type="number" name="cost_price" id="prodCost" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Current Stock</label>
            <input type="number" name="stock_qty" id="prodStock" class="form-control" step="0.01" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Reorder Level</label>
            <input type="number" name="reorder_level" id="prodReorder" class="form-control" step="0.01" value="5">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Product Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Notes</label>
            <textarea name="notes" id="prodNotes" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
              <input type="checkbox" name="track_stock" id="prodTrack" value="1" checked style="width:16px;height:16px">
              Track inventory for this product
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editProduct(p) {
  document.getElementById('productModalTitle').textContent = 'Edit Product';
  document.getElementById('productAction').value = 'edit';
  document.getElementById('productId').value = p.id;
  document.getElementById('prodName').value = p.name;
  document.getElementById('prodCat').value = p.category_id||'';
  document.getElementById('prodUnit').value = p.unit;
  document.getElementById('prodSku').value = p.sku||'';
  document.getElementById('prodBarcode').value = p.barcode||'';
  document.getElementById('prodPriceR').value = p.price_retail;
  document.getElementById('prodPriceW').value = p.price_wholesale;
  document.getElementById('prodCost').value = p.cost_price;
  document.getElementById('prodStock').value = p.stock_qty;
  document.getElementById('prodReorder').value = p.reorder_level;
  document.getElementById('prodTrack').checked = p.track_stock=='1';
  document.getElementById('prodNotes').value = p.notes||'';
  document.getElementById('existingImage').value = p.image||'';
  openModal('productModal');
}
</script>

<?php include 'includes/footer.php'; ?>
