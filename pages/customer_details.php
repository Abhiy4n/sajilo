<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
$active = 'customers';
require_once '../includes/sidebar.php';

$uid = $_SESSION['user_id'];
$msg = '';
$err = '';

// Validate customer ID
$cid = (int)($_GET['id'] ?? 0);
if ($cid === 0) {
    header('Location: customers.php');
    exit;
}

// Fetch customer — must belong to this user
$stmt = $conn->prepare("SELECT * FROM customers WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $cid, $uid);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// ── RECORD PAYMENT (update balance) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $sid     = (int)$_POST['sale_id'];
    $payment = (float)$_POST['payment_amount'];

    if ($payment <= 0) {
        $err = 'Payment amount must be greater than 0.';
    } else {
        // Get current sale
        $s = $conn->prepare("SELECT balance_due, amount_paid FROM sales WHERE id=? AND user_id=?");
        $s->bind_param("ii", $sid, $uid);
        $s->execute();
        $sale = $s->get_result()->fetch_assoc();
        $s->close();

        if ($sale) {
            $new_paid    = $sale['amount_paid'] + $payment;
            $new_balance = max(0, $sale['balance_due'] - $payment);

            $u = $conn->prepare("UPDATE sales SET amount_paid=?, balance_due=? WHERE id=? AND user_id=?");
            $u->bind_param("ddii", $new_paid, $new_balance, $sid, $uid);
            if ($u->execute()) $msg = 'Payment of Rs ' . number_format($payment) . ' recorded.';
            else $err = 'Failed to record payment.';
            $u->close();
        }
    }
}

// ── DELETE SALE ──
if (isset($_GET['del_sale']) && is_numeric($_GET['del_sale'])) {
    $sid = (int)$_GET['del_sale'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE id=? AND user_id=? AND customer_id=?");
    $stmt->bind_param("iii", $sid, $uid, $cid);
    $stmt->execute();
    $stmt->close();
    $msg = 'Sale deleted.';
    header("Location: customer_detail.php?id=$cid&msg=deleted");
    exit;
}

// ── FETCH SALES for this customer ──
$sales = $conn->query("
    SELECT *,
           DATEDIFF(warranty_expiry, CURDATE()) as days_left
    FROM sales
    WHERE customer_id = $cid AND user_id = $uid
    ORDER BY sale_date DESC, id DESC
");

$sales_arr = $sales->fetch_all(MYSQLI_ASSOC);
$total_sales    = count($sales_arr);
$total_spent    = array_sum(array_column($sales_arr, 'total_price'));
$total_paid     = array_sum(array_column($sales_arr, 'amount_paid'));
$total_due      = array_sum(array_column($sales_arr, 'balance_due'));
$active_warranties = count(array_filter($sales_arr, fn($s) =>
    $s['warranty_expiry'] && $s['warranty_expiry'] >= date('Y-m-d')
));
$expiring_soon = count(array_filter($sales_arr, fn($s) =>
    $s['warranty_expiry'] &&
    $s['warranty_expiry'] >= date('Y-m-d') &&
    $s['days_left'] <= 30
));

// Avatar color
$av_colors = ['#1DB954','#3B82F6','#8B5CF6','#F59E0B','#EF4444','#06B6D4','#EC4899','#84CC16'];
$av_color  = $av_colors[ord(strtoupper($customer['name'][0])) % 8];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sajilo — <?= htmlspecialchars($customer['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
    --sidebar:#0D1F0D;--green:#1DB954;--green-dark:#16A34A;
    --white:#fff;--bg:#F8FAF8;--border:#E4EDE4;
    --text:#0F1A0F;--sub:#6B7D6B;--light:#9CA3AF;
    --red:#EF4444;--amber:#F59E0B;
    --sh:0 1px 3px rgba(0,0,0,0.07);
    --sh-md:0 4px 16px rgba(0,0,0,0.08);
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
.topbar-left{display:flex;align-items:center;gap:12px}
.back-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--sub);text-decoration:none;padding:7px 12px;border-radius:8px;border:1.5px solid var(--border);transition:all .2s}
.back-btn:hover{border-color:var(--green);color:var(--green)}
.topbar-right{display:flex;align-items:center;gap:10px}
.btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.btn-green{background:var(--green);color:#fff}
.btn-green:hover{background:var(--green-dark)}
.btn-outline{background:var(--white);color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{border-color:var(--green);color:var(--green)}
.content{padding:28px 32px;flex:1}

/* ALERTS */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500}
.alert.ok{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0}
.alert.err{background:#FEF2F2;color:var(--red);border:1px solid #FECACA}

/* PROFILE HEADER */
.profile-header{
    background:var(--white);border:1px solid var(--border);
    border-radius:20px;padding:28px 32px;
    display:flex;align-items:center;gap:24px;
    margin-bottom:24px;box-shadow:var(--sh);
    flex-wrap:wrap;
}
.profile-avatar{
    width:72px;height:72px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-family:'Bricolage Grotesque',sans-serif;
    font-size:28px;font-weight:800;color:#fff;
    flex-shrink:0;
}
.profile-info{flex:1}
.profile-name{
    font-family:'Bricolage Grotesque',sans-serif;
    font-size:26px;font-weight:800;color:var(--text);margin-bottom:6px;
}
.profile-meta{display:flex;flex-wrap:wrap;gap:16px}
.profile-meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--sub)}
.profile-meta-item svg{opacity:.5}
.profile-actions{display:flex;gap:10px;flex-wrap:wrap}

/* STAT CARDS */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:18px 20px;box-shadow:var(--sh)}
.stat-label{font-size:12px;color:var(--sub);margin-bottom:5px}
.stat-val{font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:var(--text)}
.stat-val.g{color:var(--green)}.stat-val.r{color:var(--red)}.stat-val.a{color:var(--amber)}

/* PAGE LAYOUT */
.page-grid{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start}

/* SALES TABLE */
.card{background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--sh)}
.card-hdr{padding:17px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--light);text-transform:uppercase;letter-spacing:.05em;background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 16px;font-size:13px;color:var(--sub);border-bottom:1px solid #F3F4F6;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFEF7}
.td-main{font-weight:600;color:var(--text);font-size:14px}
.td-model{font-size:11px;color:var(--light);font-family:monospace;margin-top:1px}

