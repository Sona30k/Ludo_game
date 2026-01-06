<?php include 'includes/header.php'; ?>

<?php
$message = '';

// Handle credit/debit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    $type = $_POST['type'] ?? ''; // credit or debit
    $reason = trim($_POST['reason'] ?? '');

    if ($user_id <= 0 || $amount <= 0 || !in_array($type, ['credit', 'debit']) || empty($reason)) {
        $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Invalid data!</div>';
    } else {
        try {
            $pdo->beginTransaction();

            $sign = $type === 'credit' ? '+' : '-';
            $stmt = $pdo->prepare("UPDATE wallets SET balance = balance $sign ? WHERE user_id = ?");
            $stmt->execute([$amount, $user_id]);

            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, reason) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $amount, $type, $reason]);

            $pdo->commit();
            $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">Wallet updated successfully! ' . ucfirst($type) . 'ed Rs. ' . $amount . '</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Search user
$search = $_GET['search'] ?? '';
$users = [];
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.mobile, w.balance 
        FROM users u 
        LEFT JOIN wallets w ON u.id = w.user_id 
        WHERE (u.username LIKE ? OR u.mobile LIKE ?) AND u.role = 'user'
        ORDER BY u.username
        LIMIT 50
    ");
    $stmt->execute(["%$search%", "%$search%"]);
    $users = $stmt->fetchAll();
}
?>

<div class="max-w-5xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Wallet Management</h2>

    <?= $message ?>

    <!-- Search User -->
    <div class="bg-white/10 backdrop-blur rounded-2xl p-8 mb-10 border border-white/20">
        <h3 class="text-xl font-bold mb-6">Search User to Manage Wallet</h3>
        <form method="GET" class="flex gap-4">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Enter username or mobile..." 
                   class="flex-1 bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required />
            <button type="submit" class="bg-p2 hover:bg-blue-600 px-8 py-4 rounded-xl font-bold">
                Search
            </button>
        </form>
    </div>

    <?php if (!empty($search)): ?>
        <?php if (empty($users)): ?>
            <div class="text-center py-12 text-gray-400">No users found</div>
        <?php else: ?>
            <div class="grid gap-6">
                <?php foreach ($users as $u): ?>
                    <div class="bg-white/10 backdrop-blur rounded-2xl p-8 border border-white/20">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <p class="text-2xl font-bold"><?= htmlspecialchars($u['username']) ?></p>
                                <p class="text-lg opacity-80">Mobile: <?= htmlspecialchars($u['mobile']) ?></p>
                                <p class="text-3xl font-bold text-green-400 mt-2">Current Balance: Rs. <?= number_format($u['balance'] ?? 0) ?></p>
                            </div>
                        </div>

                        <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />

                            <select name="type" class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required>
                                <option value="credit">Credit (+)</option>
                                <option value="debit">Debit (-)</option>
                            </select>

                            <input type="number" name="amount" min="1" placeholder="Amount" 
                                   class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required />

                            <input type="text" name="reason" placeholder="Reason (e.g., Bonus, Refund)" 
                                   class="bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required />

                            <button type="submit" class="bg-p2 hover:bg-blue-600 px-8 py-4 rounded-xl font-bold transition">
                                Update Wallet
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-20 text-gray-400 text-xl">
            Search a user above to manage their wallet
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>