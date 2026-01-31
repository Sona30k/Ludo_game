<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}

require 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Current balance
require 'includes/functions.php';
$balance = calculateUserBalance($user_id, $pdo);

// Transaction history (last 10)
$stmt = $pdo->prepare("
    SELECT amount, type, reason, created_at as timestamp 
    FROM wallet_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();
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
    <title>My Wallet</title>
    <link href="style.css" rel="stylesheet">
</head>
<body class="">
    <div class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-color1">
        <!-- Background -->
        <img src="assets/images/header-bg-1.png" alt="" class="absolute top-0 left-0 right-0 -mt-32" />
        <div class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"></div>
        <div class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"></div>
        <div class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"></div>
        <div class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"></div>

        <!-- Header -->
        <div class="relative z-10 px-6">
            <div class="flex justify-between items-center gap-4">
                <div class="flex justify-start items-center gap-4">
                    <a href="index.php" class="bg-white size-8 rounded-full flex justify-center items-center text-xl dark:bg-color10">
                        <i class="ph ph-caret-left"></i>
                    </a>
                    <h2 class="text-2xl font-semibold text-white">My Wallet</h2>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="p-5 mt-8 bg-p1 rounded-2xl flex justify-between items-center relative after:absolute after:h-full after:left-2 after:right-2 after:bg-p1 after:mt-6 after:opacity-30 after:rounded-2xl after:-z-10 before:absolute before:h-full before:bg-p1 before:mt-12 before:opacity-30 before:rounded-2xl before:-z-10 before:left-4 before:right-4">
                <div class="flex justify-start items-start gap-3">
                    <div class="size-12 bg-white rounded-full flex justify-center items-center text-color9 text-xl">
                        <i class="ph ph-wallet"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-white">Rs. <?= number_format($balance) ?></p>
                        <p class="text-xs text-bgColor5">Current Balance</p>
                    </div>
                </div>
                <button class="bg-white text-color9 py-2 px-5 rounded-xl font-semibold text-xs withdrawModalOpenButton">
                    Add Money
                </button>
            </div>

            <!-- Transaction History -->
            <div class="p-6 bg-white dark:bg-color10 rounded-2xl mt-8">
                <h3 class="text-lg font-semibold pb-4">Recent Transactions</h3>
                <?php if (empty($transactions)): ?>
                    <p class="text-center text-color5">No transactions yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($transactions as $t): ?>
                            <div class="flex justify-between items-center pb-3 border-b border-dashed border-color21 dark:border-color24">
                                <div class="flex items-center gap-3">
                                    <div class="text-2xl <?= $t['type'] === 'credit' ? 'text-green-500' : 'text-red-500' ?>">
                                        <i class="ph <?= $t['type'] === 'credit' ? 'ph-arrow-circle-up' : 'ph-arrow-circle-down' ?>"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold"><?= htmlspecialchars($t['reason'] ?? ucfirst($t['type'])) ?></p>
                                        <p class="text-xs text-color5"><?= date('d M Y, h:i A', strtotime($t['timestamp'])) ?></p>
                                    </div>
                                </div>
                                <p class="font-semibold <?= $t['type'] === 'credit' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $t['type'] === 'credit' ? '+' : '-' ?> Rs. <?= $t['amount'] ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Money Modal (Offline Payment Placeholder) -->
    <div class="hidden inset-0 z-50 withdrawModal">
        <div class="bg-black opacity-40 absolute inset-0"></div>
        <div class="flex justify-end items-end flex-col h-full">
            <div class="container relative">
                <img src="assets/images/modal-bg-white.png" alt="" class="dark:hidden" />
                <img src="assets/images/modal-bg-black.png" alt="" class="hidden dark:block" />
                <div class="bg-white dark:bg-color1 relative z-40 overflow-auto pb-8">
                    <div class="flex justify-between items-center px-6 pt-10">
                        <p class="text-2xl font-semibold dark:text-white">Add Money</p>
                        <button class="p-2 rounded-full border withdrawModalCloseButton">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                    <div class="px-6 pt-5">
                        <p class="text-center text-color5 dark:text-white pb-6">
                            Contact admin for recharge.<br>
                            Upload payment proof in offline payments section (coming soon).
                        </p>
                        <button class="w-full bg-p2 dark:bg-p1 text-white py-3 rounded-full font-semibold withdrawModalCloseButton">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script defer src="index.js"></script>
</body>
</html>