<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}

require 'includes/db.php';

$user_id = $_SESSION['user_id'];

// User's game history (last 20)
$stmt = $pdo->prepare("
    SELECT 
        g.id AS game_id,
        g.status,
        g.winner_id,
        g.started_at,
        g.ended_at,
        t.type,
        t.time_limit,
        t.entry_points,
        (t.entry_points * CASE WHEN t.type = '2-player' THEN 2 ELSE 4 END) AS prize_pool
    FROM games g
    JOIN tables t ON g.table_id = t.id
    -- Abhi simple join track nahi hai, toh wallet transaction se link kar rahe hain
    WHERE EXISTS (
        SELECT 1 FROM wallet_transactions wt 
        WHERE wt.user_id = ? 
        AND wt.reason LIKE CONCAT('%table #', t.id)
        AND wt.type = 'debit'
    )
    ORDER BY g.started_at DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$games = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/logo.png" type="image/x-icon" />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Game History</title>
    <link href="style.css" rel="stylesheet">
</head>
<body class="">
    <div class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-color1">
        <!-- Background -->
        <img src="assets/images/header-bg-1.png" alt="" class="absolute top-0 left-0 right-0 -mt-16" />
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
                    <h2 class="text-2xl font-semibold text-white">Game History</h2>
                </div>
            </div>

            <!-- History List -->
            <div class="pt-12">
                <?php if (empty($games)): ?>
                    <div class="text-center pt-20">
                        <p class="text-5xl opacity-20 mb-4">🎲</p>
                        <p class="text-color5 text-lg">No games played yet</p>
                        <p class="text-sm text-color5 mt-2">Join a table to see history here</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-4 px-6">
                        <?php foreach ($games as $game): ?>
                            <?php
                            $is_winner = $game['winner_id'] == $user_id;
                            $status_text = $game['status'] === 'completed' 
                                ? ($is_winner ? 'Won' : 'Lost') 
                                : ($game['status'] === 'ongoing' ? 'Playing' : 'Waiting');
                            $status_color = $game['status'] === 'completed' 
                                ? ($is_winner ? 'text-green-500' : 'text-red-500')
                                : 'text-yellow-500';
                            ?>
                            <div class="bg-white dark:bg-color10 rounded-2xl p-5 border border-color21 dark:border-color24">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold"><?= $game['type'] ?> • <?= $game['time_limit'] ?></p>
                                        <p class="text-xs text-color5 mt-1">
                                            <?= date('d M Y, h:i A', strtotime($game['started_at'] ?? $game['ended_at'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-lg <?= $status_color ?>">
                                            <?= $status_text ?>
                                        </p>
                                        <?php if ($game['status'] === 'completed'): ?>
                                            <p class="text-sm <?= $is_winner ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= $is_winner ? '+' : '-' ?><?= $game['prize_pool'] - $game['entry_points'] ?> points
                                            </p>
                                        <?php else: ?>
                                            <p class="text-sm text-color5">-<?= $game['entry_points'] ?> entry</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mt-4 pt-4 border-t border-dashed border-color21 dark:border-color24">
                                    <div class="flex items-center gap-2">
                                        <i class="ph ph-trophy text-p1"></i>
                                        <p class="text-sm">Prize: Rs. <?= $game['prize_pool'] ?></p>
                                    </div>
                                    <div class="text-sm">
                                        Entry: Rs. <?= $game['entry_points'] ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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

    <script src="assets/js/main.js"></script>
    <script defer src="index.js"></script>
</body>
</html>