<?php
session_start();

// Agar user logged in nahi toh sign-in pe bhej do
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Logout handle
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require 'includes/functions.php';

// User details fetch
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, mobile FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Wallet balance fetch (calculated from wallet_transactions)
$balance = calculateUserBalance($user_id, $pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="assets/css/swiper.min.css" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Welcome to Goodwin</title>
    <link href="style.css" rel="stylesheet">
</head>
<body class="-z-20">
    <div class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-black">
        <!-- Absolute Items -->
        <img src="assets/images/header-bg-1.png" alt="" class="absolute top-0 left-0 right-0 -mt-6" />
        <div class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"></div>
        <div class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"></div>
        <div class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"></div>
        <div class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"></div>

        <!-- Page Title -->
        <div class="relative z-10 pb-20">
            <div class="flex justify-between items-center gap-4 px-6 relative z-20">
                <div class="flex justify-start items-center gap-2">
                    <button class="sidebarModalOpenButton text-2xl text-white !leading-none">
                        <i class="ph ph-list"></i>
                    </button>
                    <h2 class="text-2xl font-semibold text-white">Goodwin</h2>
                </div>
                <div class="flex justify-start items-center gap-2">
                    <a href="my-profile.php" class="text-white border border-color24 p-2 rounded-full flex justify-center items-center bg-color24">
                        <i class="ph ph-user"></i>
                    </a>
                </div>
            </div>

            <!-- Search Box -->
            <div class="flex justify-between items-center gap-3 pt-8 px-6 relative z-20">
                <a href="upcoming-contest.php" class="flex justify-start items-center gap-3 bg-color24 border border-color24 p-4 rounded-full text-white w-full">
                    <i class="ph ph-magnifying-glass"></i>
                    <span class="text-white w-full text-xs"><span>Search Contest</span></span>
                </a>
            </div>

            
<!-- Banner Grid 3x2 -->
<div class="banner-grid grid grid-cols-2 gap-1 mt-4 px-6" style="margin-top: 25px; gap: 5px;">

    <!-- First banner - clickable game link -->
    <a href="upcoming-contest.php" class="banner-item relative block">
        <img src="assets/images/1.webp" alt="Banner 1" class="w-full h-full object-cover rounded-xl">
    </a>

    <!-- Coming Soon banners -->
    <div class="banner-item relative rounded-xl overflow-hidden">
        <img src="assets/images/2.webp" alt="Banner 2" class="w-full h-full object-cover">
        <!-- Highlighted text box -->
        <div class="absolute top-2 left-2 bg-p2 bg-opacity-90 px-3 py-1 rounded-md">
            <span class="text-white font-bold text-sm">Coming Soon</span>
        </div>
    </div>

    <div class="banner-item relative rounded-xl overflow-hidden">
        <img src="assets/images/3.webp" alt="Banner 3" class="w-full h-full object-cover">
        <div class="absolute top-2 left-2 bg-p2 bg-opacity-90 px-3 py-1 rounded-md">
            <span class="text-white font-bold text-sm">Coming Soon</span>
        </div>
    </div>

    <div class="banner-item relative rounded-xl overflow-hidden">
        <img src="assets/images/4.webp" alt="Banner 4" class="w-full h-full object-cover">
        <div class="absolute top-2 left-2 bg-p2 bg-opacity-90 px-3 py-1 rounded-md">
            <span class="text-white font-bold text-sm">Coming Soon</span>
        </div>
    </div>

    <div class="banner-item relative rounded-xl overflow-hidden">
        <img src="assets/images/5.webp" alt="Banner 5" class="w-full h-full object-cover">
        <div class="absolute top-2 left-2 bg-p2 bg-opacity-90 px-3 py-1 rounded-md">
            <span class="text-white font-bold text-sm">Coming Soon</span>
        </div>
    </div>

    <div class="banner-item relative rounded-xl overflow-hidden">
        <img src="assets/images/6.webp" alt="Banner 6" class="w-full h-full object-cover">
        <div class="absolute top-2 left-2 bg-p2 bg-opacity-90 px-3 py-1 rounded-md">
            <span class="text-white font-bold text-sm">Coming Soon</span>
        </div>
    </div>

</div>



                      <!-- Upcoming Contest (Dynamic + Real Join on Home) -->
            <div class="pt-12 pl-6">
                <div class="flex justify-between items-center pr-6 mb-5">
                    <h3 class="text-xl font-semibold">Upcoming Contest</h3>
                    <a href="upcoming-contest.php" class="text-p1 font-semibold text-sm">See All</a>
                </div>

                <div class="pt-5" id="homeTablesContainer" style="margin-right: 8%;">
                    <!-- Loading -->
                    <p class="text-center text-color5 py-8">Loading tables...</p>
                </div>
            </div>

            <script>
                async function loadHomeTables() {
                    try {
                        const res = await fetch('api/tables/list.php');
                        const data = await res.json();

                        const container = document.getElementById('homeTablesContainer');

                        if (!data.tables || data.tables.length === 0) {
                            container.innerHTML = '<p class="text-center text-color5 py-12 text-lg">No tables available right now</p>';
                            return;
                        }

                        // Show only first 3 tables
                        const tables = data.tables;

                        let html = '<div class="flex flex-col gap-4">';
                        tables.forEach(table => {
                            const players = table.type === '2-player' ? 2 : 4;
                            const prize = table.prize_pool || table.entry_points * players;
                            const spotsLeft = table.spots_left || players;
                            const progressPercent = Math.round((players - spotsLeft) / players * 100);

                            html += `
                            <div class="rounded-2xl overflow-hidden shadow2 border border-color21">
                                <div class="p-5 bg-white dark:bg-color10">
                                    <div class="flex justify-between items-center">
                                        <div class="flex justify-start items-center gap-2">
                                            <div class="py-1 px-2 text-white bg-p2 rounded-lg dark:bg-p1 dark:text-black">
                                                <p class="font-semibold text-xs">Live</p>
                                            </div>
                                            <div class="">
                                                <p class="font-semibold text-xs">${table.type}</p>
                                                <p class="text-xs">1 Winner</p>
                                            </div>
                                        </div>
                                        <div class="flex justify-start items-center gap-1">
                                            <p class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md">05</p>
                                            <p class="text-p2 text-base font-semibold dark:text-p1">:</p>
                                            <p class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md">14</p>
                                            <p class="text-p2 text-base font-semibold dark:text-p1">:</p>
                                            <p class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md">20</p>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center text-xs pt-5">
                                        <div class="flex gap-2">
                                            <p>Max Time</p>
                                            <p class="font-semibold">- ${table.time_limit}</p>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center gap-2 text-xs py-3 text-nowrap">
                                        <p>${spotsLeft} left</p>
                                        <div class="relative bg-p2 dark:bg-p1 dark:bg-opacity-10 bg-opacity-10 h-1 w-full rounded-full after:absolute after:h-1 after:w-[${progressPercent}%] after:bg-p2 after:dark:bg-p1 after:rounded-full"></div>
                                        <p>${players} spots</p>
                                    </div>
                                    <div class="border-b border-dashed border-black dark:border-color24 border-opacity-10 pb-5 flex justify-between items-center text-xs">
                                        <div class="flex justify-start items-center gap-2">
                                            <div class="text-white flex justify-center items-center p-2 bg-p1 rounded-full">
                                                <i class="ph ph-trophy"></i>
                                            </div>
                                            <div class="">
                                                <p>Price Pool</p>
                                                <p class="font-semibold">Rs. ${prize}</p>
                                            </div>
                                        </div>
                                        <div class="flex justify-start items-center gap-2">
                                            <button class="text-white text-xs bg-p2 py-1 px-4 rounded-full dark:bg-p1 join-home-btn" data-id="${table.id}">
                                                Join Now
                                            </button>
                                            <div class="">
                                                <p>Entry</p>
                                                <p class="font-semibold">Rs. ${table.entry_points}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        });
                        html += '</div>';

                        container.innerHTML = html;

                        // Join button handlers (same as upcoming-contest.php)
                        document.querySelectorAll('.join-home-btn').forEach(btn => {
                            btn.addEventListener('click', async (e) => {
                                e.preventDefault();
                                const tableId = btn.getAttribute('data-id');

                                if (!confirm('Join this table? Entry fee will be deducted.')) return;

                                btn.disabled = true;
                                btn.textContent = 'Joining...';

                                try {
                                    const res = await fetch('api/tables/join.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ table_id: tableId })
                                    });
                                    const result = await res.json();

                                    if (result.success) {
                                        // Redirect to game page
                                        window.location.href = result.redirect || 'game.php?table_id=' + tableId;
                                    } else {
                                        alert(result.error || 'Failed to join');
                                    }
                                } catch (err) {
                                    alert('Network error');
                                } finally {
                                    btn.disabled = false;
                                    btn.textContent = 'Join Now';
                                }
                            });
                        });

                    } catch (err) {
                        container.innerHTML = '<p class="text-center text-red-500 py-8">Error loading tables</p>';
                    }
                }

                document.addEventListener('DOMContentLoaded', loadHomeTables);
            </script>

    <!-- Bottom Tab -->
    <div class="fixed bottom-0 left-0 right-0 z-40">
        <div class="container bg-p2 px-6 py-3 rounded-t-2xl flex justify-around items-center dark:bg-p1">
            <a href="home.php" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-p1 dark:bg-p2">
                    <i class="ph ph-house text-xl !leading-none text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Home</p>
            </a>
            <a href="upcoming-contest.php" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-squares-four text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">All Contest</p>
            </a>
            <a href="earn-rewards.html" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-users-three text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Recharge</p>
            </a>
            <a href="chat.html" class="flex justify-center items-center text-center flex-col gap-1">
                <div class="flex justify-center items-center p-3 rounded-full bg-white dark:bg-color10">
                    <i class="ph ph-users-three text-xl !leading-none dark:text-white"></i>
                </div>
                <p class="text-xs text-white font-semibold dark:text-color10">Chat</p>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="hidden sidebarModal inset-0 z-50">
        <div class="container bg-black bg-opacity-80 h-full overflow-y-auto">
            <div class="w-[330px] bg-slate-50 relative">
                <button class="sidebarModalCloseButton absolute top-3 right-3 border rounded-full border-p1 flex justify-center items-center p-1 text-white">
                    <i class="ph ph-x"></i>
                </button>
                <div class="bg-p2 text-white pt-8 pb-4 px-5">
                    <div class="flex justify-start items-center gap-3 pb-6 border-b border-color24 border-dashed">
                        <div class="">
                            <p class="text-2xl font-semibold">
                                <?= htmlspecialchars($user['username']) ?> <i class="ph-fill ph-seal-check text-p1"></i>
                            </p>
                            <p class="text-xs">
                                <span class="font-semibold">ID :</span> <?= htmlspecialchars($user['mobile']) ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col">
                    <a href="my-profile.php" class="flex justify-between items-center py-3 px-4 border-b border-dashed border-color21 dark:bg-color1 dark:border-color24">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border bg-color16 border-color14 text-lg !leading-none text-p2 dark:bg-bgColor14 dark:border-bgColor16 dark:text-p1">
                                <i class="ph ph-user"></i>
                            </div>
                            <p class="font-semibold dark:text-white">My Profile</p>
                        </div>
                        <div class="flex justify-center items-center rounded-full text-p2 dark:text-p1">
                            <i class="ph ph-arrow-right"></i>
                        </div>
                    </a>
                    <a href="my-wallet.php" class="flex justify-between items-center py-3 px-4 border-b border-dashed border-color21 dark:bg-color1 dark:border-color24">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border bg-color16 border-color14 text-lg !leading-none text-p2 dark:bg-bgColor14 dark:border-bgColor16 dark:text-p1">
                                <i class="ph ph-wallet"></i>
                            </div>
                            <p class="font-semibold dark:text-white">Balance</p>
                        </div>
                        <p class="text-p1 font-semibold text-sm">Rs. <?= number_format($balance) ?></p>
                    </a>
                    <a href="settings.php" class="flex justify-between items-center py-3 px-4 border-b border-dashed border-color21 dark:bg-color1 dark:border-color24">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border bg-color16 border-color14 text-lg !leading-none text-p2 dark:bg-bgColor14 dark:border-bgColor16 dark:text-p1">
                                <i class="ph ph-gear-six"></i>
                            </div>
                            <p class="font-semibold dark:text-white">Settings</p>
                        </div>
                        <div class="flex justify-center items-center rounded-full text-p2 dark:text-p1">
                            <i class="ph ph-arrow-right"></i>
                        </div>
                    </a>
                    <a href="game-history.php" class="flex justify-between items-center py-3 px-4 border-b border-dashed border-color21 dark:bg-color1 dark:border-color24">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border bg-color16 border-color14 text-lg !leading-none text-p2 dark:bg-bgColor14 dark:border-bgColor16 dark:text-p1">
                                <i class="ph ph-chats-teardrop"></i>
                            </div>
                            <p class="font-semibold dark:text-white">Game History</p>
                        </div>
                        <div class="flex justify-center items-center rounded-full text-p2 dark:text-p1">
                            <i class="ph ph-arrow-right"></i>
                        </div>
                    </a>
                    <div class="flex justify-between items-center py-3 px-4 border-b border-dashed border-color21 dark:bg-color1 dark:border-color24">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border bg-color16 border-color14 text-lg !leading-none text-p2 dark:bg-bgColor14 dark:border-bgColor16 dark:text-p1">
                                <i class="ph ph-shield"></i>
                            </div>
                            <p class="font-semibold dark:text-white">Game Rules</p>
                        </div>
                        <div class="flex justify-center items-center rounded-full text-p2 dark:text-p1">
                            <i class="ph ph-arrow-right"></i>
                        </div>
                    </div>
                    <button class="flex justify-between items-center py-3 px-4 dark:bg-color1 withdrawModalOpenButton w-full text-left">
                        <div class="flex justify-start items-center gap-3">
                            <div class="flex justify-center items-center p-2 rounded-full border text-lg !leading-none bg-bgColor14 border-bgColor16 text-p1">
                                <i class="ph ph-sign-out"></i>
                            </div>
                            <p class="font-semibold text-p1">Logout</p>
                        </div>
                        <div class="flex justify-center items-center rounded-full text-p1">
                            <i class="ph ph-arrow-right"></i>
                        </div>
                    </button>
                </div>
                <div class="flex justify-between items-center p-4 bg-p2 dark:bg-p1 text-white">
                    <p class="text-sm">Rate this App</p>
                    <div class="flex justify-start items-center gap-1 text-yellow-400 dark:text-white">
                        <i class="ph-fill ph-star-half"></i>
                        <i class="ph-fill ph-star"></i>
                        <i class="ph-fill ph-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="hidden inset-0 withdrawModal z-50">
        <div class="bg-black opacity-40 absolute inset-0 container"></div>
        <div class="flex justify-end items-end flex-col h-full">
            <div class="container relative">
                <img src="assets/images/modal-bg-white.png" alt="" class="dark:hidden" />
                <img src="assets/images/modal-bg-black.png" alt="" class="hidden dark:block" />
                <div class="bg-white dark:bg-color1 relative z-40 overflow-auto pb-8">
                    <div class="px-6 pt-8 border-b border-color21 dark:border-color24 border-dashed pb-5 mx-6">
                        <p class="text-2xl text-p1 text-center font-semibold">Log Out</p>
                    </div>
                    <div class="pt-5 px-6">
                        <p class="text-color5 dark:text-white pb-8 text-center">
                            Are you sure you want to log out?
                        </p>
                        <div class="flex justify-between items-center gap-3">
                            <button class="withdrawModalCloseButton border border-color16 bg-color14 rounded-full py-3 text-p2 text-sm font-semibold text-center block dark:border-p1 w-full dark:text-white">
                                Cancel
                            </button>
                            <a href="?logout=true" class="bg-p2 rounded-full py-3 text-white text-sm font-semibold text-center block dark:bg-p1 w-full">
                                Yes, Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Js Dependencies -->
    <script src="assets/js/plugins/plugins.js"></script>
    <script src="assets/js/plugins/plugin-custom.js"></script>
    <script src="assets/js/plugins/circle-slider.js"></script>
    <script src="assets/js/main.js"></script>
    <script defer src="index.js"></script>
</body>
</html>