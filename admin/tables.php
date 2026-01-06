<?php include 'includes/header.php'; ?>

<?php
$message = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $time_limit = $_POST['time_limit'] ?? '';
    $entry_points = (int)($_POST['entry_points'] ?? 0);

    if (!in_array($type, ['2-player', '4-player']) || !in_array($time_limit, ['3-min', '5-min', '10-min']) || $entry_points <= 0) {
        $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Invalid data!</div>';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO tables (type, time_limit, entry_points, status, created_by) VALUES (?, ?, ?, 'open', ?)");
            $stmt->execute([$type, $time_limit, $entry_points, $_SESSION['user_id']]);
            $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">Table created successfully! ID: ' . $pdo->lastInsertId() . '</div>';
        } catch (Exception $e) {
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// List existing tables
$stmt = $pdo->query("SELECT * FROM tables ORDER BY created_at DESC LIMIT 50");
$tables = $stmt->fetchAll();
?>

<div class="max-w-4xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Create New Table</h2>

    <?= $message ?>

    <div class="bg-white/10 backdrop-blur rounded-2xl p-8 mb-10 border border-white/20">
        <form method="POST">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div>
                    <label class="block text-lg font-medium mb-2">Table Type</label>
                    <select name="type" class="w-full bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required>
                        <option value="2-player">2 Players</option>
                        <option value="4-player">4 Players</option>
                    </select>
                </div>

                <div>
                    <label class="block text-lg font-medium mb-2">Time Limit</label>
                    <select name="time_limit" class="w-full bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" required>
                        <option value="3-min">3 Minutes</option>
                        <option value="5-min">5 Minutes</option>
                        <option value="10-min">10 Minutes</option>
                    </select>
                </div>

                <div>
                    <label class="block text-lg font-medium mb-2">Entry Points</label>
                    <input type="number" name="entry_points" min="10" step="10" class="w-full bg-white/10 border border-white/20 rounded-xl px-6 py-4 focus:outline-none focus:border-p2" placeholder="50" required />
                </div>
            </div>

            <button type="submit" class="bg-p2 hover:bg-blue-600 px-10 py-5 rounded-2xl font-bold text-xl shadow-xl transition">
                Create Table
            </button>
        </form>
    </div>

    <h2 class="text-3xl font-bold mb-8">Recent Tables (Last 50)</h2>

    <div class="bg-white/10 backdrop-blur rounded-2xl border border-white/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">Type</th>
                        <th class="px-6 py-4 text-left">Time</th>
                        <th class="px-6 py-4 text-left">Entry</th>
                        <th class="px-6 py-4 text-left">Prize Pool</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $t): ?>
                        <?php
                        $prize = $t['entry_points'] * ($t['type'] === '2-player' ? 2 : 4);
                        ?>
                        <tr class="border-t border-white/10">
                            <td class="px-6 py-4">#<?= $t['id'] ?></td>
                            <td class="px-6 py-4"><?= $t['type'] ?></td>
                            <td class="px-6 py-4"><?= $t['time_limit'] ?></td>
                            <td class="px-6 py-4 font-semibold">Rs. <?= $t['entry_points'] ?></td>
                            <td class="px-6 py-4 font-bold text-green-400">Rs. <?= $prize ?></td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-sm <?= $t['status'] === 'open' ? 'bg-green-600/30 text-green-300' : 'bg-red-600/30 text-red-300' ?>">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm opacity-70"><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tables)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No tables yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>