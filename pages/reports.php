<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../includes/db.php';

$uid   = $_SESSION['user_id'];
$name  = $_SESSION['user_name'];
$first = explode(' ', $name)[0];

// ── DATE RANGE ──
$range = $_GET['range'] ?? '30';
$days  = in_array($range, ['7','30','90','365']) ? (int)$range : 30;
$from  = date('Y-m-d', strtotime("-{$days} days"));
$today = date('Y-m-d');
$label = match($days) {
    7   => 'Last 7 Days',
    30  => 'Last 30 Days',
    90  => 'Last 3 Months',
    365 => 'Last 12 Months',
    default => 'Last 30 Days'
};

// ── SUMMARY STATS (using prepared statements) ──
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE user_id=? AND sale_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $uid, $from, $today);
$stmt->execute();
$revenue = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id=? AND sale_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $uid, $from, $today);
$stmt->execute();
$total_sales = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(balance_due),0) FROM sales WHERE user_id=? AND balance_due > 0");
$stmt->bind_param("i", $uid);
$stmt->execute();
$outstanding = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE user_id=? AND created_at >= ?");
$stmt->bind_param("is", $uid, $from);
$stmt->execute();
$new_customers = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total_collected = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(balance_due),0) FROM sales WHERE user_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total_due_ever = $stmt->get_result()->fetch_row()[0];
$stmt->close();

// ── REVENUE BY DAY/MONTH ──
$chart_labels  = [];
$chart_revenue = [];
$chart_sales   = [];

