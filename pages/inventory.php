<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
$active = 'inventory';
require_once '../includes/sidebar.php';

$uid = $_SESSION['user_id'];
$msg = '';
$err = '';

// ── ADD PRODUCT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name          = trim($_POST['name'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $model_no      = trim($_POST['model_no'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $supplier_phone= trim($_POST['supplier_phone'] ?? '');
    $cost_price    = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $quantity      = (int)($_POST['quantity'] ?? 0);

    if (empty($name)) {
        $err = 'Product name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO products
            (user_id, category_id, name, brand, model_no, supplier_name, supplier_phone, cost_price, selling_price, quantity)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $cat = $category_id ?: null;
        $stmt->bind_param("iisssssddi", $uid, $cat, $name, $brand, $model_no,
            $supplier_name, $supplier_phone, $cost_price, $selling_price, $quantity);
        if ($stmt->execute()) $msg = 'Product added successfully.';
        else $err = 'Failed to add product.';
        $stmt->close();
    }
}

// ── EDIT PRODUCT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $pid           = (int)$_POST['pid'];
    $name          = trim($_POST['name'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $model_no      = trim($_POST['model_no'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $supplier_phone= trim($_POST['supplier_phone'] ?? '');
    $cost_price    = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);
    $quantity      = (int)($_POST['quantity'] ?? 0);

    $stmt = $conn->prepare("UPDATE products SET
        name=?, brand=?, model_no=?, category_id=?,
        supplier_name=?, supplier_phone=?,
        cost_price=?, selling_price=?, quantity=?
        WHERE id=? AND user_id=?");
    $cat = $category_id ?: null;
    $stmt->bind_param("sssississii", $name, $brand, $model_no, $cat,
        $supplier_name, $supplier_phone, $cost_price, $selling_price, $quantity, $pid, $uid);
    if ($stmt->execute()) $msg = 'Product updated.';
    else $err = 'Failed to update.';
    $stmt->close();
}

// ── DELETE PRODUCT ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $pid, $uid);
    $stmt->execute();
    $stmt->close();
    $msg = 'Product deleted.';
}

// ── ADD CATEGORY ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $cname = trim($_POST['category_name'] ?? '');
    if (!empty($cname)) {
        $stmt = $conn->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $uid, $cname);
        $stmt->execute();
        $stmt->close();
        $msg = 'Category added.';
    }
}

// ── DELETE CATEGORY ──
if (isset($_GET['del_cat']) && is_numeric($_GET['del_cat'])) {
    $cid = (int)$_GET['del_cat'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $cid, $uid);
    $stmt->execute();
    $stmt->close();
    $msg = 'Category deleted.';
}

// ── FETCH CATEGORIES ──
$cats = $conn->query("SELECT * FROM categories WHERE user_id=$uid ORDER BY name");

// ── FETCH PRODUCTS with search + filter ──
$search = trim($_GET['q'] ?? '');
$filter_cat = (int)($_GET['cat'] ?? 0);

$where = "WHERE p.user_id=$uid";
$params = [];
$types  = '';

if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.model_no LIKE ? OR p.brand LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types  = 'sss';
}
if ($filter_cat > 0) {
    $where .= " AND p.category_id=$filter_cat";
}

$sql = "SELECT p.*, c.name as cat_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where ORDER BY p.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $products = $stmt->get_result();
    $stmt->close();
} else {
    $products = $conn->query($sql);
}

$total_products = $products->num_rows;

// For edit modal — fetch single product
$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", (int)$_GET['edit'], $uid);
    $stmt->execute();
    $edit_product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sajilo — Inventory</title>
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

/* ── SIDEBAR (same as dashboard) ── */
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

