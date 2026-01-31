<?php require 'auth.php'; ?>
<?php require '../includes/db.php'; ?>
<?php
// Stats for sidebar/top
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$open_tables = $pdo->query("SELECT COUNT(*) FROM tables WHERE status = 'open'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Panel</title>
    <link href="../style.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
         /* ===== ADMIN CONTENT FORCE FIX ===== */
.admin-content {
    color: #e5e7eb !important; /* light gray */
}

.admin-content h1,
.admin-content h2,
.admin-content h3,
.admin-content p,
.admin-content span,
.admin-content div {
    color: #10264c !important;
}

        /* Custom Admin Styles */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            overflow-y: auto;
            z-index: 50;
            transition: all 0.3s;
        }
        .admin-sidebar.closed {
            width: 70px;
        }
        .admin-sidebar.closed .sidebar-text {
            display: none;
        }
        .admin-sidebar.closed .sidebar-icon {
            margin-right: 0;
        }
        .admin-main {
            margin-left: 280px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        .admin-main.shifted {
            margin-left: 70px;
        }
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 70px;
            }
            .admin-sidebar.open {
                width: 280px;
            }
            .admin-main {
                margin-left: 70px;
            }
            .admin-main.shifted {
                margin-left: 280px;
            }
            .mobile-menu-btn {
                display: block !important;
            }
        }
    </style>
</head>
<body class="bg-color1 min-h-screen">


    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn fixed top-4 left-4 z-50 bg-p2 p-3 rounded-full hidden">
        <i class="ph ph-list text-2xl"></i>
    </button>

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold sidebar-text">Admin Panel</h2>
                <button id="toggleSidebar" class="text-2xl hover:bg-white/10 p-2 rounded-lg">
                    <i class="ph ph-caret-left"></i>
                </button>
            </div>
        </div>

        <nav class="p-4">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition sidebar-active">
                    <i class="ph ph-house text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a></li>
                <li><a href="users.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-users text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Manage Users</span>
                </a></li>
                <li><a href="tables.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-table text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Create Tables</span>
                </a></li>
                <li><a href="wallet-management.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-wallet text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Wallet Control</span>
                </a></li>
                <li><a href="payments.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-money text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Payments</span>
                </a></li>
                <li><a href="dice-control.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-dice-six text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Dice Control</span>
                </a></li>
                <li><a href="reports.php" class="flex items-center gap-4 p-4 rounded-xl hover:bg-white/10 transition">
                    <i class="ph ph-chart-bar text-2xl sidebar-icon"></i>
                    <span class="sidebar-text">Reports</span>
                </a></li>
            </ul>
        </nav>

        <div class="p-6 border-t border-gray-700 mt-auto">
            <div class="bg-white/10 rounded-xl p-4">
                <p class="text-sm opacity-70">Active Tables</p>
                <p class="text-3xl font-bold"><?= $open_tables ?></p>
            </div>
            <div class="bg-white/10 rounded-xl p-4 mt-4">
                <p class="text-sm opacity-70">Total Users</p>
                <p class="text-3xl font-bold"><?= $total_users ?></p>
            </div>
        </div>
    </aside>

            <!-- Main Content -->
<main class="admin-main bg-color1 min-h-screen admin-content" id="mainContent">

        <div class="p-6 pt-20 lg:pt-6 text-white"> <!-- Yeh line add karo -->
            <div class="flex justify-between items-center mb-10">
                <h1 class="text-4xl font-bold drop-shadow-2xl" id="pageTitle">Dashboard</h1>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 px-8 py-4 rounded-2xl font-bold text-lg flex items-center gap-3 shadow-2xl">
                    <i class="ph ph-sign-out text-2xl"></i>
                    Logout
                </a>
            </div>

            <!-- Page Content Starts Here -->