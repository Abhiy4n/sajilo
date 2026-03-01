<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../includes/db.php';
require_once '../includes/csrf.php';

$uid   = $_SESSION['user_id'];
$name  = $_SESSION['user_name'];
$first = explode(' ', $name)[0];

$msg = $msg_type = '';

// ── HANDLE SALE SUBMISSION ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_sale') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request. Please try again.'; $msg_type = 'warn';
    } else {

    // Validate and sanitize inputs
    $customer_id_raw = $_POST['customer_id'] ?? '';
    $product_id_raw = $_POST['product_id'] ?? '';
    $product_name = trim($_POST['product_name'] ?? '');
    $model_no = trim($_POST['model_no'] ?? '');
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $unit_price = isset($_POST['unit_price']) ? max(0, (float)$_POST['unit_price']) : 0;
    $input_paid = isset($_POST['amount_paid']) ? max(0, (float)$_POST['amount_paid']) : 0;
    $warranty_months = isset($_POST['warranty_months']) ? (int)$_POST['warranty_months'] : 0;
    $notes = trim($_POST['notes'] ?? '');
    $sale_date_raw = $_POST['sale_date'] ?? date('Y-m-d');

    // Validate sale date
    $sale_date = date('Y-m-d', strtotime($sale_date_raw));
    $today_date = date('Y-m-d');
    if ($sale_date > $today_date) {
        $msg = 'Sale date cannot be in the future.'; $msg_type = 'warn';
    } elseif (strtotime($sale_date) < strtotime('-1 year')) {
        $msg = 'Sale date cannot be more than 1 year in the past.'; $msg_type = 'warn';
    } elseif (empty($product_name)) {
        $msg = 'Product name is required.'; $msg_type = 'warn';
    } else {
        $total_price = $unit_price * $quantity;
        $amount_paid = min($input_paid, $total_price);
        $balance_due = max(0, $total_price - $amount_paid);

        // Handle NULL properly for customer_id and product_id
        $customer_id = !empty($customer_id_raw) ? (int)$customer_id_raw : null;
        $product_id = !empty($product_id_raw) ? (int)$product_id_raw : null;

        // Calculate warranty expiry
        $warranty_expiry = null;
        if ($warranty_months > 0) {
            $warranty_expiry = date('Y-m-d', strtotime("{$sale_date} +{$warranty_months} months"));
        }

        // Insert sale using prepared statement
        $stmt = $conn->prepare("INSERT INTO sales
            (user_id, customer_id, product_id, product_name, model_no, quantity, unit_price,
             total_price, amount_paid, balance_due, warranty_months, warranty_expiry, notes, sale_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "iiisiddddisss",
            $uid,
            $customer_id,
            $product_id,
            $product_name,
            $model_no,
            $quantity,
            $unit_price,
            $total_price,
            $amount_paid,
            $balance_due,
            $warranty_months,
            $warranty_expiry,
            $notes,
            $sale_date
        );

        if ($stmt->execute()) {
            $sale_id = $conn->insert_id;

            // Reduce stock if product_id is set
            if ($product_id !== null) {
                $stmt2 = $conn->prepare("UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id=? AND user_id=?");
                $stmt2->bind_param("iii", $quantity, $product_id, $uid);
                $stmt2->execute();
                $stmt2->close();
            }

            $msg = "Sale recorded successfully! Sale #$sale_id"; $msg_type = 'ok';
        } else {
            $msg = 'Failed to record sale. Please try again.'; $msg_type = 'warn';
        }
        $stmt->close();
    }
}

// ── HANDLE ADD NEW CUSTOMER (AJAX-style inline) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    $cname  = trim($_POST['cname'] ?? '');
    $cphone = trim($_POST['cphone'] ?? '');
    $cemail = trim($_POST['cemail'] ?? '');

    if (empty($cname) || empty($cphone)) {
        if (isset($_POST['ajax'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and phone are required.']);
            exit;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (user_id, name, phone, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $uid, $cname, $cphone, $cemail);
        if ($stmt->execute()) {
            $new_cid = $conn->insert_id;
            if (isset($_POST['ajax'])) {
                echo json_encode(['id' => $new_cid, 'name' => $cname, 'phone' => $cphone]);
                exit;
            }
            $msg = "Customer '$cname' added."; $msg_type = 'ok';
        }
        $stmt->close();
    }
}

// ── FETCH DATA ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, name, phone FROM customers WHERE user_id=? ORDER BY name");
$stmt->bind_param("i", $uid);
$stmt->execute();
$customers_res = $stmt->get_result();
$customers = [];
while ($r = $customers_res->fetch_assoc()) $customers[] = $r;
$stmt->close();

$stmt = $conn->prepare("SELECT id, name, model_no, brand, selling_price, quantity FROM products WHERE user_id=? ORDER BY name");
$stmt->bind_param("i", $uid);
$stmt->execute();
$products_res = $stmt->get_result();
$products_js = [];
while ($r = $products_res->fetch_assoc()) $products_js[] = $r;
$stmt->close();

// Recent sales for the sidebar summary
$stmt = $conn->prepare("
    SELECT s.id, s.product_name, s.total_price, s.balance_due, s.sale_date, c.name as cname
    FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
    WHERE s.user_id=? ORDER BY s.id DESC LIMIT 5
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$recent = $stmt->get_result();
$stmt->close();

$active = 'record_sale';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sajilo — Record Sale</title>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* ── LAYOUT ── */
    .sale-layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}
    @media(max-width:900px){.sale-layout{grid-template-columns:1fr}}

    /* ── FORM CARD ── */
    .form-card{background:var(--white);border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow)}
    .form-card-hdr{padding:20px 26px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
    .form-card-hdr svg{color:var(--green)}
    .form-card-title{font-family:'Bricolage Grotesque',sans-serif;font-size:16px;font-weight:700}
    .form-section{padding:22px 26px;border-bottom:1px solid var(--border)}
    .form-section:last-of-type{border-bottom:none}
    .section-label{font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;display:flex;align-items:center;gap:6px}
    .section-label::after{content:'';flex:1;height:1px;background:var(--border)}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .form-group{display:flex;flex-direction:column;gap:5px}
    .form-group.full{grid-column:1/-1}
    label{font-size:12px;font-weight:600;color:var(--text-sub)}
    .form-control{padding:10px 13px;border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text);outline:none;transition:border-color .2s;background:var(--white);width:100%}
    .form-control:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(29,185,84,0.08)}
    .form-control[readonly]{background:var(--bg);color:var(--text-sub);cursor:default}
    select.form-control{cursor:pointer}
    textarea.form-control{resize:vertical;min-height:70px}
    .input-prefix{position:relative}
    .input-prefix span{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:12px;font-weight:600;color:var(--text-sub);pointer-events:none}
    .input-prefix input{padding-left:32px}

    /* ── SUMMARY SIDEBAR ── */
    .summary-card{background:var(--white);border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow);position:sticky;top:80px}
    .summary-hdr{padding:18px 22px;border-bottom:1px solid var(--border)}
    .summary-title{font-family:'Bricolage Grotesque',sans-serif;font-size:15px;font-weight:700}
    .summary-body{padding:18px 22px}
    .summary-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)}
    .summary-row:last-child{border-bottom:none}
    .summary-row-label{font-size:12px;color:var(--text-sub)}
    .summary-row-val{font-size:13px;font-weight:600;color:var(--text)}
    .summary-total{background:var(--bg);border-radius:12px;padding:14px 16px;margin-top:14px}
    .summary-total-label{font-size:12px;color:var(--text-sub);margin-bottom:4px}
    .summary-total-num{font-family:'Bricolage Grotesque',sans-serif;font-size:28px;font-weight:800;color:var(--green)}
    .balance-num{font-family:'Bricolage Grotesque',sans-serif;font-size:22px;font-weight:800}
    .balance-num.due{color:var(--red)}
    .balance-num.clear{color:var(--green)}
    .submit-btn{width:100%;padding:13px;background:var(--green);color:#fff;border:none;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;transition:background .2s;margin-top:14px;display:flex;align-items:center;justify-content:center;gap:8px}
    .submit-btn:hover{background:var(--green-dark)}
    .submit-btn:active{transform:scale(0.99)}

    /* ── RECENT SALES ── */
    .recent-card{background:var(--white);border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:var(--shadow);margin-top:20px}
    .recent-hdr{padding:16px 20px;border-bottom:1px solid var(--border)}
    .recent-title{font-family:'Bricolage Grotesque',sans-serif;font-size:14px;font-weight:700}
    .recent-item{padding:11px 20px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center}
    .recent-item:last-child{border-bottom:none}
    .recent-name{font-size:13px;font-weight:600;color:var(--text)}
    .recent-meta{font-size:11px;color:var(--text-light);margin-top:2px}

    /* ── NEW CUSTOMER MODAL ── */
    .overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:100;display:none;align-items:center;justify-content:center;padding:20px}
    .overlay.open{display:flex}
    .modal{background:var(--white);border-radius:16px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.2);animation:slideUp .2s ease}
    @keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .modal-hdr{padding:20px 22px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .modal-title{font-family:'Bricolage Grotesque',sans-serif;font-size:16px;font-weight:700}
    .modal-close{width:28px;height:28px;border-radius:7px;border:1px solid var(--border);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-sub)}
    .modal-close:hover{border-color:var(--red);color:var(--red)}
    .modal-body{padding:20px 22px;display:flex;flex-direction:column;gap:12px}
    .modal-footer{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
    .btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:all .2s}
    .btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:var(--green-dark)}
    .btn-outline{background:var(--white);color:var(--text);border:1px solid var(--border)}.btn-outline:hover{border-color:var(--green);color:var(--green)}

    /* ── FLASH ── */
    .flash{padding:12px 18px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px}
    .flash.ok{background:#DCFCE7;color:#16A34A;border:1px solid #BBF7D0}
    .flash.warn{background:#FEF3C7;color:#D97706;border:1px solid #FDE68A}

    /* ── STOCK WARNING ── */
    .stock-hint{font-size:11px;margin-top:4px;padding:4px 8px;border-radius:6px;display:none}
    .stock-hint.low{background:#FEF3C7;color:#D97706;display:block}
    .stock-hint.out{background:#FEE2E2;color:#DC2626;display:block}

    @media(max-width:600px){.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:1}}
  </style>
</head>
<body>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <h1>Record Sale</h1>
      <p>Log a new product sale to a customer</p>
    </div>
    <div class="topbar-right">
      <a href="inventory.php" class="btn btn-outline" style="padding:9px 16px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);text-decoration:none;display:flex;align-items:center;gap:6px;color:var(--text);background:var(--white)">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Inventory
      </a>
    </div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div class="flash <?= $msg_type ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="saleForm">
      <input type="hidden" name="action" value="record_sale">
      <?= csrf_field() ?>

      <div class="sale-layout">

        <!-- ── LEFT: FORM ── -->
        <div>

          <!-- CUSTOMER -->
          <div class="form-card" style="margin-bottom:20px">
            <div class="form-card-hdr">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <span class="form-card-title">Customer</span>
            </div>
            <div class="form-section">
              <div class="form-grid">
                <div class="form-group full">
                  <label>Select Customer</label>
                  <div style="display:flex;gap:8px">
                    <select name="customer_id" id="customerSelect" class="form-control" onchange="fillCustomerInfo()">
                      <option value="">— Walk-in / No customer —</option>
                      <?php foreach($customers as $c): ?>
                      <option value="<?= $c['id'] ?>" data-phone="<?= htmlspecialchars($c['phone']) ?>">
                        <?= htmlspecialchars($c['name']) ?> — <?= htmlspecialchars($c['phone']) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline" onclick="openNewCustomer()" style="white-space:nowrap;padding:9px 14px;font-size:13px;font-weight:700;border-radius:9px;border:1px solid var(--border);background:var(--white);cursor:pointer;display:flex;align-items:center;gap:5px">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                      New
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- PRODUCT -->
          <div class="form-card" style="margin-bottom:20px">
            <div class="form-card-hdr">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <span class="form-card-title">Product</span>
            </div>
            <div class="form-section">
              <div class="form-grid">

                <div class="form-group full">
                  <label>Search Product from Inventory</label>
                  <!-- HTML5 datalist: free-text search with autocomplete from inventory -->
                  <input type="text" id="productSearch" class="form-control"
                         list="productList" placeholder="Type to search product…"
                         oninput="fillProductFromSearch()" autocomplete="off">
                  <datalist id="productList">
                    <?php foreach($products_js as $p): ?>
                    <option value="<?= htmlspecialchars($p['name']) ?> — Rs <?= number_format($p['selling_price'],2) ?> (Stock: <?= $p['quantity'] ?>)"
                            data-id="<?= $p['id'] ?>">
                    </option>
                    <?php endforeach; ?>
                  </datalist>
                  <div class="stock-hint" id="stockHint"></div>
                </div>

                <div class="form-group full">
                  <label>Product Name *</label>
                  <input type="text" name="product_name" id="fProdName" class="form-control" required placeholder="Or type manually">
                </div>

                <div class="form-group">
                  <label>Model No.</label>
                  <input type="text" name="model_no" id="fModelNo" class="form-control" placeholder="Auto-filled">
                </div>

                <div class="form-group">
                  <label>Quantity *</label>
                  <input type="number" name="quantity" id="fQty" class="form-control" min="1" value="1" required onchange="recalc()">
                </div>

                <div class="form-group">
                  <label>Unit Price (Rs) *</label>
                  <div class="input-prefix">
                    <span>Rs</span>
                    <input type="number" name="unit_price" id="fUnitPrice" class="form-control" step="0.01" min="0" required onchange="recalc()" placeholder="0.00">
                  </div>
                </div>

                <div class="form-group">
                  <label>Total Price</label>
                  <div class="input-prefix">
                    <span>Rs</span>
                    <input type="number" name="total_price" id="fTotal" class="form-control" readonly placeholder="Auto-calculated">
                  </div>
                </div>

              </div>
            </div>

            <!-- HIDDEN product_id -->
            <input type="hidden" name="product_id" id="fProdId" value="">
          </div>

          <!-- PAYMENT + WARRANTY -->
          <div class="form-card">
            <div class="form-card-hdr">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              <span class="form-card-title">Payment & Warranty</span>
            </div>
            <div class="form-section">
              <div class="form-grid">

                <div class="form-group">
                  <label>Cash Received (Rs) *</label>
                  <div class="input-prefix">
                    <span>Rs</span>
                    <input type="number" name="amount_paid" id="fPaid" class="form-control" step="0.01" min="0" value="0" required oninput="recalc()" placeholder="0.00">
                  </div>
                </div>

                <div class="form-group">
                  <label>Balance Due</label>
                  <div class="input-prefix">
                    <span>Rs</span>
                    <input type="number" name="balance_due" id="fBalance" class="form-control" readonly placeholder="Auto-calculated">
                  </div>
                </div>

                <!-- CHANGE DUE — shown only when customer overpays -->
                <div class="form-group full" id="changeRow" style="display:none">
                  <label style="color:var(--green)">💵 Change to Return to Customer</label>
                  <div class="input-prefix">
                    <span>Rs</span>
                    <input type="text" id="fChange" class="form-control" readonly
                           style="background:#F0FDF4;border-color:#86EFAC;color:#16A34A;font-weight:700;font-size:15px">
                  </div>
                </div>

                <div class="form-group">
                  <label>Warranty (months)</label>
                  <select name="warranty_months" id="fWarranty" class="form-control" onchange="calcExpiry()">
                    <option value="0">No Warranty</option>
                    <option value="3">3 months</option>
                    <option value="6">6 months</option>
                    <option value="12" selected>12 months</option>
                    <option value="18">18 months</option>
                    <option value="24">24 months</option>
                    <option value="36">36 months</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Warranty Expiry</label>
                  <input type="text" id="fExpiry" class="form-control" readonly placeholder="Auto-calculated">
                </div>

                <div class="form-group">
                  <label>Sale Date *</label>
                  <input type="date" name="sale_date" id="fSaleDate" class="form-control" required onchange="calcExpiry()">
                </div>

                <div class="form-group full">
                  <label>Notes</label>
                  <textarea name="notes" class="form-control" placeholder="Optional notes about this sale…"></textarea>
                </div>

              </div>
            </div>
          </div>

        </div>

        <!-- ── RIGHT: SUMMARY ── -->
        <div>
          <div class="summary-card">
            <div class="summary-hdr">
              <div class="summary-title">Sale Summary</div>
            </div>
            <div class="summary-body">
              <div class="summary-row">
                <span class="summary-row-label">Product</span>
                <span class="summary-row-val" id="sProd">—</span>
              </div>
              <div class="summary-row">
                <span class="summary-row-label">Customer</span>
                <span class="summary-row-val" id="sCust">Walk-in</span>
              </div>
              <div class="summary-row">
                <span class="summary-row-label">Qty × Unit Price</span>
                <span class="summary-row-val" id="sCalc">—</span>
              </div>
              <div class="summary-row">
                <span class="summary-row-label">Amount Paid</span>
                <span class="summary-row-val" id="sPaid" style="color:var(--green)">Rs 0</span>
              </div>
              <div class="summary-row">
                <span class="summary-row-label">Warranty</span>
                <span class="summary-row-val" id="sWarranty">12 months</span>
              </div>
              <div class="summary-row">
                <span class="summary-row-label">Expires</span>
                <span class="summary-row-val" id="sExpiry">—</span>
              </div>

              <div class="summary-total">
                <div class="summary-total-label">Total Price</div>
                <div class="summary-total-num" id="sTotal">Rs 0</div>
              </div>

              <div class="summary-total" style="margin-top:10px">
                <div class="summary-total-label">Balance Due</div>
                <div class="balance-num clear" id="sBalance">Rs 0</div>
              </div>

              <!-- CHANGE BOX — shown only when overpaid -->
              <div id="sChangeBox" style="display:none;margin-top:10px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:14px 16px">
                <div style="font-size:12px;color:#16A34A;margin-bottom:4px;font-weight:600">💵 Give Change</div>
                <div style="font-family:'Bricolage Grotesque',sans-serif;font-size:26px;font-weight:800;color:#16A34A" id="sChange">Rs 0</div>
              </div>

              <button type="submit" class="submit-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Record Sale
              </button>
            </div>
          </div>

          <!-- RECENT SALES -->
          <div class="recent-card">
            <div class="recent-hdr">
              <div class="recent-title">Recent Sales</div>
            </div>
            <?php
            $has = false;
            while ($r = $recent->fetch_assoc()):
              $has = true;
            ?>
            <div class="recent-item">
              <div>
                <div class="recent-name"><?= htmlspecialchars($r['product_name']) ?></div>
                <div class="recent-meta"><?= htmlspecialchars($r['cname'] ?? 'Walk-in') ?> · <?= date('M j', strtotime($r['sale_date'])) ?></div>
              </div>
              <div style="text-align:right">
                <div style="font-size:13px;font-weight:700;color:var(--text)">Rs <?= number_format($r['total_price']) ?></div>
                <?php if ($r['balance_due'] > 0): ?>
                  <span class="badge due" style="font-size:10px;padding:2px 7px">Due Rs <?= number_format($r['balance_due']) ?></span>
                <?php else: ?>
                  <span class="badge paid" style="font-size:10px;padding:2px 7px">Paid</span>
                <?php endif; ?>
              </div>
            </div>
            <?php endwhile; ?>
            <?php if (!$has): ?>
              <div style="padding:24px;text-align:center;font-size:12px;color:var(--text-light)">No sales yet.</div>
            <?php endif; ?>
          </div>

        </div>

      </div>
    </form>
  </div>
</div>

<!-- ── NEW CUSTOMER MODAL ── -->
<div class="overlay" id="custModal">
  <div class="modal">
    <div class="modal-hdr">
      <span class="modal-title">Add New Customer</span>
      <button class="modal-close" onclick="closeCustModal()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" id="newCName" class="form-control" placeholder="e.g. Ram Bahadur">
      </div>
      <div class="form-group">
        <label>Phone *</label>
        <input type="text" id="newCPhone" class="form-control" placeholder="e.g. 9800000000">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" id="newCEmail" class="form-control" placeholder="optional">
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeCustModal()">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="saveNewCustomer()">Add & Select</button>
    </div>
  </div>
</div>

<script>
// ── PRODUCT DATA ──
const products = <?= json_encode($products_js) ?>;

function fmt(n) {
  return 'Rs ' + parseFloat(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// ── FILL PRODUCT FROM DATALIST SEARCH ──
function fillProductFromSearch() {
  const input = document.getElementById('productSearch').value;
  // Match typed value against product names in our JS array
  const match = products.find(p => input.startsWith(p.name));
  const hint  = document.getElementById('stockHint');

  if (!match) {
    document.getElementById('fProdId').value = '';
    hint.className = 'stock-hint';
    return;
  }

  document.getElementById('fProdId').value     = match.id;
  document.getElementById('fProdName').value   = match.name;
  document.getElementById('fModelNo').value    = match.model_no  || '';
  document.getElementById('fUnitPrice').value  = match.selling_price;
  document.getElementById('sProd').textContent = match.name;

  const stock = parseInt(match.quantity);
  if (stock === 0)     { hint.className = 'stock-hint out'; hint.textContent = '⚠️ Out of stock'; }
  else if (stock <= 3) { hint.className = 'stock-hint low'; hint.textContent = `⚠️ Low stock — only ${stock} left`; }
  else                 { hint.className = 'stock-hint'; }

  recalc();
}

// ── RECALCULATE ──
function recalc() {
  const qty        = parseFloat(document.getElementById('fQty').value)      || 0;
  const price      = parseFloat(document.getElementById('fUnitPrice').value) || 0;
  const inputPaid  = parseFloat(document.getElementById('fPaid').value)      || 0;
  const total      = qty * price;

  // CAP: amount saved to DB can't exceed total
  const amountPaid = Math.min(inputPaid, total);
  const balance    = Math.max(0, total - amountPaid);
  const change     = Math.max(0, inputPaid - total);  // overpay → change

  document.getElementById('fTotal').value   = total.toFixed(2);
  document.getElementById('fBalance').value = balance.toFixed(2);

  // Show/hide change field
  const changeRow = document.getElementById('changeRow');
  const changeBox = document.getElementById('sChangeBox');
  if (change > 0) {
    document.getElementById('fChange').value      = change.toFixed(2);
    document.getElementById('sChange').textContent = fmt(change);
    changeRow.style.display = '';
    changeBox.style.display = '';
  } else {
    changeRow.style.display = 'none';
    changeBox.style.display = 'none';
  }

  // Summary
  document.getElementById('sCalc').textContent    = `${qty} × ${fmt(price)}`;
  document.getElementById('sTotal').textContent    = fmt(total);
  document.getElementById('sPaid').textContent     = fmt(amountPaid);
  document.getElementById('sBalance').textContent  = fmt(balance);
  document.getElementById('sBalance').className    = 'balance-num ' + (balance > 0 ? 'due' : 'clear');
}

// ── EXPIRY ──
function calcExpiry() {
  const months = parseInt(document.getElementById('fWarranty').value);
  const date   = document.getElementById('fSaleDate').value;
  const sel    = document.getElementById('fWarranty');
  document.getElementById('sWarranty').textContent = sel.options[sel.selectedIndex].textContent;

  if (!months || !date) {
    document.getElementById('fExpiry').value     = '';
    document.getElementById('sExpiry').textContent = '—';
    return;
  }
  const d = new Date(date);
  d.setMonth(d.getMonth() + months);
  const exp = d.toISOString().split('T')[0];
  document.getElementById('fExpiry').value = exp;
  document.getElementById('sExpiry').textContent = d.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
}

// ── CUSTOMER SELECT ──
function fillCustomerInfo() {
  const sel = document.getElementById('customerSelect');
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('sCust').textContent = opt.value ? opt.text.split('—')[0].trim() : 'Walk-in';
}

// ── NEW CUSTOMER MODAL ──
function openNewCustomer() {
  document.getElementById('custModal').classList.add('open');
}
function closeCustModal() {
  document.getElementById('custModal').classList.remove('open');
}
document.getElementById('custModal').addEventListener('click', function(e) {
  if (e.target === this) closeCustModal();
});

function saveNewCustomer() {
  const name  = document.getElementById('newCName').value.trim();
  const phone = document.getElementById('newCPhone').value.trim();
  const email = document.getElementById('newCEmail').value.trim();
  if (!name || !phone) { alert('Name and phone are required.'); return; }

  const fd = new FormData();
  fd.append('action', 'add_customer');
  fd.append('ajax', '1');
  fd.append('cname',  name);
  fd.append('cphone', phone);
  fd.append('cemail', email);

  fetch('record_sale.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      // Add to select dropdown and auto-select
      const sel = document.getElementById('customerSelect');
      const opt = new Option(`${data.name} — ${data.phone}`, data.id);
      opt.dataset.phone = data.phone;
      sel.appendChild(opt);
      sel.value = data.id;
      fillCustomerInfo();
      closeCustModal();
      document.getElementById('newCName').value = document.getElementById('newCPhone').value = document.getElementById('newCEmail').value = '';
    })
    .catch(() => alert('Error saving customer. Please try again.'));
}

// ── INIT ──
document.addEventListener('DOMContentLoaded', () => {
  // Set today's date
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('fSaleDate').value = today;
  calcExpiry();
  recalc();
});
</script>

</body>
</html>