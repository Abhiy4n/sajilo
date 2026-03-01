<?php
/**
 * abhiyan_company - Sidebar Component
 * Usage: 
 * $active = 'dashboard'; // set this before including
 * include 'includes/sidebar.php';
 */

// 1. Prevent errors if session isn't started or user isn't logged in
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. Safely get user data
$full_name = $_SESSION['user_name'] ?? 'User';
$initial   = strtoupper(substr($full_name, 0, 1));

// 3. Logic-based Pathing (Standardizes all links)
$is_subfolder = str_contains($_SERVER['PHP_SELF'], '/pages/');
$root = $is_subfolder ? '../' : './';

// 4. Helper function for active states
if (!function_exists('nav_active')) {
    function nav_active(string $current, string $active): string {
        return ($current === $active) ? 'sb-link active' : 'sb-link';
    }
}
?>

<aside class="sidebar">
    <a href="<?= $root ?>dashboard.php" class="sb-logo">
        <div class="sb-logo-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
        </div>
        <span class="sb-logo-name">Sajilo</span>
    </a>

    <nav class="sb-nav">
        <div class="sb-label">Menu</div>

        <a href="<?= $root ?>dashboard.php" class="<?= nav_active('dashboard', $active) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Dashboard
        </a>

        <a href="<?= $root ?>pages/inventory.php" class="<?= nav_active('inventory', $active) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            Products
        </a>

        <a href="<?= $root ?>pages/customers.php" class="<?= nav_active('customers', $active) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Customers
        </a>

        <a href="<?= $root ?>pages/record_sale.php" class="<?= nav_active('record_sale', $active) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Record Sale
        </a>

        <a href="<?= $root ?>pages/reports.php" class="<?= nav_active('reports', $active) ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                <polyline points="17 6 23 6 23 12"/>
            </svg>
            Reports
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><?= $initial ?></div>
            <div class="sb-info-container">
                <div class="sb-uname"><?= htmlspecialchars($full_name) ?></div>
                <div class="sb-urole">Owner</div>
            </div>
        </div>
        <a href="<?= $root ?>logout.php" class="sb-link logout-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Logout
        </a>
    </div>
</aside>