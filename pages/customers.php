<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/csrf.php';

$uid = $_SESSION['user_id'];
$msg = '';
$err = '';

// ── ADD CUSTOMER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid request. Please try again.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $err = 'Customer name is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (user_id, name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $uid, $name, $phone, $email, $address);
            if ($stmt->execute()) $msg = 'Customer added successfully.';
            else $err = 'Failed to add customer.';
            $stmt->close();
        }
    }
}

// ── EDIT CUSTOMER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = 'Invalid request. Please try again.';
    } else {
        $cid     = (int)$_POST['cid'];
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name)) {
            $err = 'Customer name is required.';
        } else {
            $stmt = $conn->prepare("UPDATE customers SET name=?, phone=?, email=?, address=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssssii", $name, $phone, $email, $address, $cid, $uid);
            if ($stmt->execute()) $msg = 'Customer updated.';
            else $err = 'Failed to update.';
            $stmt->close();
        }
    }
}

// ── DELETE CUSTOMER ──
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf($_GET['csrf_token'])) {
        $err = 'Invalid request. Please try again.';
    } else {
        $del_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM customers WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $del_id, $uid);
        $stmt->execute();
        $stmt->close();
        $msg = 'Customer deleted.';
    }
}

// ── SEARCH ──
$search = trim($_GET['q'] ?? '');
$where  = "WHERE c.user_id = $uid";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $like   = "%$search%";
    $params = [$like, $like, $like];
    $types  = 'sss';
}

$sql = "SELECT c.*,
        COUNT(s.id) as total_sales,
        COALESCE(SUM(s.total_price), 0) as total_spent,
        COALESCE(SUM(s.balance_due), 0) as total_due,
        MAX(s.sale_date) as last_purchase
        FROM customers c
        LEFT JOIN sales s ON s.customer_id = c.id AND s.user_id = $uid
        $where
        GROUP BY c.id
        ORDER BY c.name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}

// ── Fetch all rows immediately and free result ──
$all = $result->fetch_all(MYSQLI_ASSOC);
$result->free();

$total_cust    = count($all);
$total_revenue = array_sum(array_column($all, 'total_spent'));
$total_due     = array_sum(array_column($all, 'total_due'));
$with_due      = count(array_filter($all, fn($c) => $c['total_due'] > 0));

