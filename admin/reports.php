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

<style>
    .report-page {
        color: #e2e8f0;
    }

    .report-page .page-title {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 28px;
    }

    .report-page .page-subtitle {
        color: rgba(226, 232, 240, 0.7);
        font-size: 14px;
    }

    .report-page .stat-card {
        border-radius: 24px;
        padding: 26px 24px;
        box-shadow: 0 18px 40px rgba(8, 12, 32, 0.35);
        position: relative;
        overflow: hidden;
    }

    .report-page .stat-card::after {
        content: "";
        position: absolute;
        inset: auto -30% -40% auto;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
        opacity: 0.4;
        pointer-events: none;
    }

    .report-page .stat-label {
        font-size: 14px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .report-page .stat-value {
        margin-top: 16px;
        font-size: clamp(26px, 3vw, 38px);
        font-weight: 800;
        color: #fff;
    }

    .report-page .filter-card {
        border-radius: 24px;
        padding: 24px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(15, 23, 42, 0.55);
        box-shadow: 0 18px 40px rgba(8, 12, 32, 0.3);
    }

    .report-page .filter-card input,
    .report-page .filter-card select {
        color: #0f172a;
    }

    .report-page .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        border-radius: 14px;
        font-weight: 700;
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
    }

    .report-page .btn-secondary {
        background: rgba(148, 163, 184, 0.2);
        color: #e2e8f0;
        border-radius: 14px;
        font-weight: 700;
    }

    .report-page .table-card {
        border-radius: 26px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        background: rgba(15, 23, 42, 0.6);
        box-shadow: 0 18px 40px rgba(8, 12, 32, 0.35);
        overflow: hidden;
    }

    .report-page .table-title {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 20px;
        font-weight: 700;
    }

    .report-page table thead {
        background: rgba(15, 23, 42, 0.7);
    }

    .report-page table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: rgba(226, 232, 240, 0.7);
    }

    .report-page .row-hover:hover {
        background: rgba(148, 163, 184, 0.1);
    }

    .report-page .badge-credit {
        background: rgba(34, 197, 94, 0.2);
        color: #86efac;
    }

    .report-page .badge-debit {
        background: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
    }
</style>

<div class="max-w-7xl mx-auto report-page">
    <div class="page-title">
        <h2 class="text-3xl font-bold">Reports & Analytics</h2>
        <p class="page-subtitle">Track wallet activity and summary performance at a glance.</p>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <div class="stat-card bg-gradient-to-br from-green-900/60 to-green-800/40 border border-green-500/30">
            <p class="stat-label text-green-300">Total Credit</p>
            <p class="stat-value">Rs. <?= number_format($total_credit) ?></p>
        </div>
        <div class="stat-card bg-gradient-to-br from-red-900/60 to-red-800/40 border border-red-500/30">
            <p class="stat-label text-red-300">Total Debit</p>
            <p class="stat-value">Rs. <?= number_format($total_debit) ?></p>
        </div>
        <div class="stat-card bg-gradient-to-br from-blue-900/60 to-blue-800/40 border border-blue-500/30">
            <p class="stat-label text-blue-300">Completed Games</p>
            <p class="stat-value"><?= $total_games ?></p>
        </div>
        <div class="stat-card bg-gradient-to-br from-purple-900/60 to-purple-800/40 border border-purple-500/30">
            <p class="stat-label text-purple-300">Total Prize Pool</p>
            <p class="stat-value">Rs. <?= number_format($total_prize) ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card mb-10">
        <h3 class="text-2xl font-bold mb-6">Filter Transactions</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="text" name="user" value="<?= htmlspecialchars($search_user) ?>" placeholder="Username or Mobile"
                   class="bg-white/90 border border-white/20 rounded-xl px-5 py-3 focus:outline-none focus:border-blue-400" />
            <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>"
                   class="bg-white/90 border border-white/20 rounded-xl px-5 py-3 focus:outline-none focus:border-blue-400" />
            <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>"
                   class="bg-white/90 border border-white/20 rounded-xl px-5 py-3 focus:outline-none focus:border-blue-400" />
            <select name="type" class="bg-white/90 border border-white/20 rounded-xl px-5 py-3 focus:outline-none focus:border-blue-400">
                <option value="">All Types</option>
                <option value="credit" <?= $type_filter === 'credit' ? 'selected' : '' ?>>Credit Only</option>
                <option value="debit" <?= $type_filter === 'debit' ? 'selected' : '' ?>>Debit Only</option>
            </select>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary px-6 py-3 flex-1">
                    Apply Filter
                </button>
                <?php if (!empty($_GET)): ?>
                    <a href="reports.php" class="btn-secondary px-6 py-3 flex-1 text-center">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="table-card">
        <h3 class="table-title">Wallet Transactions</h3>
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
                            <tr class="border-t border-white/10 transition row-hover">
                                <td class="px-6 py-4"><?= date('d M Y, h:i A', strtotime($t['timestamp'])) ?></td>
                                <td class="px-6 py-4">
                                    <p class="font-medium"><?= htmlspecialchars($t['username']) ?></p>
                                    <p class="text-sm opacity-70"><?= htmlspecialchars($t['mobile']) ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-4 py-2 rounded-full text-sm <?= $t['type'] === 'credit' ? 'badge-credit' : 'badge-debit' ?>">
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