if ($days <= 30) {
    $stmt = $conn->prepare("
        SELECT sale_date, SUM(amount_paid) as revenue, COUNT(*) as cnt
        FROM sales WHERE user_id=? AND sale_date BETWEEN ? AND ?
        GROUP BY sale_date ORDER BY sale_date ASC
    ");
    $stmt->bind_param("iss", $uid, $from, $today);
} else {
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(sale_date,'%Y-%m') as sale_date,
               SUM(amount_paid) as revenue, COUNT(*) as cnt
        FROM sales WHERE user_id=? AND sale_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(sale_date,'%Y-%m') ORDER BY sale_date ASC
    ");
    $stmt->bind_param("iss", $uid, $from, $today);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $chart_labels[]  = $days <= 30
        ? date('M j', strtotime($r['sale_date']))
        : date('M y', strtotime($r['sale_date'].'-01'));
    $chart_revenue[] = (float)$r['revenue'];
    $chart_sales[]   = (int)$r['cnt'];
}
$res->free();
$stmt->close();

// ── TOP PRODUCTS ──
$stmt = $conn->prepare("
    SELECT product_name, COUNT(*) as units_sold,
           SUM(total_price) as total_revenue,
           SUM(amount_paid) as total_paid,
           SUM(balance_due) as total_due
    FROM sales WHERE user_id=? AND sale_date BETWEEN ? AND ?
    GROUP BY product_name ORDER BY total_revenue DESC LIMIT 6
");
$stmt->bind_param("iss", $uid, $from, $today);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── TOP CUSTOMERS ──
$stmt = $conn->prepare("
    SELECT c.name, c.phone,
           COUNT(s.id) as purchases,
           SUM(s.total_price) as total_spent,
           SUM(s.balance_due) as balance_due
    FROM sales s JOIN customers c ON s.customer_id=c.id
    WHERE s.user_id=? AND s.sale_date BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total_spent DESC LIMIT 5
");
$stmt->bind_param("iss", $uid, $from, $today);
$stmt->execute();
$top_customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── SALES BY CATEGORY ──
$stmt = $conn->prepare("
    SELECT COALESCE(cat.name,'Uncategorized') as cat_name,
           COUNT(s.id) as cnt,
           SUM(s.total_price) as revenue
    FROM sales s
    LEFT JOIN products p ON s.product_id=p.id
    LEFT JOIN categories cat ON p.category_id=cat.id
    WHERE s.user_id=? AND s.sale_date BETWEEN ? AND ?
    GROUP BY cat.name ORDER BY revenue DESC LIMIT 6
");
$stmt->bind_param("iss", $uid, $from, $today);
$stmt->execute();
$by_category = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── EXPIRING WARRANTIES ──
$stmt = $conn->prepare("
    SELECT s.product_name, s.model_no, s.warranty_expiry,
           DATEDIFF(s.warranty_expiry, CURDATE()) as days_left,
           c.name as customer_name, c.phone
    FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
    WHERE s.user_id=?
      AND s.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    ORDER BY s.warranty_expiry ASC LIMIT 8
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$expiring = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── DEBTORS ──
$stmt = $conn->prepare("
    SELECT c.name, c.phone,
           SUM(s.balance_due) as total_due,
           COUNT(s.id) as sales_count
    FROM sales s JOIN customers c ON s.customer_id=c.id
    WHERE s.user_id=? AND s.balance_due > 0
    GROUP BY c.id ORDER BY total_due DESC LIMIT 5
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$debtors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Payment collection rate
$total_receivable = $revenue + $outstanding;
$collection_rate = $total_receivable > 0
    ? round(($revenue / $total_receivable) * 100)
    : 100;

// Warranty breakdown
$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id=? AND warranty_expiry >= CURDATE()");
$stmt->bind_param("i", $uid);
$stmt->execute();
$w_active = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id=? AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)");
$stmt->bind_param("i", $uid);
$stmt->execute();
$w_expiring = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id=? AND warranty_expiry < CURDATE() AND warranty_expiry IS NOT NULL");
$stmt->bind_param("i", $uid);
$stmt->execute();
$w_expired = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM sales WHERE user_id=? AND warranty_expiry IS NULL");
$stmt->bind_param("i", $uid);
$stmt->execute();
$w_none = $stmt->get_result()->fetch_row()[0];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sajilo — Reports</title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
    --sidebar:#0D1F0D;--green:#1DB954;--green-dark:#16A34A;
    --white:#fff;--bg:#F8FAF8;--border:#E4EDE4;
    --text:#0F1A0F;--sub:#6B7D6B;--light:#9CA3AF;
    --red:#EF4444;--amber:#F59E0B;--blue:#3B82F6;--purple:#8B5CF6;
    --sh:0 1px 3px rgba(0,0,0,0.07);
    --sh-md:0 6px 24px rgba(0,0,0,0.09);
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:240px;min-height:100vh;background:var(--sidebar);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:50}
.sb-logo{display:flex;align-items:center;gap:10px;padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.06);text-decoration:none}
.sb-logo-icon{width:30px;height:30px;background:var(--green);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-logo-name{font-family:'Bricolage Grotesque',sans-serif;font-size:17px;font-weight:700;color:#fff}
.sb-nav{flex:1;padding:14px 12px}
.sb-label{font-size:10px;font-weight:600;color:rgba(255,255,255,0.25);text-transform:uppercase;letter-spacing:.1em;padding:0 10px;margin:14px 0 5px}
.sb-link{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:14px;font-weight:500;color:rgba(255,255,255,0.5);text-decoration:none;transition:all .18s;margin-bottom:2px;position:relative}
.sb-link svg{flex-shrink:0;opacity:.5;transition:opacity .18s}
.sb-link:hover{background:rgba(255,255,255,0.06);color:rgba(255,255,255,.85)}
.sb-link:hover svg{opacity:.85}
.sb-link.active{background:rgba(29,185,84,0.15);color:#fff}
.sb-link.active svg{opacity:1}
.sb-link.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:20px;background:var(--green);border-radius:0 3px 3px 0}
.sb-bottom{padding:14px 12px;border-top:1px solid rgba(255,255,255,0.06)}
.sb-user{display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:4px}
.sb-avatar{width:32px;height:32px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.sb-uname{font-size:13px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-urole{font-size:11px;color:rgba(255,255,255,.35)}

/* MAIN */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40}
.topbar h1{font-family:'Bricolage Grotesque',sans-serif;font-size:19px;font-weight:800}
.topbar p{font-size:12px;color:var(--sub);margin-top:1px}
.content{padding:28px 32px;flex:1}

/* RANGE PILLS */
.range-pills{display:flex;gap:6px;margin-bottom:24px}
.pill{padding:7px 18px;border-radius:50px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid var(--border);color:var(--sub);background:var(--white);transition:all .2s}
.pill:hover{border-color:var(--green);color:var(--green)}
.pill.on{background:var(--green);color:#fff;border-color:var(--green)}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:16px;padding:22px 24px;box-shadow:var(--sh);transition:all .2s}
.stat-card:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.stat-lbl{font-size:13px;font-weight:500;color:var(--sub)}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.stat-num{font-family:'Bricolage Grotesque',sans-serif;font-size:26px;font-weight:800;line-height:1;margin-bottom:4px}
.stat-num.g{color:var(--green)}.stat-num.r{color:var(--red)}
.stat-num.b{color:var(--blue)}.stat-num.p{color:var(--purple)}
.stat-sub{font-size:12px;color:var(--light)}

/* LAYOUT GRIDS */
.row{display:grid;gap:20px;margin-bottom:20px}
.row-2{grid-template-columns:1fr 1fr}
.row-3{grid-template-columns:2fr 1fr}
.row-32{grid-template-columns:1fr 1fr 1fr}

/* CHART CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--sh)}
.card-hdr{padding:18px 22px 14px;display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700}
.card-sub{font-size:12px;color:var(--light);background:var(--bg);padding:4px 10px;border-radius:50px;border:1px solid var(--border)}
.chart-body{padding:8px 20px 20px;position:relative}
.chart-body canvas{max-height:260px}

/* DONUT CENTER TEXT */
.donut-wrap{position:relative;display:flex;align-items:center;justify-content:center}
.donut-center{position:absolute;text-align:center;pointer-events:none}
.donut-center-num{font-family:'Bricolage Grotesque',sans-serif;font-size:26px;font-weight:800;color:var(--text);line-height:1}
.donut-center-lbl{font-size:11px;color:var(--light);margin-top:2px}

/* LEGEND */
.legend{display:flex;flex-wrap:wrap;gap:10px;padding:0 20px 16px}
.legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--sub)}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}

/* COLLECTION RATE */
.collection-wrap{padding:20px 24px}
.collection-rate-num{font-family:'Bricolage Grotesque',sans-serif;font-size:48px;font-weight:800;color:var(--green);line-height:1;margin-bottom:4px}
.collection-rate-lbl{font-size:13px;color:var(--sub);margin-bottom:20px}
.coll-bar{height:12px;background:var(--bg);border-radius:6px;overflow:hidden;border:1px solid var(--border);margin-bottom:12px}
.coll-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,#1DB954,#16A34A);transition:width 1s ease}
.coll-row{display:flex;justify-content:space-between;font-size:12px;color:var(--light)}

/* TABLES */
table{width:100%;border-collapse:collapse}
th{padding:9px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--light);text-transform:uppercase;letter-spacing:.05em;background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:11px 16px;font-size:13px;color:var(--sub);border-bottom:1px solid #F3F4F6;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFEF7}
.td-main{font-weight:600;color:var(--text)}
.av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
.cc{display:flex;align-items:center;gap:9px}
.badge{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:600}
.badge.exp{background:#FEE2E2;color:#DC2626}
.badge.soon{background:#FEF3C7;color:#D97706}
.badge.ok{background:#DCFCE7;color:#16A34A}
.badge.due{background:#FEE2E2;color:#DC2626}
.empty{padding:32px;text-align:center;font-size:13px;color:var(--light)}
.av-0{background:#1DB954}.av-1{background:#3B82F6}.av-2{background:#8B5CF6}
.av-3{background:#F59E0B}.av-4{background:#EF4444}.av-5{background:#06B6D4}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard.php" class="sb-logo">
        <div class="sb-logo-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </div>
        <span class="sb-logo-name">Sajilo</span>
    </a>
    <nav class="sb-nav">
        <div class="sb-label">Menu</div>
        <a href="../dashboard.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <a href="inventory.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            Products
        </a>
        <a href="customers.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Customers
        </a>
        <a href="record_sale.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Record Sale
        </a>
        <a href="reports.php" class="sb-link active">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            Reports
        </a>
    </nav>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><?= strtoupper(substr($first,0,1)) ?></div>
            <div>
                <div class="sb-uname"><?= htmlspecialchars($name) ?></div>
                <div class="sb-urole">Owner</div>
            </div>
        </div>
        <a href="../logout.php" class="sb-link" style="color:rgba(255,100,100,0.6)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Reports & Analytics</h1>
            <p><?= $label ?> — <?= date('M j', strtotime($from)) ?> to <?= date('M j, Y') ?></p>
        </div>
    </div>

    <div class="content">

        <!-- RANGE PILLS -->
        <div class="range-pills">
            <a href="?range=7"   class="pill <?= $days==7?'on':'' ?>">7 days</a>
            <a href="?range=30"  class="pill <?= $days==30?'on':'' ?>">30 days</a>
            <a href="?range=90"  class="pill <?= $days==90?'on':'' ?>">3 months</a>
            <a href="?range=365" class="pill <?= $days==365?'on':'' ?>">12 months</a>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-lbl">Revenue</span>
                    <div class="stat-icon" style="background:#F0FDF4">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                </div>
                <div class="stat-num g">Rs <?= number_format($revenue) ?></div>
                <div class="stat-sub"><?= $label ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-lbl">Sales Count</span>
                    <div class="stat-icon" style="background:#EFF6FF">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                </div>
                <div class="stat-num b"><?= $total_sales ?></div>
                <div class="stat-sub">Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-lbl">Outstanding</span>
                    <div class="stat-icon" style="background:<?= $outstanding>0?'#FEE2E2':'#F0FDF4' ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $outstanding>0?'#EF4444':'#1DB954' ?>" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                </div>
                <div class="stat-num <?= $outstanding>0?'r':'g' ?>">Rs <?= number_format($outstanding) ?></div>
                <div class="stat-sub">Unpaid balance</div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-lbl">New Customers</span>
                    <div class="stat-icon" style="background:#F5F3FF">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    </div>
                </div>
                <div class="stat-num p"><?= $new_customers ?></div>
                <div class="stat-sub">Joined <?= strtolower($label) ?></div>
            </div>
        </div>

        <!-- ROW 1: Revenue Line Chart + Payment Donut -->
        <div class="row row-3">

            <!-- LINE CHART: Revenue over time -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Revenue Over Time</span>
                    <span class="card-sub"><?= $label ?></span>
                </div>
                <div class="chart-body">
                    <?php if (count($chart_revenue) > 0): ?>
                    <canvas id="lineChart"></canvas>
                    <?php else: ?>
                    <div class="empty">No sales data for this period.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DONUT: Payment collection rate -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Collection Rate</span>
                    <span class="card-sub">All time</span>
                </div>
                <div class="chart-body">
                    <div class="donut-wrap">
                        <canvas id="collectionDonut" style="max-height:200px"></canvas>
                        <div class="donut-center">
                            <div class="donut-center-num"><?= $collection_rate ?>%</div>
                            <div class="donut-center-lbl">Collected</div>
                        </div>
                    </div>
                </div>
                <div class="collection-wrap" style="padding-top:0">
                    <div class="coll-row">
                        <span style="color:var(--green);font-weight:600">Rs <?= number_format($total_collected) ?> collected</span>
                        <span style="color:var(--red);font-weight:600">Rs <?= number_format($total_due_ever) ?> pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 2: Bar Chart (top products) + Category Donut -->
        <div class="row row-2">

            <!-- BAR CHART: Top products -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Top Products by Revenue</span>
                    <span class="card-sub"><?= $label ?></span>
                </div>
                <div class="chart-body">
                    <?php if (count($top_products) > 0): ?>
                    <canvas id="barChart"></canvas>
                    <?php else: ?>
                    <div class="empty">No product data.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DONUT: Sales by category -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Sales by Category</span>
                    <span class="card-sub"><?= $label ?></span>
                </div>
                <div class="chart-body">
                    <?php if (count($by_category) > 0): ?>
                    <div class="donut-wrap">
                        <canvas id="categoryDonut" style="max-height:200px"></canvas>
                        <div class="donut-center">
                            <div class="donut-center-num"><?= array_sum(array_column($by_category,'cnt')) ?></div>
                            <div class="donut-center-lbl">Total Sales</div>
                        </div>
                    </div>
                    <div class="legend">
                        <?php
                        $cat_colors = ['#1DB954','#3B82F6','#8B5CF6','#F59E0B','#EF4444','#06B6D4'];
                        foreach ($by_category as $i => $cat):
                        ?>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:<?= $cat_colors[$i % 6] ?>"></div>
                            <?= htmlspecialchars($cat['cat_name']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty">No category data.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ROW 3: Warranty status donut + top customers -->
        <div class="row row-2">

            <!-- DONUT: Warranty status -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Warranty Status</span>
                    <span class="card-sub">All products sold</span>
                </div>
                <div class="chart-body" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
                    <div class="donut-wrap" style="flex:1;min-width:160px">
                        <canvas id="warrantyDonut" style="max-height:200px"></canvas>
                        <div class="donut-center">
                            <div class="donut-center-num"><?= $w_active ?></div>
                            <div class="donut-center-lbl">Active</div>
                        </div>
                    </div>
                    <div style="flex:1;min-width:140px;display:flex;flex-direction:column;gap:12px">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
                                <div style="width:10px;height:10px;border-radius:50%;background:#1DB954"></div>
                                <span style="font-size:13px;color:var(--sub)">Active</span>
                            </div>
                            <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:#1DB954"><?= $w_active ?></div>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
                                <div style="width:10px;height:10px;border-radius:50%;background:#F59E0B"></div>
                                <span style="font-size:13px;color:var(--sub)">Expiring (30d)</span>
                            </div>
                            <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:#F59E0B"><?= $w_expiring ?></div>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
                                <div style="width:10px;height:10px;border-radius:50%;background:#EF4444"></div>
                                <span style="font-size:13px;color:var(--sub)">Expired</span>
                            </div>
                            <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:#EF4444"><?= $w_expired ?></div>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
                                <div style="width:10px;height:10px;border-radius:50%;background:#E5E7EB"></div>
                                <span style="font-size:13px;color:var(--sub)">No Warranty</span>
                            </div>
                            <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:var(--light)"><?= $w_none ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TOP CUSTOMERS TABLE -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Top Customers</span>
                    <span class="card-sub"><?= strtolower($label) ?></span>
                </div>
                <?php if (count($top_customers) > 0): ?>
                <table>
                    <thead><tr><th>Customer</th><th>Orders</th><th>Spent</th><th>Due</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_customers as $i => $c): ?>
                    <tr>
                        <td>
                            <div class="cc">
                                <div class="av av-<?= $i%6 ?>"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                                <div>
                                    <div class="td-main"><?= htmlspecialchars($c['name']) ?></div>
                                    <?php if($c['phone']): ?><div style="font-size:11px;color:var(--light)"><?= htmlspecialchars($c['phone']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= $c['purchases'] ?></td>
                        <td style="font-weight:700;color:var(--green)">Rs <?= number_format($c['total_spent']) ?></td>
                        <td>
                            <?php if($c['balance_due']>0): ?>
                            <span class="badge due">Rs <?= number_format($c['balance_due']) ?></span>
                            <?php else: ?><span style="font-size:11px;color:var(--light)">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><div class="empty">No data for this period.</div><?php endif; ?>
            </div>
        </div>

        <!-- EXPIRING WARRANTIES TABLE -->
        <?php if (count($expiring) > 0): ?>
        <div class="card">
            <div class="card-hdr">
                <span class="card-title">⚠ Warranties Expiring in 60 Days</span>
                <span class="card-sub"><?= count($expiring) ?> item<?= count($expiring)!=1?'s':'' ?></span>
            </div>
            <table>
                <thead><tr><th>Product</th><th>Model</th><th>Customer</th><th>Phone</th><th>Expires</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($expiring as $w):
                    $d = (int)$w['days_left'];
                    $cls = $d<=7?'exp':($d<=30?'soon':'ok');
                    $lbl = $d<=7?'Critical!':"${d}d left";
                ?>
                <tr>
                    <td class="td-main"><?= htmlspecialchars($w['product_name']) ?></td>
                    <td style="font-size:12px;font-family:monospace;color:var(--light)"><?= htmlspecialchars($w['model_no']??'—') ?></td>
                    <td><?= htmlspecialchars($w['customer_name']??'—') ?></td>
                    <td style="font-size:12px;color:var(--light)"><?= htmlspecialchars($w['phone']??'—') ?></td>
                    <td><?= date('M j, Y', strtotime($w['warranty_expiry'])) ?></td>
                    <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// ── CHART.JS GLOBAL DEFAULTS ──
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.plugins.legend.display = false;
Chart.defaults.plugins.tooltip.backgroundColor = '#111827';
Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };
Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;

const green   = '#1DB954';
const greenDk = '#16A34A';
const blue    = '#3B82F6';
const purple  = '#8B5CF6';
const amber   = '#F59E0B';
const red     = '#EF4444';
const teal    = '#06B6D4';

// ── 1. LINE CHART: Revenue over time ──
<?php if (count($chart_revenue) > 0): ?>
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode($chart_revenue) ?>,
            borderColor: green,
            backgroundColor: 'rgba(29,185,84,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: green,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: {
            x: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { font: { size: 11 }, color: '#9CA3AF' }
            },
            y: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: {
                    font: { size: 11 }, color: '#9CA3AF',
                    callback: v => 'Rs ' + v.toLocaleString('en-IN')
                },
                beginAtZero: true
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => ' Rs ' + ctx.raw.toLocaleString('en-IN')
                }
            }
        }
    }
});
<?php endif; ?>

// ── 2. DONUT: Collection rate ──
new Chart(document.getElementById('collectionDonut'), {
    type: 'doughnut',
    data: {
        datasets: [{
            data: [<?= $total_collected ?>, <?= max(0, $total_due_ever) ?>],
            backgroundColor: [green, '#FEE2E2'],
            borderColor: ['#fff','#fff'],
            borderWidth: 3,
            hoverOffset: 6
        }]
    },
    options: {
        cutout: '72%',
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => [' Collected',' Pending'][ctx.dataIndex] + ': Rs ' + ctx.raw.toLocaleString('en-IN')
                }
            }
        }
    }
});