// ── For edit modal — AFTER result is freed ──
$edit_customer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $edit_id, $uid);
    $stmt->execute();                                    // ← was missing before
    $edit_customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sajilo — Customers</title>
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
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--white);border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40}
.topbar h1{font-family:'Bricolage Grotesque',sans-serif;font-size:19px;font-weight:800}
.topbar p{font-size:12px;color:var(--sub);margin-top:1px}
.topbar-right{display:flex;align-items:center;gap:10px}
.btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.btn-green{background:var(--green);color:#fff}
.btn-green:hover{background:var(--green-dark)}
.btn-outline{background:var(--white);color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{border-color:var(--green);color:var(--green)}
.btn-sm-red{background:#FEE2E2;color:var(--red);font-size:12px;padding:5px 11px;border-radius:7px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;transition:all .2s}
.btn-sm-red:hover{background:#FECACA}
.btn-sm-edit{background:#F0FDF4;color:var(--green);font-size:12px;padding:5px 11px;border-radius:7px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;transition:all .2s}
.btn-sm-edit:hover{background:#DCFCE7}
.btn-sm-blue{background:#EFF6FF;color:#3B82F6;font-size:12px;padding:5px 11px;border-radius:7px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;transition:all .2s}
.btn-sm-blue:hover{background:#DBEAFE}
.content{padding:28px 32px;flex:1}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500}
.alert.ok{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0}
.alert.err{background:#FEF2F2;color:var(--red);border:1px solid #FECACA}
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:220px}
.search-wrap input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;background:var(--white);outline:none;transition:border-color .2s}
.search-wrap input:focus{border-color:var(--green)}
.search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:.4;pointer-events:none}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.mini-stat{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:16px 18px;box-shadow:var(--sh)}
.mini-stat-label{font-size:12px;color:var(--sub);margin-bottom:4px}
.mini-stat-val{font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800;color:var(--text)}
.mini-stat-val.g{color:var(--green)}
.mini-stat-val.r{color:var(--red)}
.card{background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--sh)}
.card-hdr{padding:17px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700}
.card-count{font-size:12px;color:var(--light);background:var(--bg);padding:4px 10px;border-radius:50px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--light);text-transform:uppercase;letter-spacing:.05em;background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:12px 16px;font-size:13px;color:var(--sub);border-bottom:1px solid #F3F4F6;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFEF7}
.td-main{font-weight:600;color:var(--text);font-size:14px}
.td-phone{font-size:12px;color:var(--light);margin-top:2px}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.customer-cell{display:flex;align-items:center;gap:10px}
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:600}
.badge.ok{background:#DCFCE7;color:#16A34A}
.badge.due{background:#FEE2E2;color:#DC2626}
.badge.none{background:#F3F4F6;color:var(--light)}
.actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.empty{padding:60px 20px;text-align:center;color:var(--light)}
.empty svg{margin:0 auto 12px;display:block;opacity:.25}
.empty p{font-size:14px;margin-bottom:14px}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.modal{background:var(--white);border-radius:20px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,0.2)}
.modal-hdr{padding:22px 26px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1}
.modal-title{font-family:'Bricolage Grotesque',sans-serif;font-size:18px;font-weight:800}
.modal-close{width:32px;height:32px;border:none;background:var(--bg);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--sub);transition:all .2s;text-decoration:none}
.modal-close:hover{background:var(--border)}
.modal-body{padding:24px 26px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-grid .full{grid-column:1/-1}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:12px;font-weight:600;color:var(--text)}
.field input,.field textarea{padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;color:var(--text);background:#FAFAFA;outline:none;transition:all .2s}
.field input:focus,.field textarea:focus{border-color:var(--green);background:var(--white);box-shadow:0 0 0 3px rgba(29,185,84,0.08)}
.field input::placeholder,.field textarea::placeholder{color:#C4C4C4}
.field textarea{resize:vertical;min-height:70px}
.modal-footer{padding:16px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
.av-0{background:#1DB954}.av-1{background:#3B82F6}.av-2{background:#8B5CF6}
.av-3{background:#F59E0B}.av-4{background:#EF4444}.av-5{background:#06B6D4}
.av-6{background:#EC4899}.av-7{background:#84CC16}
</style>
</head>
<body>

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

<div class="main">
    <div class="topbar">
        <div>
            <h1>Customers</h1>
            <p>Manage your customer base and balances</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-green" onclick="openModal('add-modal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Customer
            </button>
        </div>
    </div>

    <div class="content">
        <?php if ($msg): ?><div class="alert ok">✓ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert err">⚠ <?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- MINI STATS -->
        <div class="stats-row">
            <div class="mini-stat">
                <div class="mini-stat-label">Total Customers</div>
                <div class="mini-stat-val"><?= $total_cust ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Total Revenue</div>
                <div class="mini-stat-val g">Rs <?= number_format($total_revenue) ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">Outstanding Balance</div>
                <div class="mini-stat-val <?= $total_due > 0 ? 'r' : 'g' ?>">Rs <?= number_format($total_due) ?></div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-label">With Pending Dues</div>
                <div class="mini-stat-val <?= $with_due > 0 ? 'r' : 'g' ?>"><?= $with_due ?></div>
            </div>
        </div>

        <!-- SEARCH -->
        <form method="GET" class="toolbar">
            <div class="search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="q" placeholder="Search by name, phone or email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if ($search): ?>
            <a href="customers.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
            <button type="submit" style="display:none"></button>
        </form>

        <!-- TABLE -->
        <div class="card">
            <div class="card-hdr">
                <span class="card-title">All Customers</span>
                <span class="card-count"><?= $total_cust ?> customer<?= $total_cust != 1 ? 's' : '' ?></span>
            </div>

            <?php if ($total_cust > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Purchases</th>
                        <th>Total Spent</th>
                        <th>Balance Due</th>
                        <th>Last Purchase</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all as $i => $c): ?>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="avatar av-<?= $i % 8 ?>"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                            <div>
                                <div class="td-main"><?= htmlspecialchars($c['name']) ?></div>
                                <?php if ($c['email']): ?>
                                <div class="td-phone"><?= htmlspecialchars($c['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                    <td><?= $c['total_sales'] ?> sale<?= $c['total_sales'] != 1 ? 's' : '' ?></td>
                    <td>Rs <?= number_format($c['total_spent']) ?></td>
                    <td>
                        <?php if ($c['total_due'] > 0): ?>
                            <span class="badge due">Rs <?= number_format($c['total_due']) ?></span>
                        <?php elseif ($c['total_sales'] > 0): ?>
                            <span class="badge ok">Cleared</span>
                        <?php else: ?>
                            <span class="badge none">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['last_purchase'] ? date('M j, Y', strtotime($c['last_purchase'])) : '—' ?></td>
                    <td>
                        <div class="actions">
                            <a href="customer_details.php?id=<?= $c['id'] ?>" class="btn-sm-blue">View</a>
                            <a href="?edit=<?= $c['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn-sm-edit">Edit</a>
                            <a href="?delete=<?= $c['id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                               class="btn-sm-red"
                               onclick="return confirm('Delete this customer?\nThis will NOT delete their sales records.')">
                               Delete
                             </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <p><?= $search ? 'No customers match your search.' : 'No customers yet.' ?></p>
                <?php if (!$search): ?>
                <button class="btn btn-green" onclick="openModal('add-modal')">Add your first customer</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div class="overlay" id="add-modal" style="display:none">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Add Customer</span>
            <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field full">
                        <label>Full Name *</label>
                        <input type="text" name="name" placeholder="Customer full name" required>
                    </div>
                    <div class="field">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="98XXXXXXXX">
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@example.com">
                    </div>
                    <div class="field full">
                        <label>Address</label>
                        <textarea name="address" placeholder="Street, City..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('add-modal')">Cancel</button>
                <button type="submit" name="add_customer" class="btn btn-green">Add Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<?php if ($edit_customer): ?>
<div class="overlay" id="edit-modal" style="display:flex">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Edit Customer</span>
            <a href="customers.php<?= $search ? '?q='.urlencode($search) : '' ?>" class="modal-close">✕</a>
        </div>
        <form method="POST">
            <input type="hidden" name="cid" value="<?= $edit_customer['id'] ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_customer['name']) ?>" required>
                    </div>
                    <div class="field">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($edit_customer['phone'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($edit_customer['email'] ?? '') ?>">
                    </div>
                    <div class="field full">
                        <label>Address</label>
                        <textarea name="address"><?= htmlspecialchars($edit_customer['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="customers.php<?= $search ? '?q='.urlencode($search) : '' ?>" class="btn btn-outline">Cancel</a>
                <button type="submit" name="edit_customer" class="btn btn-green">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
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