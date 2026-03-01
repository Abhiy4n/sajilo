<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'includes/db.php';
require_once 'includes/csrf.php';

$errors = [];
$mode   = $_GET['mode'] ?? 'login';

// ── LOGIN ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $errors[] = 'Please fill in all fields.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
    $mode = 'login';
}

// ── SIGNUP ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $errors[] = 'Please fill in all fields.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'This email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hash);
                if ($stmt->execute()) {
                    $_SESSION['user_id']   = $conn->insert_id;
                    $_SESSION['user_name'] = $name;
                    header('Location: dashboard.php');
                    exit;
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
    $mode = 'signup';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sajilo — <?= $mode === 'login' ? 'Sign In' : 'Create Account' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..60,400;12..60,600;12..60,700;12..60,800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --green:      #1DB954;
      --green-dark: #16A34A;
      --green-dim:  rgba(29,185,84,0.12);
      --dark:       #0A1A0A;
      --dark-2:     #0F2010;
      --white:      #FFFFFF;
      --off-white:  #F7FAF7;
      --border:     #E4EDE4;
      --text:       #0F1A0F;
      --text-sub:   #5A7060;
      --text-light: #9DB09D;
      --red:        #EF4444;
      --red-bg:     #FEF2F2;
    }

    html, body {
      height: 100%;
      font-family: 'DM Sans', sans-serif;
      background: var(--dark);
    }

    /* ── LAYOUT ── */
    .page {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
    }

    /* ── LEFT PANEL ── */
    .panel-left {
      background: var(--dark);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 40px 48px;
    }

    /* animated grid background */
    .grid-bg {
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(29,185,84,0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(29,185,84,0.07) 1px, transparent 1px);
      background-size: 48px 48px;
      animation: gridShift 20s linear infinite;
    }
    @keyframes gridShift {
      0%   { transform: translate(0, 0); }
      100% { transform: translate(48px, 48px); }
    }

    /* floating orbs */
    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.25;
      animation: orbFloat 8s ease-in-out infinite;
    }
    .orb-1 { width: 320px; height: 320px; background: #1DB954; top: -80px; left: -80px; animation-delay: 0s; }
    .orb-2 { width: 200px; height: 200px; background: #16A34A; bottom: 60px; right: -60px; animation-delay: -3s; }
    .orb-3 { width: 140px; height: 140px; background: #4ADE80; top: 50%; left: 40%; animation-delay: -5s; }
    @keyframes orbFloat {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33%       { transform: translate(15px, -20px) scale(1.05); }
      66%       { transform: translate(-10px, 10px) scale(0.95); }
    }

    .panel-left-content { position: relative; z-index: 2; }

    /* Logo */
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }
    .logo-icon {
      width: 36px; height: 36px;
      background: var(--green);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .logo-name {
      font-family: 'Bricolage Grotesque', sans-serif;
      font-size: 20px; font-weight: 800;
      color: #fff;
    }

    /* Hero copy */
    .hero { margin-top: 80px; }
    .hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 12px;
      background: var(--green-dim);
      border: 1px solid rgba(29,185,84,0.2);
      border-radius: 50px;
      font-size: 11px;
      font-weight: 600;
      color: var(--green);
      letter-spacing: .06em;
      text-transform: uppercase;
      margin-bottom: 24px;
    }
    .hero-tag::before {
      content: '';
      width: 6px; height: 6px;
      background: var(--green);
      border-radius: 50%;
      animation: pulse 2s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: 0.4; transform: scale(1.4); }
    }
    .hero h1 {
      font-family: 'Bricolage Grotesque', sans-serif;
      font-size: clamp(32px, 3.5vw, 48px);
      font-weight: 800;
      color: #fff;
      line-height: 1.1;
      margin-bottom: 16px;
    }
    .hero h1 span { color: var(--green); }
    .hero p {
      font-size: 15px;
      color: rgba(255,255,255,0.45);
      line-height: 1.7;
      max-width: 340px;
    }

    /* Feature pills */
    .features {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 36px;
    }
    .feat-pill {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 50px;
      font-size: 12px;
      font-weight: 500;
      color: rgba(255,255,255,0.6);
      animation: fadeUp 0.6s ease both;
    }
    .feat-pill:nth-child(1) { animation-delay: 0.1s; }
    .feat-pill:nth-child(2) { animation-delay: 0.2s; }
    .feat-pill:nth-child(3) { animation-delay: 0.3s; }
    .feat-pill:nth-child(4) { animation-delay: 0.4s; }
    .feat-pill svg { color: var(--green); flex-shrink: 0; }

    /* Testimonial */
    .testimonial {
      position: relative; z-index: 2;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 16px;
      padding: 20px 24px;
      animation: fadeUp 0.8s ease 0.5s both;
    }
    .test-text {
      font-size: 13px;
      color: rgba(255,255,255,0.55);
      line-height: 1.6;
      font-style: italic;
      margin-bottom: 12px;
    }
    .test-author {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .test-avatar {
      width: 30px; height: 30px;
      background: var(--green);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff;
    }
    .test-name { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.7); }
    .test-role { font-size: 11px; color: rgba(255,255,255,0.3); }

    /* ── RIGHT PANEL ── */
    .panel-right {
      background: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 48px;
      position: relative;
    }
    .panel-right::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--green), #4ADE80, var(--green));
      background-size: 200% 100%;
      animation: shimmer 3s linear infinite;
    }
    @keyframes shimmer {
      0%   { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }

    .form-wrap {
      width: 100%;
      max-width: 400px;
      animation: fadeUp 0.5s ease both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Tab switcher */
    .tabs {
      display: flex;
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 32px;
    }
    .tab {
      flex: 1;
      padding: 9px;
      text-align: center;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-sub);
      border-radius: 9px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
    }
    .tab.active {
      background: var(--white);
      color: var(--text);
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .tab:hover:not(.active) { color: var(--text); }

    /* Headings */
    .form-title {
      font-family: 'Bricolage Grotesque', sans-serif;
      font-size: 26px;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
    }
    .form-sub {
      font-size: 13px;
      color: var(--text-sub);
      margin-bottom: 28px;
    }

    /* Error */
    .error-box {
      display: flex;
      align-items: center;
      gap: 9px;
      background: var(--red-bg);
      border: 1px solid #FECACA;
      border-radius: 10px;
      padding: 11px 14px;
      font-size: 13px;
      color: var(--red);
      margin-bottom: 20px;
      animation: shake 0.4s ease;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%       { transform: translateX(-6px); }
      40%       { transform: translateX(6px); }
      60%       { transform: translateX(-4px); }
      80%       { transform: translateX(4px); }
    }

    /* Form fields */
    .field { margin-bottom: 16px; }
    .field label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-sub);
      margin-bottom: 6px;
    }
    .input-wrap { position: relative; }
    .input-wrap svg.icon-left {
      position: absolute;
      left: 13px; top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      pointer-events: none;
      transition: color 0.2s;
    }
    .input-wrap input {
      width: 100%;
      padding: 11px 40px 11px 38px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      color: var(--text);
      background: var(--white);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .input-wrap input:focus {
      border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(29,185,84,0.1);
    }
    .input-wrap input:focus + svg.icon-left,
    .input-wrap input:focus ~ svg.icon-left { color: var(--green); }
    .input-wrap input.error { border-color: var(--red); }
    .input-wrap input.valid { border-color: var(--green); }

    /* Password toggle */
    .pass-toggle {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-light);
      display: flex;
      align-items: center;
      transition: color 0.2s;
      padding: 2px;
    }
    .pass-toggle:hover { color: var(--text-sub); }

    /* Inline field validation hint */
    .field-hint {
      font-size: 11px;
      margin-top: 5px;
      display: none;
    }
    .field-hint.show { display: block; }
    .field-hint.err  { color: var(--red); }
    .field-hint.ok   { color: var(--green); }

    /* Password strength */
    .strength-bar {
      display: flex;
      gap: 4px;
      margin-top: 6px;
    }
    .strength-seg {
      flex: 1;
      height: 3px;
      background: var(--border);
      border-radius: 2px;
      transition: background 0.3s;
    }
    .strength-seg.weak   { background: var(--red); }
    .strength-seg.medium { background: var(--amber, #F59E0B); }
    .strength-seg.strong { background: var(--green); }
    .strength-label {
      font-size: 10px;
      font-weight: 600;
      color: var(--text-light);
      margin-top: 3px;
    }

    /* Submit button */
    .submit-btn {
      width: 100%;
      padding: 13px;
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 11px;
      font-size: 14px;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: background 0.2s, transform 0.1s;
      margin-top: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .submit-btn:hover  { background: var(--green-dark); }
    .submit-btn:active { transform: scale(0.99); }
    .submit-btn::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,0.15) 50%, transparent 70%);
      transform: translateX(-100%);
      transition: transform 0.5s;
    }
    .submit-btn:hover::after { transform: translateX(100%); }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0;
      color: var(--text-light);
      font-size: 12px;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* Google button (UI only) */
    .google-btn {
      width: 100%;
      padding: 11px;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: 11px;
      font-size: 13px;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      color: var(--text);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: border-color 0.2s, background 0.2s;
    }
    .google-btn:hover { border-color: #4285F4; background: #F8FAFF; }

    /* Switch link */
    .switch-link {
      text-align: center;
      margin-top: 22px;
      font-size: 13px;
      color: var(--text-sub);
    }
    .switch-link a {
      color: var(--green);
      font-weight: 700;
      text-decoration: none;
    }
    .switch-link a:hover { text-decoration: underline; }

    /* Terms */
    .terms {
      text-align: center;
      font-size: 11px;
      color: var(--text-light);
      margin-top: 16px;
      line-height: 1.5;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 800px) {
      .page { grid-template-columns: 1fr; }
      .panel-left { display: none; }
      .panel-right { padding: 32px 24px; }
    }
  </style>
</head>
<body>

<div class="page">

  <!-- ── LEFT PANEL ── -->
  <div class="panel-left">
    <div class="grid-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="panel-left-content">
      <a href="index.php" class="logo">
        <div class="logo-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
        </div>
        <span class="logo-name">Sajilo</span>
      </a>

      <div class="hero">
        <div class="hero-tag">Business tracking, made simple</div>
        <h1>Your business,<br><span>under control.</span></h1>
        <p>Track products, record sales, manage warranties and outstanding payments — all in one clean dashboard.</p>

        <div class="features">
          <div class="feat-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            Inventory
          </div>
          <div class="feat-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Customers
          </div>
          <div class="feat-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Warranties
          </div>
          <div class="feat-pill">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Reports
          </div>
        </div>
      </div>
    </div>

    <div class="testimonial">
      <div class="test-text">"Sajilo replaced 3 spreadsheets I was maintaining manually. Now I check everything from one page."</div>
      <div class="test-author">
        <div class="test-avatar">R</div>
        <div>
          <div class="test-name">Ramesh Shrestha</div>
          <div class="test-role">Electronics Retailer, Kathmandu</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT PANEL ── -->
  <div class="panel-right">
    <div class="form-wrap">

      <!-- Tab switcher -->
      <div class="tabs">
        <a href="?mode=login"  class="tab <?= $mode === 'login'  ? 'active' : '' ?>">Sign In</a>
        <a href="?mode=signup" class="tab <?= $mode === 'signup' ? 'active' : '' ?>">Create Account</a>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="error-box">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors[0]) ?>
      </div>
      <?php endif; ?>

      <!-- ── SIGN IN ── -->
      <?php if ($mode === 'login'): ?>

      <div class="form-title">Welcome back 👋</div>
      <div class="form-sub">Sign in to your Sajilo account</div>

      <form method="POST" novalidate id="loginForm">
        <?= csrf_field() ?>
        <div class="field">
          <label for="loginEmail">Email address</label>
          <div class="input-wrap">
            <svg class="icon-left" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" id="loginEmail" name="email" placeholder="you@example.com"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   oninput="validateEmail(this, 'emailHint')" required>
            <button type="button" class="pass-toggle" style="display:none"></button>
          </div>
          <div class="field-hint" id="emailHint"></div>
        </div>

        <div class="field">
          <label for="loginPass">Password</label>
          <div class="input-wrap">
            <svg class="icon-left" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="loginPass" name="password" placeholder="Your password" required>
            <button type="button" class="pass-toggle" onclick="togglePass('loginPass', this)" title="Show/hide password">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="eyeLogin"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" name="login" class="submit-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Sign In
        </button>
      </form>

      <div class="divider">or</div>

      <button class="google-btn" type="button" onclick="alert('Google login coming soon!')">
        <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Continue with Google
      </button>

      <div class="switch-link">No account? <a href="?mode=signup">Create one free →</a></div>

      <!-- ── SIGN UP ── -->
      <?php else: ?>

      <div class="form-title">Start for free ✨</div>
      <div class="form-sub">Create your Sajilo account in seconds</div>

      <form method="POST" novalidate id="signupForm">
        <?= csrf_field() ?>
        <div class="field">
          <label for="signupName">Full name</label>
          <div class="input-wrap">
            <svg class="icon-left" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" id="signupName" name="name" placeholder="Ram Bahadur"
                   value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                   oninput="validateName(this)" required>
            <button type="button" class="pass-toggle" style="display:none"></button>
          </div>
          <div class="field-hint" id="nameHint"></div>
        </div>

        <div class="field">
          <label for="signupEmail">Email address</label>
          <div class="input-wrap">
            <svg class="icon-left" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" id="signupEmail" name="email" placeholder="you@example.com"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   oninput="validateEmail(this, 'signupEmailHint')" required>
            <button type="button" class="pass-toggle" style="display:none"></button>
          </div>
          <div class="field-hint" id="signupEmailHint"></div>
        </div>

        <div class="field">
          <label for="signupPass">Password</label>
          <div class="input-wrap">
            <svg class="icon-left" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="signupPass" name="password" placeholder="Min. 6 characters"
                   oninput="checkStrength(this)" required>
            <button type="button" class="pass-toggle" onclick="togglePass('signupPass', this)" title="Show/hide">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="eyeSignup"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <!-- Strength bar -->
          <div class="strength-bar" id="strengthBar">
            <div class="strength-seg" id="s1"></div>
            <div class="strength-seg" id="s2"></div>
            <div class="strength-seg" id="s3"></div>
            <div class="strength-seg" id="s4"></div>
          </div>
          <div class="strength-label" id="strengthLabel">Enter a password</div>
        </div>

        <button type="submit" name="signup" class="submit-btn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          Create Account
        </button>
      </form>

      <div class="terms">
        By creating an account, you agree to Sajilo's<br>
        <a href="#" style="color:var(--green)">Terms of Service</a> and <a href="#" style="color:var(--green)">Privacy Policy</a>
      </div>

      <div class="switch-link" style="margin-top:14px">Already have an account? <a href="?mode=login">Sign in →</a></div>

      <?php endif; ?>

    </div>
  </div>