// ── 3. BAR CHART: Top products ──
<?php if (count($top_products) > 0): ?>
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($p) => strlen($p['product_name'])>18 ? substr($p['product_name'],0,18).'…' : $p['product_name'], $top_products)) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode(array_column($top_products, 'total_revenue')) ?>,
            backgroundColor: [green, blue, purple, amber, red, teal],
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 }, color: '#9CA3AF' }
            },
            y: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: {
                    font: { size: 11 }, color: '#9CA3AF',
                    callback: v => 'Rs ' + v.toLocaleString('en-IN')
                },
                beginAtZero: true
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => ' Rs ' + ctx.raw.toLocaleString('en-IN')
                }
            }
        }
    }
});
<?php endif; ?>

// ── 4. DONUT: Sales by category ──
<?php if (count($by_category) > 0): ?>
new Chart(document.getElementById('categoryDonut'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($by_category,'cat_name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($by_category,'cnt')) ?>,
            backgroundColor: [green, blue, purple, amber, red, teal],
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 8
        }]
    },
    options: {
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.raw + ' sales'
                }
            }
        }
    }
});
<?php endif; ?>

// ── 5. DONUT: Warranty status ──
new Chart(document.getElementById('warrantyDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Expiring Soon', 'Expired', 'No Warranty'],
        datasets: [{
            data: [
                <?= max(0, $w_active - $w_expiring) ?>,
                <?= $w_expiring ?>,
                <?= $w_expired ?>,
                <?= $w_none ?>
            ],
            backgroundColor: [green, amber, red, '#E5E7EB'],
            borderColor: '#fff',
            borderWidth: 3,
            hoverOffset: 8
        }]
    },
    options: {
        cutout: '70%',
        plugins: {
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.label + ': ' + ctx.raw
                }
            }
        }
    }
});
</script>

</body>
</html>