/* ── MAIN ── */
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
.btn-red{background:#FEE2E2;color:var(--red);font-size:12px;padding:6px 12px}
.btn-red:hover{background:#FECACA}
.btn-edit{background:#F0FDF4;color:var(--green);font-size:12px;padding:6px 12px}
.btn-edit:hover{background:#DCFCE7}
.content{padding:28px 32px;flex:1}

/* ALERTS */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:500}
.alert.ok{background:#F0FDF4;color:#16A34A;border:1px solid #BBF7D0}
.alert.err{background:#FEF2F2;color:var(--red);border:1px solid #FECACA}

/* TOOLBAR */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:200px}
.search-wrap input{width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;background:var(--white);outline:none;transition:border-color .2s}
.search-wrap input:focus{border-color:var(--green)}
.search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:.4}
.filter-select{padding:10px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;background:var(--white);outline:none;cursor:pointer;transition:border-color .2s}
.filter-select:focus{border-color:var(--green)}

/* TABLE CARD */
.card{background:var(--white);border:1px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:var(--sh)}
.card-hdr{padding:17px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700}
.card-count{font-size:12px;color:var(--light);background:var(--bg);padding:4px 10px;border-radius:50px;border:1px solid var(--border)}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--light);text-transform:uppercase;letter-spacing:.05em;background:var(--bg);border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:11px 16px;font-size:13px;color:var(--sub);border-bottom:1px solid #F3F4F6;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#FAFEF7}
.td-main{font-weight:600;color:var(--text)}
.td-model{font-size:12px;color:var(--light);font-family:monospace}
.stock-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:600}
.stock-ok{background:#DCFCE7;color:#16A34A}
.stock-low{background:#FEF3C7;color:#D97706}
.stock-out{background:#FEE2E2;color:#DC2626}
.actions{display:flex;align-items:center;gap:6px}
.empty{padding:60px 20px;text-align:center;color:var(--light)}
.empty svg{margin:0 auto 12px;display:block;opacity:.25}
.empty p{font-size:14px;margin-bottom:12px}

/* ── MODAL ── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.modal{background:var(--white);border-radius:20px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,0.2)}
.modal-hdr{padding:22px 26px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1}
.modal-title{font-family:'Bricolage Grotesque',sans-serif;font-size:18px;font-weight:800}
.modal-close{width:32px;height:32px;border:none;background:var(--bg);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--sub);transition:all .2s}
.modal-close:hover{background:var(--border)}
.modal-body{padding:24px 26px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-grid .full{grid-column:1/-1}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:12px;font-weight:600;color:var(--text)}
.field input,.field select{padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:'DM Sans',sans-serif;color:var(--text);background:#FAFAFA;outline:none;transition:all .2s}
.field input:focus,.field select:focus{border-color:var(--green);background:var(--white);box-shadow:0 0 0 3px rgba(29,185,84,0.08)}
.field input::placeholder{color:#C4C4C4}
.modal-footer{padding:16px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

/* CATEGORY SECTION */
.cat-section{margin-bottom:24px}
.cat-section-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700;margin-bottom:12px}
.cat-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.cat-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg);border:1px solid var(--border);border-radius:50px;font-size:13px;font-weight:500;color:var(--text)}
.cat-chip a{color:var(--light);text-decoration:none;font-size:11px;line-height:1}
.cat-chip a:hover{color:var(--red)}
.cat-add-row{display:flex;gap:8px}
.cat-add-row input{flex:1;padding:9px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s}
.cat-add-row input:focus{border-color:var(--green)}
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
        <a href="inventory.php" class="sb-link active">
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
        <div>
            <h1>Products & Inventory</h1>
            <p>Manage your product catalogue and stock</p>
        </div>
        <div class="topbar-right">
            <button class="btn btn-outline" onclick="openModal('cat-modal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                Categories
            </button>
            <button class="btn btn-green" onclick="openModal('add-modal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Product
            </button>
        </div>
    </div>

    <div class="content">

        <?php if ($msg): ?><div class="alert ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <!-- TOOLBAR -->
        <form method="GET" action="">
            <div class="toolbar">
                <div class="search-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" placeholder="Search by name, model, brand..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="cat" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php
                    $cats->data_seek(0);
                    while ($c = $cats->fetch_assoc()):
                    ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_cat == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <?php if ($search || $filter_cat): ?>
                    <a href="inventory.php" class="btn btn-outline" style="white-space:nowrap">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- PRODUCTS TABLE -->
        <div class="card">
            <div class="card-hdr">
                <span class="card-title">All Products</span>
                <span class="card-count"><?= $total_products ?> item<?= $total_products != 1 ? 's' : '' ?></span>
            </div>

            <?php if ($total_products > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Supplier</th>
                        <th>Cost</th>
                        <th>Sell Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($p = $products->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="td-main"><?= htmlspecialchars($p['name']) ?></div>
                        <?php if ($p['model_no']): ?>
                        <div class="td-model"><?= htmlspecialchars($p['model_no']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['brand'] ?: '—') ?></td>
                    <td>
                        <?= htmlspecialchars($p['supplier_name'] ?: '—') ?>
                        <?php if ($p['supplier_phone']): ?>
                        <div style="font-size:11px;color:var(--light)"><?= htmlspecialchars($p['supplier_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>Rs <?= number_format($p['cost_price'], 2) ?></td>
                    <td>Rs <?= number_format($p['selling_price'], 2) ?></td>
                    <td>
                        <?php
                        $q = (int)$p['quantity'];
                        if ($q === 0) echo '<span class="stock-badge stock-out">Out of stock</span>';
                        elseif ($q <= 5) echo '<span class="stock-badge stock-low">'.$q.' left</span>';
                        else echo '<span class="stock-badge stock-ok">'.$q.' in stock</span>';
                        ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="?edit=<?= $p['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-edit">Edit</a>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-red"
                               onclick="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?>? This cannot be undone.')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                <p><?= $search ? 'No products match your search.' : 'No products yet.' ?></p>
                <?php if (!$search): ?>
                <button class="btn btn-green" onclick="openModal('add-modal')">Add your first product</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ ADD PRODUCT MODAL ══ -->
<div class="overlay" id="add-modal" style="display:none">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Add Product</span>
            <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label>Product Name *</label>
                        <input type="text" name="name" placeholder="e.g. Samsung LED TV 43 inch" required>
                    </div>
                    <div class="field">
                        <label>Brand</label>
                        <input type="text" name="brand" placeholder="e.g. Samsung">
                    </div>
                    <div class="field">
                        <label>Model No.</label>
                        <input type="text" name="model_no" placeholder="e.g. UA43T5300">
                    </div>
                    <div class="field full">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">— Select Category —</option>
                            <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" placeholder="Supplier / distributor">
                    </div>
                    <div class="field">
                        <label>Supplier Phone</label>
                        <input type="text" name="supplier_phone" placeholder="98XXXXXXXX">
                    </div>
                    <div class="field">
                        <label>Cost Price (Rs)</label>
                        <input type="number" step="0.01" name="cost_price" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label>Selling Price (Rs)</label>
                        <input type="number" step="0.01" name="selling_price" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label>Quantity in Stock</label>
                        <input type="number" name="quantity" placeholder="0" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('add-modal')">Cancel</button>
                <button type="submit" name="add_product" class="btn btn-green">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT PRODUCT MODAL ══ -->
<?php if ($edit_product): ?>
<div class="overlay" id="edit-modal" style="display:flex">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Edit Product</span>
            <a href="inventory.php" class="modal-close">✕</a>
        </div>
        <form method="POST">
            <input type="hidden" name="pid" value="<?= $edit_product['id'] ?>">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field full">
                        <label>Product Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($edit_product['name']) ?>" required>
                    </div>
                    <div class="field">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?= htmlspecialchars($edit_product['brand']) ?>">
                    </div>
                    <div class="field">
                        <label>Model No.</label>
                        <input type="text" name="model_no" value="<?= htmlspecialchars($edit_product['model_no']) ?>">
                    </div>
                    <div class="field full">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">— Select Category —</option>
                            <?php $cats->data_seek(0); while ($c = $cats->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $edit_product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Supplier Name</label>
                        <input type="text" name="supplier_name" value="<?= htmlspecialchars($edit_product['supplier_name']) ?>">
                    </div>
                    <div class="field">
                        <label>Supplier Phone</label>
                        <input type="text" name="supplier_phone" value="<?= htmlspecialchars($edit_product['supplier_phone']) ?>">
                    </div>
                    <div class="field">
                        <label>Cost Price (Rs)</label>
                        <input type="number" step="0.01" name="cost_price" value="<?= $edit_product['cost_price'] ?>">
                    </div>
                    <div class="field">
                        <label>Selling Price (Rs)</label>
                        <input type="number" step="0.01" name="selling_price" value="<?= $edit_product['selling_price'] ?>">
                    </div>
                    <div class="field">
                        <label>Quantity in Stock</label>
                        <input type="number" name="quantity" value="<?= $edit_product['quantity'] ?>" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="inventory.php" class="btn btn-outline">Cancel</a>
                <button type="submit" name="edit_product" class="btn btn-green">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ══ CATEGORIES MODAL ══ -->
<div class="overlay" id="cat-modal" style="display:none">
    <div class="modal">
        <div class="modal-hdr">
            <span class="modal-title">Manage Categories</span>
            <button class="modal-close" onclick="closeModal('cat-modal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="cat-section">
                <div class="cat-section-title">Your Categories</div>
                <div class="cat-chips">
                    <?php $cats->data_seek(0); $has_cats = false; while ($c = $cats->fetch_assoc()): $has_cats = true; ?>
                    <div class="cat-chip">
                        <?= htmlspecialchars($c['name']) ?>
                        <a href="?del_cat=<?= $c['id'] ?>"
                           onclick="return confirm('Delete category <?= htmlspecialchars(addslashes($c['name'])) ?>?')"
                           title="Delete">✕</a>
                    </div>
                    <?php endwhile; ?>
                    <?php if (!$has_cats): ?>
                    <span style="font-size:13px;color:var(--light)">No categories yet.</span>
                    <?php endif; ?>
                </div>
                <form method="POST" class="cat-add-row">
                    <input type="text" name="category_name" placeholder="New category name..." required>
                    <button type="submit" name="add_category" class="btn btn-green">Add</button>
                </form>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('cat-modal')">Done</button>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = '';
}
// Close on overlay click
document.querySelectorAll('.overlay').forEach(o => {
    o.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});
// Auto-open add modal if msg is success from add
<?php if ($msg && strpos($msg, 'added') !== false && !$edit_product): ?>
// product was just added, stay on page
<?php endif; ?>
// Auto-open categories modal if came from category action
<?php if ($msg && (strpos($msg, 'Category') !== false)): ?>
window.onload = () => openModal('cat-modal');
<?php endif; ?>
</script>

</body>
</html>