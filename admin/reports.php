<?php include 'includes/header.php'; ?>

<?php
// Filters
$search_user = $_GET['user'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$type_filter = $_GET['type'] ?? ''; // credit/debit/all

$where = [];
$params = [];

if (!empty($search_user)) {
    $where[] = "(u.username LIKE ? OR u.mobile LIKE ?)";
    $params[] = "%$search_user%";
    $params[] = "%$search_user%";
}
if (!empty($date_from)) {
    $where[] = "wt.timestamp >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if (!empty($date_to)) {
    $where[] = "wt.timestamp <= ?";
    $params[] = $date_to . ' 23:59:59';
}
if ($type_filter && in_array($type_filter, ['credit', 'debit'])) {
    $where[] = "wt.type = ?";
    $params[] = $type_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Transactions
$stmt = $pdo->prepare("
    SELECT wt.*, u.username, u.mobile 
    FROM wallet_transactions wt
    JOIN users u ON wt.user_id = u.id
    $where_sql
    ORDER BY wt.timestamp DESC
    LIMIT 200
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Stats
$total_credit = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'credit'")->fetchColumn();
$total_debit = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'debit'")->fetchColumn();
$total_games = $pdo->query("SELECT COUNT(*) FROM games WHERE status = 'completed'")->fetchColumn();
$total_prize = $pdo->query("SELECT COALESCE(SUM(t.entry_points * CASE WHEN t.type = '2-player' THEN 2 ELSE 4 END), 0)
                           FROM games g JOIN tables t ON g.table_id = t.id WHERE g.status = 'completed'")->fetchColumn();
?>

<div class="max-w-7xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Reports & Analytics</h2>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
        <div class="bg-gradient-to-br from-green-900/60 to-green-800/40 backdrop-blur-lg border border-green-500/30 rounded-3xl p-8 shadow-2xl">
            <p class="text-green-300 text-lg font-medium">Total Credit</p>
            <p class="text-6xl font-extrabold text-white mt-4">Rs. <?= number_format($total_credit) ?></p>
        </div>
        <div class="bg-gradient-to-br from-red-900/60 to-red-800/40 backdrop-blur-lg border border-red-500/30 rounded-3xl p-8 shadow-2xl">
            <p class="text-red-300 text-lg font-medium">Total Debit</p>
            <p class="text-6xl font-extrabold text-white mt-4">Rs. <?= number_format($total_debit) ?></p>
        </div>
        <div class="bg-gradient-to-br from-blue-900/60 to-blue-800/40 backdrop-blur-lg border border-blue-500/30 rounded-3xl p-8 shadow-2xl">
            <p class="text-blue-300 text-lg font-medium">Completed Games</p>
            <p class="text-6xl font-extrabold text-white mt-4"><?= $total_games ?></p>
        </div>
        <div class="bg-gradient-to-br from-purple-900/60 to-purple-800/40 backdrop-blur-lg border border-purple-500/30 rounded-3xl p-8 shadow-2xl">
            <p class="text-purple-300 text-lg font-medium">Total Prize Pool</p>
            <p class="text-6xl font-extrabold text-white mt-4">Rs. <?= number_format($total_prize) ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white/10 backdrop-blur rounded-3xl p-8 mb-10 border border-white/20">
        <h3 class="text-2xl font-bold mb-6">Filter Transactions</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <input type="text" name="user" value="<?= htmlspecialchars($search_user) ?>" placeholder="Username or Mobile" 
                   class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" />
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" 
                   class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" />
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" 
                   class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" />
            <select name="type" class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2">
                <option value="">All Types</option>
                <option value="credit" <?= $type_filter === 'credit' ? 'selected' : '' ?>>Credit Only</option>
                <option value="debit" <?= $type_filter === 'debit' ? 'selected' : '' ?>>Debit Only</option>
            </select>
            <div class="flex gap-4">
                <button type="submit" class="bg-p2 hover:bg-blue-600 px-8 py-4 rounded-xl font-bold flex-1">
                    Apply Filter
                </button>
                <?php if (!empty($_GET)): ?>
                    <a href="reports.php" class="bg-gray-600 hover:bg-gray-700 px-8 py-4 rounded-xl font-bold flex-1 text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white/10 backdrop-blur rounded-3xl border border-white/20 overflow-hidden shadow-2xl">
        <h3 class="text-2xl font-bold p-8 border-b border-white/10">Wallet Transactions</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-left">User</th>
                        <th class="px-6 py-4 text-left">Type</th>
                        <th class="px-6 py-4 text-left">Amount</th>
                        <th class="px-6 py-4 text-left">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="px-6 py-16 text-center text-gray-400 text-lg">No transactions found</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr class="border-t border-white/10 hover:bg-white/5 transition">
                                <td class="px-6 py-4"><?= date('d M Y, h:i A', strtotime($t['timestamp'])) ?></td>
                                <td class="px-6 py-4">
                                    <p class="font-medium"><?= htmlspecialchars($t['username']) ?></p>
                                    <p class="text-sm opacity-70"><?= htmlspecialchars($t['mobile']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-4 py-2 rounded-full text-sm <?= $t['type'] === 'credit' ? 'bg-green-600/30 text-green-300' : 'bg-red-600/30 text-red-300' ?>">
                                        <?= ucfirst($t['type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-2xl font-bold <?= $t['type'] === 'credit' ? 'text-green-400' : 'text-red-400' ?>">
                                    <?= $t['type'] === 'credit' ? '+' : '-' ?>Rs. <?= $t['amount'] ?>
                                </td>
                                <td class="px-6 py-4"><?= htmlspecialchars($t['reason'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>