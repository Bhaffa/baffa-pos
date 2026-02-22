<?php
// PHP 7.x compatibility shims
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
ob_start(); // Global output buffer — prevents any stray output from breaking AJAX JSON responses

// ─── DATABASE CONFIG ─────────────────────────────────────
define('DB_HOST', getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'farmpos');
define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');
define('UPLOAD_DIR', __DIR__ . '/uploads/products/');
define('UPLOAD_URL', 'uploads/products/');
define('VERSION', '1.0.0');

// ─── SESSION ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_httponly'  => true,
        'use_strict_mode'  => true,
    ]);
}

// ─── SESSION TIMEOUT: 2 hours ─────────────────────────────
define('SESSION_TIMEOUT', 7200);
if (isset($_SESSION['pos_last_active']) && (time() - $_SESSION['pos_last_active']) > SESSION_TIMEOUT) {
    session_destroy();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        // AJAX request — send JSON error instead of redirect
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'Session expired. Please refresh the page.','session_expired'=>true]);
        exit;
    }
    ob_end_clean();
    header('Location: login.php?timeout=1');
    exit;
}
if (isset($_SESSION['pos_id'])) {
    $_SESSION['pos_last_active'] = time();
}

// ─── DATABASE CONNECTION ─────────────────────────────────
try {
    $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false || strpos($e->getMessage(), "Can't connect") !== false) {
        die('<html><head><title>Setup Required</title>
        <style>body{font-family:-apple-system,sans-serif;max-width:580px;margin:80px auto;padding:24px;background:#f5f5f7}
        .box{background:#fff;border-radius:20px;padding:36px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
        h2{color:#1d1d1f;font-size:22px}p{color:#6e6e73;line-height:1.6}
        code{background:#f0f0f0;padding:2px 8px;border-radius:6px;font-size:13px}
        .step{background:#f5f5f7;border-radius:12px;padding:14px 18px;margin:10px 0;font-size:14px}
        </style></head><body><div class="box">
        <h2>⚙️ Database Setup Required</h2>
        <div class="step"><b>Step 1:</b> Open <code>http://localhost/phpmyadmin</code></div>
        <div class="step"><b>Step 2:</b> Click <b>Import</b> → Choose File → select <code>install.sql</code> → Go</div>
        <div class="step"><b>Step 3:</b> Visit <code>http://localhost/farmpos/setup_admin.php</code> to create your account</div>
        </div></body></html>');
    }
    die('Database error: ' . $e->getMessage());
}

// ─── FIRST-RUN: Redirect to setup if no admin exists ────
// (skip this check on setup_admin.php itself)
if (strpos($_SERVER['PHP_SELF'] ?? '', 'setup_admin.php') === false) {
    try {
        $adminCount = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
        if ((int)$adminCount === 0) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok'=>false,'msg'=>'Setup required. Please visit setup_admin.php first.']);
                exit;
            }
            ob_end_clean();
            header('Location: setup_admin.php');
            exit;
        }
    } catch (Exception $e) {}
}

// ─── LOAD BUSINESS SETTINGS ──────────────────────────────
function getSettings(PDO $pdo): array {
    try {
        $r = $pdo->query("SELECT * FROM admin LIMIT 1")->fetch();
        return $r ?: ['business_name'=>'Baffa Precision Agri-Tech','currency_symbol'=>'₦','tax_rate'=>0,'receipt_footer'=>''];
    } catch (Exception $e) { return ['business_name'=>'Baffa Precision Agri-Tech','currency_symbol'=>'₦','tax_rate'=>0]; }
}

// ─── AUTH ─────────────────────────────────────────────────
function isLoggedIn(): bool { return isset($_SESSION['pos_id']); }

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function redirect(string $url, string $msg = '', string $type = 'success'): void {
    if ($msg) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
    ob_end_clean(); // Clear any buffered output before redirect
    header("Location: $url"); exit;
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function logAction(PDO $pdo, string $action, string $desc = ''): void {
    try {
        $pdo->prepare("INSERT INTO audit_logs(action,description,ip_address)VALUES(?,?,?)")
            ->execute([$action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

// ─── FORMATTING ────────────────────────────────────────────
function money(float $amount, string $symbol = '₦'): string {
    return $symbol . number_format($amount, 2);
}

function fmtDate(string $date): string {
    return date('d M Y', strtotime($date));
}

// ─── INVOICE NUMBER ───────────────────────────────────────
function generateInvoiceNo(PDO $pdo): string {
    $prefix = 'INV';
    $date   = date('Ymd');
    $last   = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    return $prefix . $date . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
}

// ─── INVENTORY: DEDUCT STOCK ──────────────────────────────
function deductStock(PDO $pdo, int $productId, float $qty): void {
    $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND track_stock = 1")
        ->execute([$qty, $productId]);
}

function addStock(PDO $pdo, int $productId, float $qty): void {
    $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ? AND track_stock = 1")
        ->execute([$qty, $productId]);
}

// ─── LOW STOCK CHECK ─────────────────────────────────────
function getLowStockCount(PDO $pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM products WHERE track_stock=1 AND stock_qty <= reorder_level AND is_active=1")->fetchColumn();
}

// ─── DAILY STATS ─────────────────────────────────────────
function getTodayStats(PDO $pdo): array {
    $today = date('Y-m-d');
    $sales = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) revenue FROM sales WHERE DATE(created_at)=? AND status='completed'");
    $sales->execute([$today]); $s = $sales->fetch();
    $items = $pdo->prepare("SELECT COALESCE(SUM(si.qty),0) FROM sale_items si JOIN sales sa ON si.sale_id=sa.id WHERE DATE(sa.created_at)=? AND sa.status='completed'");
    $items->execute([$today]);
    return [
        'transactions' => $s['cnt'],
        'revenue'      => $s['revenue'],
        'items_sold'   => $items->fetchColumn(),
    ];
}

// ─── CLEAN OUTPUT ─────────────────────────────────────────
function clean(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