/* BADGES */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:600;white-space:nowrap}
.badge.paid{background:#DCFCE7;color:#16A34A}
.badge.due{background:#FEE2E2;color:#DC2626}
.badge.w-ok{background:#DCFCE7;color:#16A34A}
.badge.w-soon{background:#FEF3C7;color:#D97706}
.badge.w-exp{background:#FEE2E2;color:#DC2626}
.badge.w-none{background:#F3F4F6;color:var(--light)}

/* ACTIONS */
.actions{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.btn-sm{font-size:11px;padding:4px 10px;border-radius:7px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;transition:all .2s}
.btn-pay{background:#F0FDF4;color:var(--green)}.btn-pay:hover{background:#DCFCE7}
.btn-edit-s{background:#EFF6FF;color:#3B82F6}.btn-edit-s:hover{background:#DBEAFE}
.btn-del{background:#FEE2E2;color:var(--red)}.btn-del:hover{background:#FECACA}

/* RIGHT SIDEBAR CARDS */
.info-card{background:var(--white);border:1px solid var(--border);border-radius:16px;padding:22px;box-shadow:var(--sh);margin-bottom:16px}
.info-card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:14px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.info-row{display:flex;flex-direction:column;gap:2px;margin-bottom:12px}
.info-row:last-child{margin-bottom:0}
.info-row-label{font-size:11px;font-weight:600;color:var(--light);text-transform:uppercase;letter-spacing:.05em}
.info-row-val{font-size:14px;color:var(--text);font-weight:500}
.info-row-val.empty{color:var(--light);font-style:italic}

/* WARRANTY LIST */
.w-item{padding:12px;background:var(--bg);border-radius:10px;margin-bottom:8px;border:1px solid var(--border)}
.w-item:last-child{margin-bottom:0}
.w-item-name{font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px}
.w-item-date{font-size:11px;color:var(--light)}
.w-item-top{display:flex;justify-content:space-between;align-items:flex-start}

/* PAYMENT MODAL */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.modal{background:var(--white);border-radius:20px;width:100%;max-width:420px;box-shadow:0 24px 80px rgba(0,0,0,0.2)}
.modal-hdr{padding:22px 26px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-family:'Bricolage Grotesque',sans-serif;font-size:18px;font-weight:800}
.modal-close{width:32px;height:32px;border:none;background:var(--bg);border-radius:8px;cursor:pointer;font-size:16px;color:var(--sub)}
.modal-body{padding:24px 26px}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.field label{font-size:12px;font-weight:600;color:var(--text)}
.field input{padding:11px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:15px;font-family:'DM Sans',sans-serif;color:var(--text);background:#FAFAFA;outline:none;transition:all .2s}
.field input:focus{border-color:var(--green);background:var(--white);box-shadow:0 0 0 3px rgba(29,185,84,0.08)}
.modal-footer{padding:16px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
.empty-state{padding:48px 20px;text-align:center;color:var(--light)}
.empty-state svg{margin:0 auto 12px;display:block;opacity:.2}
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
        <a href="customers.php" class="sb-link active">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Customers
        </a>
        <a href="record_sale.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Record Sale
        </a>
        <a href="reports.php" class="sb-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            Reports
        </a>
    </nav>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
            <div>
                <div class="sb-uname"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="sb-urole">Owner</div>
            </div>
        </div>
        <a href="../logout.php" class="sb-link" style="color:rgba(255,100,100,0.6)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <a href="customers.php" class="back-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                Customers
            </a>
        </div>
        <div class="topbar-right">
            <a href="record_sale.php" class="btn btn-green">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Sale
            </a>
        </div>
    </div>

    <div class="content">

        <?php if ($msg): ?><div class="alert ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert err">⚠ <?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- PROFILE HEADER -->
        <div class="profile-header">
            <div class="profile-avatar" style="background:<?= $av_color ?>">
                <?= strtoupper(substr($customer['name'],0,1)) ?>
            </div>
            <div class="profile-info">
                <div class="profile-name"><?= htmlspecialchars($customer['name']) ?></div>
                <div class="profile-meta">
                    <?php if ($customer['phone']): ?>
                    <span class="profile-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.5 16.9"/></svg>
                        <?= htmlspecialchars($customer['phone']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($customer['email']): ?>
                    <span class="profile-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <?= htmlspecialchars($customer['email']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($customer['address']): ?>
                    <span class="profile-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= htmlspecialchars($customer['address']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="profile-meta-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Customer since <?= date('M Y', strtotime($customer['created_at'])) ?>
                    </span>
                </div>
            </div>
            <div class="profile-actions">
                <a href="customers.php?edit=<?= $customer['id'] ?>" class="btn btn-outline">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </a>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total Purchases</div>
                <div class="stat-val"><?= $total_sales ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-val g">Rs <?= number_format($total_spent) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Amount Paid</div>
                <div class="stat-val g">Rs <?= number_format($total_paid) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Balance Due</div>
                <div class="stat-val <?= $total_due > 0 ? 'r' : 'g' ?>">
                    Rs <?= number_format($total_due) ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Warranties</div>
                <div class="stat-val <?= $expiring_soon > 0 ? 'a' : 'g' ?>">
                    <?= $active_warranties ?>
                    <?php if ($expiring_soon > 0): ?>
                    <span style="font-size:13px;color:var(--amber)"> (<?= $expiring_soon ?> expiring)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="page-grid">

            <!-- SALES TABLE -->
            <div class="card">
                <div class="card-hdr">
                    <span class="card-title">Purchase History</span>
                    <span style="font-size:12px;color:var(--light)"><?= $total_sales ?> record<?= $total_sales != 1 ? 's' : '' ?></span>
                </div>
                <?php if ($total_sales > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Warranty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sales_arr as $s): ?>
                    <tr>
                        <td>
                            <div class="td-main"><?= htmlspecialchars($s['product_name']) ?></div>
                            <?php if ($s['model_no']): ?>
                            <div class="td-model"><?= htmlspecialchars($s['model_no']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($s['sale_date'])) ?></td>
                        <td style="font-weight:600;color:var(--text)">Rs <?= number_format($s['total_price']) ?></td>
                        <td>Rs <?= number_format($s['amount_paid']) ?></td>
                        <td>
                            <?php if ($s['balance_due'] > 0): ?>
                                <span class="badge due">Rs <?= number_format($s['balance_due']) ?></span>
                            <?php else: ?>
                                <span class="badge paid">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$s['warranty_expiry']): ?>
                                <span class="badge w-none">None</span>
                            <?php elseif ($s['warranty_expiry'] < date('Y-m-d')): ?>
                                <span class="badge w-exp">Expired</span>
                            <?php elseif ($s['days_left'] <= 30): ?>
                                <span class="badge w-soon"><?= $s['days_left'] ?>d left</span>
                            <?php else: ?>
                                <span class="badge w-ok">
                                    <?= date('M Y', strtotime($s['warranty_expiry'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($s['balance_due'] > 0): ?>
                                <button class="btn-sm btn-pay"
                                    onclick="openPayment(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['product_name'])) ?>', <?= $s['balance_due'] ?>)">
                                    + Pay
                                </button>
                                <?php endif; ?>
                                <a href="?id=<?= $cid ?>&del_sale=<?= $s['id'] ?>"
                                   class="btn-sm btn-del"
                                   onclick="return confirm('Delete this sale record?')">Del</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <p style="font-size:14px;margin-bottom:10px">No purchases recorded yet.</p>
                    <a href="record_sale.php" class="btn btn-green">Record First Sale</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT PANEL -->
            <div>
                <!-- Contact Info -->
                <div class="info-card">
                    <div class="info-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Contact Info
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Phone</span>
                        <span class="info-row-val <?= $customer['phone'] ? '' : 'empty' ?>">
                            <?= $customer['phone'] ? htmlspecialchars($customer['phone']) : 'Not provided' ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Email</span>
                        <span class="info-row-val <?= $customer['email'] ? '' : 'empty' ?>">
                            <?= $customer['email'] ? htmlspecialchars($customer['email']) : 'Not provided' ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Address</span>
                        <span class="info-row-val <?= $customer['address'] ? '' : 'empty' ?>">
                            <?= $customer['address'] ? htmlspecialchars($customer['address']) : 'Not provided' ?>
                        </span>
                    </div>
                </div>

                <!-- Active Warranties -->
                <div class="info-card">
                    <div class="info-card-title">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Active Warranties
                    </div>
                    <?php
                    $active_w = array_filter($sales_arr, fn($s) =>
                        $s['warranty_expiry'] && $s['warranty_expiry'] >= date('Y-m-d')
                    );
                    if (count($active_w) > 0):
                    foreach ($active_w as $s):
                        $d = (int)$s['days_left'];
                        $cls = $d <= 30 ? 'w-soon' : 'w-ok';
                    ?>
                    <div class="w-item">
                        <div class="w-item-top">
                            <div class="w-item-name"><?= htmlspecialchars($s['product_name']) ?></div>
                            <span class="badge <?= $cls ?>"><?= $d ?>d left</span>
                        </div>
                        <div class="w-item-date">
                            Expires <?= date('M j, Y', strtotime($s['warranty_expiry'])) ?>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                    <p style="font-size:13px;color:var(--light)">No active warranties.</p>
                    <?php endif; ?>
                </div>

                <!-- Balance Summary -->
                <?php if ($total_due > 0): ?>
                <div class="info-card" style="border-color:#FECACA;background:#FEF2F2">
                    <div class="info-card-title" style="color:var(--red)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Outstanding Balance
                    </div>
                    <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:28px;font-weight:800;color:var(--red);margin-bottom:6px">
                        Rs <?= number_format($total_due) ?>
                    </div>
                    <p style="font-size:12px;color:#B91C1C">
                        Balance due across <?= count(array_filter($sales_arr, fn($s) => $s['balance_due'] > 0)) ?> sale(s).
                        Click "+ Pay" on any row to record a payment.
                    </p>
                </div>
                <?php else: ?>
                <div class="info-card" style="border-color:#BBF7D0;background:#F0FDF4">
                    <div class="info-card-title" style="color:var(--green)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1DB954" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        All Cleared
                    </div>
                    <p style="font-size:13px;color:#16A34A">No outstanding balance. All payments received.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div class="overlay" id="pay-modal" style="display:none">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Record Payment</span>
            <button class="modal-close" onclick="closeModal('pay-modal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="sale_id" id="pay-sale-id">
            <div class="modal-body">
                <p style="font-size:13px;color:var(--sub);margin-bottom:16px" id="pay-product-label"></p>
                <div class="field">
                    <label>Balance Due</label>
                    <input type="text" id="pay-balance-display" readonly
                           style="background:#F3F4F6;color:var(--sub);cursor:default">
                </div>
                <div class="field">
                    <label>Payment Amount (Rs) *</label>
                    <input type="number" step="0.01" name="payment_amount"
                           id="pay-amount" placeholder="Enter amount received" required min="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('pay-modal')">Cancel</button>
                <button type="submit" name="record_payment" class="btn btn-green">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayment(saleId, productName, balanceDue) {
    document.getElementById('pay-sale-id').value = saleId;
    document.getElementById('pay-product-label').textContent = 'Product: ' + productName;
    document.getElementById('pay-balance-display').value = 'Rs ' + balanceDue.toLocaleString('en-IN');
    document.getElementById('pay-amount').value = balanceDue;
    document.getElementById('pay-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('pay-amount').select(), 100);
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}
document.querySelectorAll('.overlay').forEach(o => {
    o.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});
</script>
</body>
</html>