<?php
if (!defined('PAGE_TITLE')) define('PAGE_TITLE', 'Baffa Precision Agri-Tech');
requireLogin();

$settings = getSettings($pdo);
$bizName  = $settings['business_name'] ?? 'Baffa Precision Agri-Tech';
$currSym  = $settings['currency_symbol'] ?? '₦';
$lowStock = getLowStockCount($pdo);
$flash    = getFlash();

// Active page detection
$page = basename($_SERVER['PHP_SELF'], '.php');

// Try to get today's egg production
$todayEggs = 0;
try {
    $te = $pdo->query("SELECT COALESCE(SUM(total_collected),0) FROM production_logs WHERE log_date=CURDATE()");
    $todayEggs = (int)$te->fetchColumn();
} catch(Exception $e) {}

$navGroups = [
    'Sales' => [
        ['p'=>'pos',        'i'=>'🛒','l'=>'POS Checkout'],
        ['p'=>'sales',      'i'=>'📑','l'=>'Sales History'],
        ['p'=>'returns',    'i'=>'↩️','l'=>'Returns'],
        ['p'=>'customers',  'i'=>'👥','l'=>'Customers'],
    ],
    'Inventory' => [
        ['p'=>'products',   'i'=>'📦','l'=>'Products'],
        ['p'=>'inventory',  'i'=>'🗃️','l'=>'Stock Management'],
        ['p'=>'purchases',  'i'=>'🚚','l'=>'Purchases / Stock In'],
        ['p'=>'suppliers',  'i'=>'🏭','l'=>'Suppliers'],
    ],
    'Farm' => [
        ['p'=>'flocks',     'i'=>'🐔','l'=>'Flock Management'],
        ['p'=>'production', 'i'=>'🥚','l'=>'Egg Production Log'],
        ['p'=>'feed',       'i'=>'🌾','l'=>'Feed & Inputs'],
        ['p'=>'mortality',  'i'=>'📉','l'=>'Mortality Records'],
        ['p'=>'expenses',   'i'=>'💸','l'=>'Farm Expenses'],
    ],
    'Analytics' => [
        ['p'=>'reports',    'i'=>'📊','l'=>'Reports & Analytics'],
    ],
    'System' => [
        ['p'=>'settings',   'i'=>'⚙️','l'=>'Settings'],
        ['p'=>'backup',     'i'=>'💾','l'=>'Backup & Export'],
        ['p'=>'logs',       'i'=>'🔍','l'=>'Audit Logs'],
    ],
];
$userInitial = strtoupper(substr($_SESSION['pos_name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= clean(PAGE_TITLE) ?> — Baffa Precision Agri-Tech</title>
<link rel="icon" type="image/png" href="<?= str_repeat('../', substr_count(trim($_SERVER['PHP_SELF'],'/'), '/') - 1) ?>assets/logo.png">
<link rel="apple-touch-icon" href="<?= str_repeat('../', substr_count(trim($_SERVER['PHP_SELF'],'/'), '/') - 1) ?>assets/logo.png">
<link rel="stylesheet" href="<?= str_repeat('../', substr_count(trim($_SERVER['PHP_SELF'],'/'), '/') - 1) ?>assets/style.css">
<meta name="theme-color" content="#1C1917">
</head>
<body>
<div class="layout">

<!-- ─── SIDEBAR ─────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <img src="<?= str_repeat('../', substr_count(trim($_SERVER['PHP_SELF'],'/'), '/') - 1) ?>assets/logo.png"
         alt="Baffa Logo" style="width:54px;height:54px;object-fit:contain;border-radius:10px;background:#fff;padding:3px">
    <div class="biz-name"><?= clean($bizName) ?></div>
    <div class="biz-sub">Precision Agri-Tech POS</div>
    <?php if ($todayEggs > 0): ?>
    <div class="pos-badge">🥚 <?= number_format($todayEggs) ?> eggs today</div>
    <?php endif; ?>
  </div>

  <nav class="nav-scroll">
    <?php foreach ($navGroups as $group => $navItems): ?>
    <div class="nav-section"><?= $group ?></div>
    <?php foreach ($navItems as $item): ?>
    <a href="<?= $item['p'] ?>.php" class="nav-item <?= $page === $item['p'] ? 'active' : '' ?>">
      <span class="nav-icon"><?= $item['i'] ?></span>
      <span><?= $item['l'] ?></span>
      <?php if ($item['p'] === 'inventory' && $lowStock > 0): ?>
      <span class="nav-badge"><?= $lowStock ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom">
    <div class="sidebar-user">
      <div class="user-avatar"><?= $userInitial ?></div>
      <div>
        <div class="user-name"><?= clean($_SESSION['pos_name'] ?? 'Admin') ?></div>
        <div class="user-role">Owner / Admin</div>
      </div>
      <a href="logout.php" class="logout-btn" title="Logout">⏏</a>
    </div>
  </div>
</aside>

<!-- ─── MAIN ─────────────────────────────────────────────── -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title"><?= clean(PAGE_TITLE) ?></div>
    <?php if ($page !== 'pos'): // POS has its own search ?>
    <div class="topbar-search">
      <span>🔍</span>
      <input type="text" id="tableSearch" placeholder="Search table..." autocomplete="off">
    </div>
    <?php endif; ?>
    <a href="pos.php" class="btn btn-primary btn-sm" style="gap:6px">🛒 New Sale</a>
    <?php if ($lowStock > 0): ?>
    <a href="inventory.php#low" class="btn btn-warning btn-sm">⚠️ <?= $lowStock ?> Low Stock</a>
    <?php endif; ?>
    <a href="reports.php" class="btn btn-secondary btn-sm">📊</a>
  </div>
  <div class="page-content">

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= $flash['type'] === 'success' ? '✅' : '❌' ?> <?= clean($flash['msg']) ?></div>
<?php endif; ?>
