<?php include 'includes/header.php'; ?>

<?php
$message = '';

// Admin Join as Player (Compulsory Entry - Free)
if (isset($_POST['admin_join'])) {
    $table_id = (int)($_POST['table_id_join'] ?? 0);

    if ($table_id <= 0) {
        $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Invalid table selected!</div>';
    } else {
        // Check if table exists and is open/ongoing
        $stmt = $pdo->prepare("SELECT id, status FROM tables WHERE id = ? AND status IN ('open', 'ongoing')");
        $stmt->execute([$table_id]);
        if ($stmt->rowCount() === 0) {
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Table not available!</div>';
        } else {
            // Log as admin action (no deduction)
            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, reason) VALUES (?, 0, 'credit', ?)");
            $stmt->execute([$_SESSION['user_id'], 'Admin joined table #' . $table_id . ' (compulsory monitoring)']);

            // Optional: Create a dummy game entry or flag for Node.js to recognize admin presence
            // Node.js can check if admin user_id is in players

            $message = '<div class="bg-yellow-600 p-4 rounded-xl mb-6">You have successfully joined Table #' . $table_id . ' as Admin Player for monitoring!</div>';
        }
    }
}

// Set Dice Override
if (isset($_POST['set_dice'])) {
    $table_id = (int)($_POST['table_id'] ?? 0);
    $dice_value = (int)($_POST['dice_value'] ?? 0);

    if ($table_id <= 0 || $dice_value < 1 || $dice_value > 6) {
        $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Invalid table or dice value!</div>';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND status IN ('open', 'ongoing')");
        $stmt->execute([$table_id]);
        if ($stmt->rowCount() === 0) {
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Table not active!</div>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO dice_override (table_id, dice_value, set_by) VALUES (?, ?, ?)");
            $stmt->execute([$table_id, $dice_value, $_SESSION['user_id']]);
            $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">Dice successfully set to <strong>' . $dice_value . '</strong> for Table #' . $table_id . '!</div>';
        }
    }
}

// Get active tables
$stmt = $pdo->query("
    SELECT 
        t.id, 
        t.type, 
        t.time_limit, 
        t.entry_points, 
        t.status
    FROM tables t
    WHERE t.status IN ('open', 'ongoing')
    ORDER BY t.created_at DESC
");
$tables = $stmt->fetchAll();

// Recent overrides
$stmt = $pdo->query("
    SELECT do.*, t.type, u.username AS admin_name
    FROM dice_override do
    JOIN tables t ON do.table_id = t.id
    JOIN users u ON do.set_by = u.id
    ORDER BY do.timestamp DESC
    LIMIT 15
");
$overrides = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Dice Control & Monitoring</h2>

    <?= $message ?>

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Admin Compulsory Join -->
        <div class="bg-gradient-to-br from-yellow-900/50 to-yellow-800/30 backdrop-blur-lg border border-yellow-500/40 rounded-3xl p-8 shadow-2xl">
            <h3 class="text-2xl font-bold text-yellow-300 mb-4">Compulsory Admin Entry</h3>
            <p class="text-yellow-200/80 mb-8">Join any active table as Admin Player to monitor the game closely (Free Entry - No deduction).</p>

            <form method="POST">
                <input type="hidden" name="admin_join" value="1" />
                <div class="grid md:grid-cols-2 gap-6 items-end">
                    <div>
                        <label class="block text-lg font-medium text-yellow-200 mb-3">Select Table to Join</label>
                        <select name="table_id_join" class="w-full bg-white/10 border border-yellow-500/50 rounded-xl px-6 py-4 focus:outline-none focus:border-yellow-400 text-black" required="">
                            <option value="">-- Choose Table --</option>
                            <?php foreach ($tables as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    #<?= $t['id'] ?> • <?= $t['type'] ?> • <?= $t['time_limit'] ?> • Entry: Rs. <?= $t['entry_points'] ?> • Status: <?= ucfirst($t['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-500 text-black font-bold py-4 px-8 rounded-xl text-xl shadow-xl transition">
                        Join as Admin Player
                    </button>
                </div>
            </form>
        </div>

        <!-- Dice Override -->
        <div class="bg-gradient-to-br from-purple-900/50 to-purple-800/30 backdrop-blur-lg border border-purple-500/40 rounded-3xl p-8 shadow-2xl">
            <h3 class="text-2xl font-bold text-purple-300 mb-4">Force Dice Value</h3>
            <p class="text-purple-200/80 mb-8">Override next dice roll for any active table.</p>

            <form method="POST">
                <input type="hidden" name="set_dice" value="1" />
                <div class="mb-8">
                    <label class="block text-lg font-medium text-purple-200 mb-3">Select Table</label>
                    <select name="table_id" class="w-full bg-white/10 border border-purple-500/50 rounded-xl px-6 py-4 focus:outline-none focus:border-purple-400 text-black" required>
                        <option value="">-- Choose Active Table --</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                #<?= $t['id'] ?> • <?= $t['type'] ?> • <?= $t['time_limit'] ?> • Entry: Rs. <?= $t['entry_points'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-8">
                    <label class="block text-lg font-medium text-purple-200 mb-4">Choose Dice Value</label>
                    <div class="grid grid-cols-6 gap-4">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="dice_value" value="<?= $i ?>" class="hidden peer" required />
                                <div class="bg-white/10 peer-checked:bg-purple-600 peer-checked:border-purple-400 border border-white/20 rounded-2xl p-8 text-center text-5xl font-bold hover:bg-white/20 transition shadow-lg">
                                    <?= $i ?>
                                </div>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 py-5 rounded-2xl font-bold text-xl shadow-xl transition">
                    Force Dice Value
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Overrides History -->
    <div class="mt-12 bg-white/10 backdrop-blur rounded-3xl p-8 border border-white/20 shadow-2xl">
        <h3 class="text-2xl font-bold mb-6">Recent Dice Overrides</h3>
        <?php if (empty($overrides)): ?>
            <p class="text-center text-gray-400 py-12 text-lg">No dice overrides recorded yet.</p>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($overrides as $o): ?>
                    <div class="bg-white/5 rounded-2xl p-6 border border-white/10 flex justify-between items-center">
                        <div>
                            <p class="text-3xl font-bold">Dice Forced: <span class="text-purple-400"><?= $o['dice_value'] ?></span></p>
                            <p class="text-lg mt-2">Table #<?= $o['table_id'] ?> • <?= $o['type'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">By: <?= htmlspecialchars($o['admin_name']) ?></p>
                            <p class="text-sm opacity-70"><?= date('d M Y, h:i A', strtotime($o['timestamp'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>