</div>

<script>
// ── HELPERS ──
function setField(input, hintId, isOk, msg) {
  input.className = isOk ? 'valid' : (msg ? 'error' : '');
  if (!hintId) return;
  const h = document.getElementById(hintId);
  if (!h) return;
  h.textContent = msg;
  h.className = 'field-hint show ' + (isOk ? 'ok' : 'err');
  if (!msg) h.className = 'field-hint';
}

// ── EMAIL VALIDATION ──
function validateEmail(input, hintId) {
  const val = input.value.trim();
  const re  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!val) { setField(input, hintId, false, ''); return; }
  if (!re.test(val)) { setField(input, hintId, false, 'Enter a valid email address'); return; }
  setField(input, hintId, true, '✓ Looks good');
}

// ── NAME VALIDATION ──
function validateName(input) {
  const val = input.value.trim();
  if (!val) { setField(input, 'nameHint', false, ''); return; }
  if (val.length < 2) { setField(input, 'nameHint', false, 'Name too short'); return; }
  setField(input, 'nameHint', true, '✓ Great');
}

// ── PASSWORD STRENGTH ──
function checkStrength(input) {
  const val  = input.value;
  const segs = [document.getElementById('s1'), document.getElementById('s2'),
                document.getElementById('s3'), document.getElementById('s4')];
  const lbl  = document.getElementById('strengthLabel');
  segs.forEach(s => s.className = 'strength-seg');

  if (!val) { lbl.textContent = 'Enter a password'; lbl.style.color = 'var(--text-light)'; return; }

  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
  if (/[0-9!@#$%^&*]/.test(val)) score++;

  const levels = ['', 'weak', 'medium', 'medium', 'strong'];
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong 💪'];
  const colors = ['', 'var(--red)', '#F59E0B', '#F59E0B', 'var(--green)'];

  for (let i = 0; i < score; i++) segs[i].className = 'strength-seg ' + levels[score];
  lbl.textContent = labels[score];
  lbl.style.color = colors[score];
}

// ── PASSWORD TOGGLE ──
function togglePass(inputId, btn) {
  const input = document.getElementById(inputId);
  const isPass = input.type === 'password';
  input.type = isPass ? 'text' : 'password';
  btn.innerHTML = isPass
    ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
    : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
}

// ── CLIENT-SIDE FORM GUARD ──
<?php if ($mode === 'login'): ?>
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const email = document.getElementById('loginEmail').value.trim();
  const pass  = document.getElementById('loginPass').value;
  if (!email || !pass) { e.preventDefault(); alert('Please fill in all fields.'); }
});
<?php else: ?>
document.getElementById('signupForm').addEventListener('submit', function(e) {
  const name  = document.getElementById('signupName').value.trim();
  const email = document.getElementById('signupEmail').value.trim();
  const pass  = document.getElementById('signupPass').value;
  const re    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!name || !email || !pass)          { e.preventDefault(); alert('Please fill in all fields.'); return; }
  if (!re.test(email))                   { e.preventDefault(); alert('Enter a valid email.'); return; }
  if (pass.length < 6)                   { e.preventDefault(); alert('Password must be at least 6 characters.'); }
});
<?php endif; ?>
</script>

</body>
</html>