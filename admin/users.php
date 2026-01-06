<?php include 'includes/header.php'; ?>

<?php
$message = '';

// Block/Unblock handle
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'block') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">User blocked successfully!</div>';
    } elseif ($action === 'unblock') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">User unblocked successfully!</div>';
    }
}

// Search
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = "WHERE (username LIKE ? OR mobile LIKE ?) AND role = 'user'";
    $params = ["%$search%", "%$search%"];
}

// Users list
$sql = "SELECT u.id, u.username, u.mobile, u.status, u.created_at, w.balance 
        FROM users u 
        LEFT JOIN wallets w ON u.id = w.user_id 
        $where 
        ORDER BY u.created_at DESC 
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Manage Users</h2>

    <?= $message ?>

    <!-- Search Form -->
    <form method="GET" class="mb-8">
        <div class="flex gap-4">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or mobile..." 
                   class="flex-1 bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" />
            <button type="submit" class="bg-p2 hover:bg-blue-600 px-8 py-4 rounded-xl font-bold">
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="users.php" class="bg-gray-600 hover:bg-gray-700 px-8 py-4 rounded-xl font-bold">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Users Table -->
    <div class="bg-white/10 backdrop-blur rounded-2xl border border-white/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">Username</th>
                        <th class="px-6 py-4 text-left">Mobile</th>
                        <th class="px-6 py-4 text-left">Balance</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Joined</th>
                        <th class="px-6 py-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="border-t border-white/10 hover:bg-white/5 transition">
                                <td class="px-6 py-4">#<?= $u['id'] ?></td>
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($u['username']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($u['mobile']) ?></td>
                                <td class="px-6 py-4 font-bold text-green-400">Rs. <?= number_format($u['balance'] ?? 0) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-4 py-2 rounded-full text-sm font-medium <?= $u['status'] === 'active' ? 'bg-green-600/30 text-green-300' : 'bg-red-600/30 text-red-300' ?>">
                                        <?= ucfirst($u['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm opacity-70">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <a href="?action=block&id=<?= $u['id'] ?>&search=<?= urlencode($search) ?>" 
                                           class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-sm font-medium"
                                           onclick="return confirm('Block this user?')">
                                            Block
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=unblock&id=<?= $u['id'] ?>&search=<?= urlencode($search) ?>" 
                                           class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-sm font-medium"
                                           onclick="return confirm('Unblock this user?')">
                                            Unblock
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>