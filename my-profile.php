<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}

require 'includes/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, mobile FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

require 'includes/functions.php';
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
    <title>My Profile</title>
    <link href="style.css" rel="stylesheet">
</head>
<body class="">
    <div class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-color1">
        <!-- Background Effects -->
        <img src="assets/images/header-bg-1.png" alt="" class="absolute top-0 left-0 right-0 -mt-20" />
        <div class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"></div>
        <div class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"></div>
        <div class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"></div>
        <div class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"></div>

        <!-- Header -->
        <div class="relative z-10 px-6">
            <div class="flex justify-between items-center gap-4">
                <div class="flex justify-start items-center gap-4">
                    <a href="home.php" class="bg-white size-8 rounded-full flex justify-center items-center text-xl dark:bg-color10">
                        <i class="ph ph-caret-left"></i>
                    </a>
                    <h2 class="text-2xl font-semibold text-white">My Profile</h2>
                </div>
            </div>

            <!-- Profile Info -->
            <div class="flex justify-center items-end pt-16 gap-8">
                <div class="relative size-40 flex justify-center items-center">
                    <img src="assets/images/user-img.png" alt="Profile" class="size-32 rounded-full object-cover" />
                </div>
            </div>

            <div class="flex justify-center items-center pt-5 flex-col pb-5">
                <div class="flex justify-start items-center gap-1 text-2xl">
                    <p class="font-semibold"><?= htmlspecialchars($user['username']) ?></p>
                    <i class="ph-fill ph-seal-check text-p1"></i>
                </div>
                <p class="text-color5 pt-1 dark:text-bgColor20 font-semibold">
                    Mobile: <?= htmlspecialchars($user['mobile']) ?>
                </p>
            </div>

            <!-- Wallet Summary -->
            <div class="flex justify-between items-center gap-6 bg-white py-3 px-5 border border-color21 dark:border-color24 rounded-2xl dark:bg-color9">
                <div>
                    <p class="text-p2 font-semibold dark:text-p1">Rs. <?= number_format($balance) ?></p>
                    <p class="text-xs">Current Balance</p>
                </div>
                <a href="my-wallet.php" class="flex justify-start items-center gap-2">
                    <p class="text-p2 font-semibold text-sm dark:text-p1">View Wallet</p>
                    <div class="text-p2 dark:text-p1 border border-color14 p-2 rounded-full bg-color16 dark:bg-bgColor14 dark:border-bgColor16">
                        <i class="ph ph-caret-right"></i>
                    </div>
                </a>
            </div>

            <!-- Simple Stats (Optional – baad mein real data se fill kar sakte hain) -->
            <div class="p-5 mt-8 bg-p2 dark:bg-p1 rounded-2xl text-white text-center">
                <p class="text-sm font-semibold">Coming Soon</p>
                <p class="text-3xl font-bold mt-2">Game Stats & Achievements</p>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script defer src="index.js"></script>
</body>
</html>