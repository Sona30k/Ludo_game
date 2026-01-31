<?php include 'includes/header.php'; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
    <?php
    $active_games = $pdo->query("SELECT COUNT(*) FROM games WHERE status IN ('waiting', 'ongoing')")->fetchColumn();
    $total_transactions = $pdo->query("SELECT COUNT(*) FROM wallet_transactions")->fetchColumn();
    ?>
    <div class="bg-white/20 backdrop-blur-md border border-white/10 rounded-2xl p-6 shadow-xl">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-white/70 text-sm font-medium">Total Users</p>
                <p class="text-5xl font-bold text-white mt-3"><?= $total_users ?></p>
            </div>
            <i class="ph ph-users text-6xl text-white/20"></i>
        </div>
    </div>

    <div class="bg-white/20 backdrop-blur-md border border-white/10 rounded-2xl p-6 shadow-xl">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-white/70 text-sm font-medium">Open Tables</p>
                <p class="text-5xl font-bold text-green-400 mt-3"><?= $open_tables ?></p>
            </div>
            <i class="ph ph-table text-6xl text-white/20"></i>
        </div>
    </div>

    <div class="bg-white/20 backdrop-blur-md border border-white/10 rounded-2xl p-6 shadow-xl">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-white/70 text-sm font-medium">Active Games</p>
                <p class="text-5xl font-bold text-yellow-400 mt-3"><?= $active_games ?></p>
            </div>
            <i class="ph ph-game-controller text-6xl text-white/20"></i>
        </div>
    </div>

    <div class="bg-white/20 backdrop-blur-md border border-white/10 rounded-2xl p-6 shadow-xl">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-white/70 text-sm font-medium">Total Transactions</p>
                <p class="text-5xl font-bold text-blue-400 mt-3"><?= $total_transactions ?></p>
            </div>
            <i class="ph ph-credit-card text-6xl text-white/20"></i>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white/20 backdrop-blur-md border border-white/10 rounded-2xl p-8 shadow-xl">
    <h2 class="text-2xl font-bold text-white mb-8">Quick Actions</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <a href="tables.php" class="bg-p2/80 hover:bg-p2 backdrop-blur border border-p2/50 p-8 rounded-2xl text-center transition shadow-lg">
            <i class="ph ph-plus text-5xl mb-4 text-white"></i>
            <p class="font-bold text-white">Create Table</p>
        </a>
        <a href="users.php" class="bg-green-600/80 hover:bg-green-600 backdrop-blur border border-green-500/50 p-8 rounded-2xl text-center transition shadow-lg">
            <i class="ph ph-user-switch text-5xl mb-4 text-white"></i>
            <p class="font-bold text-white">Manage Users</p>
        </a>
        <a href="payments.php" class="bg-yellow-600/80 hover:bg-yellow-600 backdrop-blur border border-yellow-500/50 p-8 rounded-2xl text-center transition shadow-lg">
            <i class="ph ph-money text-5xl mb-4 text-white"></i>
            <p class="font-bold text-white">Payments</p>
        </a>
        <a href="dice-control.php" class="bg-orange-600/80 hover:bg-orange-600 backdrop-blur border border-orange-500/50 p-8 rounded-2xl text-center transition shadow-lg">
            <i class="ph ph-dice-six text-5xl mb-4 text-white"></i>
            <p class="font-bold text-white">Dice Control</p>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>