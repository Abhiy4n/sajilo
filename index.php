<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sajilo — Business Tracking, Made Simple</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green:      #1DB954;
            --green-dark: #16A34A;
            --green-pale: #F0FDF4;
            --green-mid:  #DCFCE7;
            --text:       #111827;
            --text-sub:   #6B7280;
            --text-light: #9CA3AF;
            --bg:         #F8F9FA;
            --white:      #FFFFFF;
            --border:     #E5E7EB;
            --shadow:     0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg:  0 20px 60px rgba(0,0,0,0.1);
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--white);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ── NAVBAR ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 60px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-icon {
            width: 32px; height: 32px;
            background: var(--green);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .logo-text {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--green-dark);
        }

        .nav-links {
            display: flex;
            gap: 32px;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-sub);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: var(--text); }

        .nav-actions { display: flex; gap: 12px; align-items: center; }

        .btn-outline {
            padding: 8px 20px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            background: none;
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-outline:hover { border-color: var(--green); color: var(--green); }

        .btn-green {
            padding: 8px 22px;
            background: var(--green);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-green:hover { background: var(--green-dark); transform: translateY(-1px); }

        /* ── HERO ── */
        .hero {
            padding: 120px 60px 80px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green-pale);
            border: 1px solid #BBF7D0;
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--green-dark);
            margin-bottom: 24px;
        }

        .badge-shield {
            width: 14px; height: 14px;
            background: var(--green);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }

        .hero h1 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(36px, 4.5vw, 58px);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: var(--text);
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 16px;
            color: var(--text-sub);
            line-height: 1.75;
            margin-bottom: 36px;
            max-width: 480px;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-hero-green {
            padding: 13px 28px;
            background: var(--green);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-green:hover { background: var(--green-dark); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(29,185,84,0.3); }

        .btn-hero-outline {
            padding: 13px 28px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: none;
            color: var(--text);
            font-size: 15px;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-outline:hover { border-color: var(--text); }

        /* Hero right: visual */
        .hero-visual {
            position: relative;
        }

        .hero-bg-shape {
            position: absolute;
            top: -20px; right: -20px;
            width: 420px; height: 420px;
            background: linear-gradient(135deg, #DCFCE7 0%, #F0FDF4 60%, #fff 100%);
            border-radius: 40% 60% 60% 40% / 40% 40% 60% 60%;
            z-index: 0;
        }

        .hero-card-main {
            position: relative;
            z-index: 1;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .hc-header {
            background: var(--green);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hc-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .hc-dots { display: flex; gap: 5px; margin-left: auto; }
        .hc-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.4); }
        .hc-dot:first-child { background: rgba(255,255,255,0.8); }

        .hc-body { padding: 20px; }

        .hc-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .hc-stat {
            background: var(--bg);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }

        .hc-stat-num {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }
        .hc-stat-num.green { color: var(--green); }
        .hc-stat-num.orange { color: #F59E0B; }
        .hc-stat-label { font-size: 10px; color: var(--text-light); margin-top: 2px; }

        .hc-table { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
        .hc-th {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1fr 0.8fr;
            padding: 8px 14px;
            background: var(--bg);
            font-size: 10px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
        }
        .hc-tr {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1fr 0.8fr;
            padding: 9px 14px;
            font-size: 11px;
            color: var(--text-sub);
            border-bottom: 1px solid #F3F4F6;
            align-items: center;
        }
        .hc-tr:last-child { border-bottom: none; }
        .hc-name { font-weight: 500; color: var(--text); }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 50px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge.ok   { background: #DCFCE7; color: #16A34A; }
        .badge.warn { background: #FEF3C7; color: #D97706; }
        .badge.exp  { background: #FEE2E2; color: #DC2626; }

        /* Floating notification cards */
        .notif {
            position: absolute;
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
            z-index: 2;
            animation: float 3s ease-in-out infinite;
        }

        .notif-1 { top: -18px; right: 30px; animation-delay: 0s; }
        .notif-2 { bottom: 60px; right: -20px; animation-delay: 1.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .notif-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .notif-icon.red   { background: #FEE2E2; }
        .notif-icon.amber { background: #FEF3C7; }
        .notif-text { font-size: 12px; font-weight: 600; color: var(--text); white-space: nowrap; }
        .notif-sub  { font-size: 11px; color: var(--text-light); }

        /* ── CATEGORIES STRIP ── */
        .categories {
            background: var(--bg);
            padding: 50px 60px;
            text-align: center;
        }

        .categories h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 32px;
        }

        .cats-row {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 100px;
        }

        .cat-card:hover {
            border-color: var(--green);
            box-shadow: 0 4px 16px rgba(29,185,84,0.12);
            transform: translateY(-3px);
        }

        .cat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }

        .cat-label { font-size: 12px; font-weight: 500; color: var(--text); }

        /* ── WHAT IS SAJILO ── */
        .what {
            padding: 80px 60px;
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .section-eyebrow {
            font-size: 13px;
            font-weight: 600;
            color: var(--green);
            letter-spacing: 0.04em;
            margin-bottom: 14px;
        }

        .what h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(28px, 3.5vw, 42px);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .what p {
            font-size: 16px;
            color: var(--text-sub);
            line-height: 1.75;
        }

        /* ── FEATURES GRID ── */
        .features {
            padding: 40px 60px 80px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .features-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(26px, 3vw, 38px);
            font-weight: 800;
            text-align: center;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .features-sub {
            text-align: center;
            font-size: 15px;
            color: var(--text-sub);
            max-width: 560px;
            margin: 0 auto 48px;
            line-height: 1.7;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .feat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 0;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
        }

        .feat-card:hover {
            border-color: var(--green);
            box-shadow: 0 8px 32px rgba(29,185,84,0.12);
            transform: translateY(-4px);
        }

        .feat-img {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Individual feature illustration backgrounds */
        .feat-img.sales   { background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); }
        .feat-img.parties { background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%); }
        .feat-img.stock   { background: linear-gradient(135deg, #DCFCE7 0%, #BBF7D0 100%); }
        .feat-img.reports { background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%); }

        .feat-mockup {
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            padding: 12px;
            width: 130px;
        }

        .fm-row {
            height: 8px;
            border-radius: 4px;
            margin-bottom: 6px;
        }
        .fm-row.green  { background: #BBF7D0; }
        .fm-row.blue   { background: #BFDBFE; }
        .fm-row.red    { background: #FECACA; }
        .fm-row.short  { width: 60%; }
        .fm-row.medium { width: 80%; }
        .fm-row.full   { width: 100%; }

        /* Sales mockup rows with colors */
        .fm-sale {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }
        .fm-avatar {
            width: 20px; height: 20px;
            border-radius: 50%;
            background: #E5E7EB;
            flex-shrink: 0;
        }
        .fm-line { flex: 1; height: 7px; border-radius: 3px; background: #E5E7EB; }
        .fm-amount {
            font-size: 9px;
            font-weight: 700;
            color: var(--green);
            white-space: nowrap;
        }

        /* Bar chart for reports */
        .fm-bars {
            display: flex;
            align-items: flex-end;
            gap: 5px;
            height: 50px;
            padding-top: 6px;
        }
        .fm-bar {
            flex: 1;
            border-radius: 3px 3px 0 0;
        }
        .fm-bar.g { background: var(--green); }
        .fm-bar.r { background: #FECACA; }

        .feat-body { padding: 18px; }
        .feat-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text);
        }
        .feat-desc { font-size: 13px; color: var(--text-sub); line-height: 1.6; }

        /* ── HOW IT WORKS ── */
        .how {
            background: var(--bg);
            padding: 80px 60px;
        }

        .how-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .how h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(26px, 3vw, 38px);
            font-weight: 800;
            text-align: center;
            margin-bottom: 48px;
            letter-spacing: -0.02em;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            position: relative;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 28px;
            left: calc(16.67% + 14px);
            right: calc(16.67% + 14px);
            height: 2px;
            background: linear-gradient(90deg, var(--green), #BBF7D0);
        }

        .step {
            background: var(--white);
            border-radius: 18px;
            padding: 28px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        .step:hover { border-color: var(--green); box-shadow: var(--shadow-md); }

        .step-num {
            width: 56px; height: 56px;
            background: var(--green);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
        }

        .step-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .step-desc { font-size: 13px; color: var(--text-sub); line-height: 1.7; }

        /* ── CTA BANNER ── */
        .cta {
            background: linear-gradient(135deg, #0F2E1A 0%, #1A4A28 60%, #16A34A 100%);
            padding: 80px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: -100px; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 400px;
            background: radial-gradient(ellipse, rgba(29,185,84,0.2) 0%, transparent 70%);
        }

        .cta h2 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 800;
            color: #fff;
            margin-bottom: 14px;
            letter-spacing: -0.02em;
            position: relative;
        }

        .cta p {
            font-size: 16px;
            color: rgba(255,255,255,0.65);
            margin-bottom: 36px;
            position: relative;
        }

        .btn-cta {
            padding: 14px 36px;
            background: var(--green);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }
        .btn-cta:hover { background: #17a74a; transform: translateY(-2px); box-shadow: 0 12px 32px rgba(29,185,84,0.4); }

        /* ── FOOTER ── */
        footer {
            background: var(--white);
            border-top: 1px solid var(--border);
            padding: 36px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo-text {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 700;
            font-size: 16px;
            color: var(--green-dark);
        }

        footer p { font-size: 13px; color: var(--text-light); }

        /* ── ANIMATIONS ── */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        .feat-card:nth-child(1) { transition-delay: 0s; }
        .feat-card:nth-child(2) { transition-delay: 0.1s; }
        .feat-card:nth-child(3) { transition-delay: 0.2s; }
        .feat-card:nth-child(4) { transition-delay: 0.3s; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-left  { animation: fadeUp 0.7s 0.1s ease both; }
        .hero-visual { animation: fadeUp 0.7s 0.3s ease both; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="#" class="nav-logo">
       
        <span class="logo-text">Sajilo</span>
    </a>

    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how">How It Works</a></li>
        <li><a href="#cta">Contact</a></li>
    </ul>

    <div class="nav-actions">
        <a href="login.php" class="btn-outline">Log In</a>
        <a href="login.php?mode=signup" class="btn-green">Get Started →</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-left">
        <div class="hero-badge">
            <div class="badge-shield">
                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            digital Business partner
        </div>
        <h1>Manage Your Business<br>Anytime, Anywhere</h1>
        <p>Sajilo makes business tracking simple, smart, and stress-free. Track products, record sales, manage customer warranties, and monitor outstanding payments — all in one place.</p>
        <div class="hero-actions">
            <a href="login.php?mode=signup" class="btn-hero-green">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                Get Started Now
            </a>
            <a href="#features" class="btn-hero-outline">
                Learn More ↓
            </a>
        </div>
    </div>

    <!-- Visual -->
    <div class="hero-visual">
        <div class="hero-bg-shape"></div>

        <!-- Floating alert cards -->
        <div class="notif notif-1">
            <div class="notif-icon red">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div class="notif-text">Payment Reminder</div>
                <div class="notif-sub">Rs 4,500 balance due</div>
            </div>
        </div>

        <div class="notif notif-2">
            <div class="notif-icon amber">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="notif-text">Warranty Expiring</div>
                <div class="notif-sub">3 items in 30 days</div>
            </div>
        </div>

        <div class="hero-card-main">
            <div class="hc-header">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <span class="hc-title">Sajilo Dashboard</span>
                <div class="hc-dots">
                    <div class="hc-dot"></div>
                    <div class="hc-dot"></div>
                    <div class="hc-dot"></div>
                </div>
            </div>
            <div class="hc-body">
                <div class="hc-stats">
                    <div class="hc-stat">
                        <div class="hc-stat-num">284</div>
                        <div class="hc-stat-label">Products</div>
                    </div>
                    <div class="hc-stat">
                        <div class="hc-stat-num green">142</div>
                        <div class="hc-stat-label">Active Warranty</div>
                    </div>
                    <div class="hc-stat">
                        <div class="hc-stat-num orange">12</div>
                        <div class="hc-stat-label">Expiring Soon</div>
                    </div>
                </div>
                <div class="hc-table">
                    <div class="hc-th">
                        <div>Product</div>
                        <div>Model No.</div>
                        <div>Warranty</div>
                        <div>Status</div>
                    </div>
                    <div class="hc-tr">
                        <div class="hc-name">Samsung TV 55"</div>
                        <div>UA55AU8000</div>
                        <div>Dec 2026</div>
                        <div><span class="badge ok">Active</span></div>
                    </div>
                    <div class="hc-tr">
                        <div class="hc-name">LG Refrigerator</div>
                        <div>GN-B392PLGB</div>
                        <div>Mar 2025</div>
                        <div><span class="badge warn">Expiring</span></div>
                    </div>
                    <div class="hc-tr">
                        <div class="hc-name">HP Laptop</div>
                        <div>15s-du3047TX</div>
                        <div>Jan 2024</div>
                        <div><span class="badge exp">Expired</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORY STRIP -->
<section class="categories">
    <h2>Built for all growing businesses</h2>
    <div class="cats-row">
        <div class="cat-card">
            <div class="cat-icon" style="background:#DCFCE7; font-size:22px;">📱</div>
            <div class="cat-label">Electronics</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#DBEAFE; font-size:22px;">🏠</div>
            <div class="cat-label">Appliances</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#EDE9FE; font-size:22px;">👗</div>
            <div class="cat-label">Clothing</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#FEF3C7; font-size:22px;">🍔</div>
            <div class="cat-label">Food & Bev</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#FEE2E2; font-size:22px;">⚕️</div>
            <div class="cat-label">Medical</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#F0FDF4; font-size:22px;">🔧</div>
            <div class="cat-label">Hardware</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#FFF7ED; font-size:22px;">🚗</div>
            <div class="cat-label">Auto Parts</div>
        </div>
        <div class="cat-card">
            <div class="cat-icon" style="background:#ECFDF5; font-size:22px;">📦</div>
            <div class="cat-label">General</div>
        </div>
    </div>
</section>

<!-- WHAT IS SAJILO -->
<section class="what reveal">
    <div class="section-eyebrow">What is Sajilo?</div>
    <h2>The simplest way to<br>manage your business</h2>
    <p>Sajilo is your digital business partner, helping you manage product inventory, customer sales, warranty tracking and payment collection — available on desktop.</p>
</section>

<!-- ALL IN ONE FEATURES -->
<section class="features" id="features">
    <div class="reveal">
        <div class="features-title">All in One Business App</div>
        <div class="features-sub">Manage your products, customers, warranties and sales effortlessly — everything your business needs, all in one place.</div>
    </div>

    <div class="features-grid">
        <!-- Record Transactions -->
        <div class="feat-card reveal">
            <div class="feat-img sales">
                <div class="feat-mockup">
                    <div style="font-size:9px;font-weight:700;color:#6B7280;margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;">Recent Sales</div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#BBF7D0;"></div>
                        <div class="fm-line" style="background:#E5E7EB;"></div>
                        <span class="fm-amount">+12,000</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#BFDBFE;"></div>
                        <div class="fm-line" style="background:#E5E7EB;"></div>
                        <span class="fm-amount">+8,500</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#FDE68A;"></div>
                        <div class="fm-line" style="background:#E5E7EB;"></div>
                        <span class="fm-amount" style="color:#DC2626;">-2,000</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#DDD6FE;"></div>
                        <div class="fm-line" style="background:#E5E7EB;"></div>
                        <span class="fm-amount">+6,700</span>
                    </div>
                </div>
            </div>
            <div class="feat-body">
                <div class="feat-title">Record Transactions</div>
                <div class="feat-desc">Record sales to customers with payment tracking and auto-calculate outstanding balances.</div>
            </div>
        </div>

        <!-- Manage Customers -->
        <div class="feat-card reveal">
            <div class="feat-img parties">
                <div class="feat-mockup">
                    <div style="font-size:9px;font-weight:700;color:#6B7280;margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;">Customers</div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#DDD6FE;"></div>
                        <div class="fm-line"></div>
                        <span style="font-size:9px;font-weight:700;color:#7C3AED;">12,000</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#C4B5FD;"></div>
                        <div class="fm-line"></div>
                        <span style="font-size:9px;font-weight:700;color:#7C3AED;">15,000</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#A78BFA;"></div>
                        <div class="fm-line"></div>
                        <span style="font-size:9px;font-weight:700;color:#DC2626;">-4,500</span>
                    </div>
                    <div class="fm-sale">
                        <div class="fm-avatar" style="background:#EDE9FE;"></div>
                        <div class="fm-line"></div>
                        <span style="font-size:9px;font-weight:700;color:#7C3AED;">26,000</span>
                    </div>
                </div>
            </div>
            <div class="feat-body">
                <div class="feat-title">Manage Customers</div>
                <div class="feat-desc">Track customer contact info, purchase history, and outstanding balances in one view.</div>
            </div>
        </div>

        <!-- Manage Inventory -->
        <div class="feat-card reveal">
            <div class="feat-img stock">
                <div style="position:relative;z-index:1;">
                    <div class="feat-mockup" style="width:120px;">
                        <div style="font-size:9px;font-weight:700;color:#6B7280;margin-bottom:8px;">Inventory</div>
                        <div class="fm-row green full"></div>
                        <div class="fm-row green medium"></div>
                        <div class="fm-row" style="background:#FEE2E2;width:40%;"></div>
                        <div class="fm-row green short"></div>
                        <div class="fm-row green full"></div>
                    </div>
                    <!-- Floating +/- badges -->
                    <div style="position:absolute;top:-10px;right:-30px;background:#1DB954;color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;box-shadow:0 4px 12px rgba(29,185,84,0.4);">+</div>
                    <div style="position:absolute;bottom:-10px;right:-20px;background:#EF4444;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;box-shadow:0 4px 12px rgba(239,68,68,0.4);">−</div>
                </div>
            </div>
            <div class="feat-body">
                <div class="feat-title">Manage Inventory</div>
                <div class="feat-desc">Keep track of product stock in real time. Know what's in store and what needs restocking.</div>
            </div>
        </div>

        <!-- Business Insights -->
        <div class="feat-card reveal">
            <div class="feat-img reports">
                <div class="feat-mockup">
                    <div style="font-size:9px;font-weight:700;color:#6B7280;margin-bottom:6px;">Revenue</div>
                    <div style="display:flex;justify-content:space-between;font-size:9px;color:#6B7280;margin-bottom:4px;">
                        <span style="font-size:11px;font-weight:700;color:#1D4ED8;">Rs 2,14,000</span>
                        <span style="color:#16A34A;font-weight:600;">↑ 18%</span>
                    </div>
                    <div class="fm-bars">
                        <div class="fm-bar g" style="height:60%;"></div>
                        <div class="fm-bar r" style="height:40%;"></div>
                        <div class="fm-bar g" style="height:75%;"></div>
                        <div class="fm-bar g" style="height:50%;"></div>
                        <div class="fm-bar g" style="height:90%;"></div>
                        <div class="fm-bar r" style="height:30%;"></div>
                        <div class="fm-bar g" style="height:85%;"></div>
                    </div>
                </div>
            </div>
            <div class="feat-body">
                <div class="feat-title">Business Insights</div>
                <div class="feat-desc">View revenue, outstanding balances, and warranty alerts. Make smarter decisions with real data.</div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how">
    <div class="how-inner">
        <h2 class="reveal">Up and running in 3 steps</h2>
        <div class="steps">
            <div class="step reveal">
                <div class="step-num">1</div>
                <div class="step-title">Add Your Products</div>
                <div class="step-desc">Enter product name, model number, purchase date, cost price, selling price and supplier. Takes 30 seconds per item.</div>
            </div>
            <div class="step reveal">
                <div class="step-num">2</div>
                <div class="step-title">Record Sales</div>
                <div class="step-desc">Link products to customers when you sell. Set warranty months and track how much was paid vs. what's owed.</div>
            </div>
            <div class="step reveal">
                <div class="step-num">3</div>
                <div class="step-title">Track & Alert</div>
                <div class="step-desc">Get instant visibility on expiring warranties and outstanding balances. Never lose track of what your customers owe.</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta" id="cta">
    <h2>Ready to simplify<br>your business?</h2>
    <p>Join shop owners who never lose track of products, warranties, or payments again.</p>
    <a href="login.php?mode=signup" class="btn-cta">Get Started Free →</a>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-logo">
        <div class="logo-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
        </div>
        <span class="footer-logo-text">Sajilo</span>
    </div>
    <p>Business tracking, made simple.</p>
    <p>© 2026 Sajilo</p>
</footer>

<script>
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>