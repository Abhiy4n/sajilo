<?php
// ── BOOTSTRAP ──────────────────────────────────────────────────────────────
session_start();
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

$uid   = $_SESSION['user_id'];
$name  = $_SESSION['user_name'];
$first = explode(' ', $name)[0];

$hour  = (int)date('H');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── STATS (using prepared statements) ───────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id = ? AND warranty_expiry >= CURDATE()");
$stmt->bind_param("i", $uid);
$stmt->execute();
$active_warranties = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id = ? AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stmt->bind_param("i", $uid);
$stmt->execute();
$expiring_soon = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total_customers = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(balance_due),0) FROM sales WHERE user_id = ? AND balance_due > 0");
$stmt->bind_param("i", $uid);
$stmt->execute();
$outstanding = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE user_id = ? AND MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())");
$stmt->bind_param("i", $uid);
$stmt->execute();
$revenue = $stmt->get_result()->fetch_row()[0];
$stmt->close();

// ── RECENT SALES ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT s.*, c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.user_id = ?
    ORDER BY s.sale_date DESC, s.id DESC
    LIMIT 6
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$recent_sales = $stmt->get_result();
$stmt->close();

// ── EXPIRING WARRANTIES ───────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT s.*, c.name AS customer_name,
           DATEDIFF(s.warranty_expiry, CURDATE()) AS days_left
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.user_id = ?
      AND s.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY s.warranty_expiry ASC
    LIMIT 6
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$expiring = $stmt->get_result();
$stmt->close();

$alerts = (int)$expiring_soon + ($outstanding > 0 ? 1 : 0);

// ── SIDEBAR ACTIVE PAGE ───────────────────────────────────────────────────────
$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sajilo — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php require_once 'includes/sidebar.php'; ?>

<div class="main">

  <!-- ── TOPBAR ── -->
  <div class="topbar">
    <div>
      <h1><?= $greet ?>, <?= htmlspecialchars($first) ?> 👋</h1>
      <p><?= date('l, F j, Y') ?></p>
    </div>
    <div class="topbar-right">
      <a href="pages/inventory.php" class="bell" title="<?= $alerts ?> alert(s)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7D6B" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <?php if ($alerts > 0): ?>
          <span class="bell-count"><?= $alerts ?></span>
        <?php endif; ?>
      </a>
      <a href="pages/record_sale.php" class="btn-new">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Record Sale
      </a>
    </div>
  </div>

  <!-- ── CONTENT ── -->
  <div class="content">

    <!-- STATS -->
    <div class="stats-grid">

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Total Products</span>
          <div class="stat-icon" style="background:#F0FDF4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          </div>
        </div>
        <div class="stat-num"><?= number_format($total_products) ?></div>
        <div class="stat-sub">Items in inventory</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Active Warranties</span>
          <div class="stat-icon" style="background:#F0FDF4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
        </div>
        <div class="stat-num g"><?= number_format($active_warranties) ?></div>
        <div class="stat-sub">Currently valid</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Expiring in 30 Days</span>
          <div class="stat-icon" style="background:#FFFBEB">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
        </div>
        <div class="stat-num a"><?= number_format($expiring_soon) ?></div>
        <div class="stat-sub">Need attention soon</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Total Customers</span>
          <div class="stat-icon" style="background:#EFF6FF">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
        </div>
        <div class="stat-num"><?= number_format($total_customers) ?></div>
        <div class="stat-sub">Registered customers</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Outstanding Balance</span>
          <div class="stat-icon" style="background:<?= $outstanding > 0 ? '#FEE2E2' : '#F0FDF4' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $outstanding > 0 ? '#EF4444' : '#1DB954' ?>" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
        </div>
        <div class="stat-num <?= $outstanding > 0 ? 'r' : 'g' ?>">Rs <?= number_format($outstanding) ?></div>
        <div class="stat-sub"><?= $outstanding > 0 ? 'Unpaid balances owed' : 'All cleared' ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <span class="stat-label">Revenue This Month</span>
          <div class="stat-icon" style="background:#F0FDF4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          </div>
        </div>
        <div class="stat-num g">Rs <?= number_format($revenue) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
      </div>

    </div><!-- /stats-grid -->

    <!-- TABLES -->
    <div class="grid2">

      <!-- Recent Sales -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">Recent Sales</span>
          <a href="pages/record_sale.php" class="card-action">+ New Sale</a>
        </div>
        <?php if ($recent_sales->num_rows > 0): ?>
        <table>
          <thead>
            <tr><th>Product</th><th>Customer</th><th>Total</th><th>Balance</th></tr>
          </thead>
          <tbody>
            <?php while ($r = $recent_sales->fetch_assoc()): ?>
            <tr>
              <td class="td-main"><?= htmlspecialchars($r['product_name']) ?></td>
              <td><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
              <td>Rs <?= number_format($r['total_price']) ?></td>
              <td>
                <?php if ($r['balance_due'] > 0): ?>
                  <span class="badge due">Rs <?= number_format($r['balance_due']) ?></span>
                <?php else: ?>
                  <span class="badge paid">Paid</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty">No sales yet. <a href="pages/record_sale.php">Record your first →</a></div>
        <?php endif; ?>
      </div>

      <!-- Expiring Warranties -->
      <div class="card">
        <div class="card-hdr">
          <span class="card-title">Expiring Warranties</span>
          <a href="pages/inventory.php" class="card-action">View All</a>
        </div>
        <?php if ($expiring->num_rows > 0): ?>
        <table>
          <thead>
            <tr><th>Product</th><th>Customer</th><th>Expires</th><th>Left</th></tr>
          </thead>
          <tbody>
            <?php while ($r = $expiring->fetch_assoc()): $d = (int)$r['days_left']; ?>
            <tr>
              <td class="td-main"><?= htmlspecialchars($r['product_name']) ?></td>
              <td><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
              <td><?= date('M j, Y', strtotime($r['warranty_expiry'])) ?></td>
              <td><span class="badge <?= $d <= 7 ? 'exp' : 'warn' ?>"><?= $d ?>d</span></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty">No warranties expiring in the next 30 days.</div>
        <?php endif; ?>
      </div>

    </div><!-- /grid2 -->

  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>