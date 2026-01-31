<?php include 'includes/header.php'; ?>

<?php
$message = '';
$joinedTableId = $_SESSION['admin_joined_table_id'] ?? null;
$joinedVirtualTableId = $_SESSION['admin_joined_virtual_table_id'] ?? null;
$spectatorVirtualTableId = $joinedVirtualTableId;

if ($joinedTableId && !$spectatorVirtualTableId) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM virtual_tables 
        WHERE table_id = ? AND status IN ('WAITING', 'RUNNING') 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$joinedTableId]);
    $virtualTable = $stmt->fetch();
    if ($virtualTable) {
        $spectatorVirtualTableId = $virtualTable['id'];
        $_SESSION['admin_joined_virtual_table_id'] = $spectatorVirtualTableId;
    }
}

// Admin selects a table to control (no player seat)
if (isset($_POST['admin_select'])) {
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
            $stmt = $pdo->prepare("
                SELECT id 
                FROM virtual_tables 
                WHERE table_id = ? AND status IN ('WAITING', 'RUNNING') 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$table_id]);
            $virtualTable = $stmt->fetch();

            if (!$virtualTable) {
                $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">No active virtual table found for this table!</div>';
            } else {
                $_SESSION['admin_joined_table_id'] = $table_id;
                $_SESSION['admin_joined_virtual_table_id'] = $virtualTable['id'];

                // Ensure admin is not seated as a player in this virtual table
                $stmt = $pdo->prepare("
                    DELETE FROM virtual_table_players
                    WHERE virtual_table_id = ? AND user_id = ?
                ");
                $stmt->execute([$virtualTable['id'], $_SESSION['user_id']]);

                $message = '<div class="bg-yellow-600 p-4 rounded-xl mb-6">Controller linked to Table #' . $table_id . ' (spectator only).</div>';
            }
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
        if (empty($_SESSION['admin_joined_table_id']) || (int)$_SESSION['admin_joined_table_id'] !== $table_id) {
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Select this table first to enable dice control.</div>';
        } else {
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND status IN ('open', 'ongoing')");
        $stmt->execute([$table_id]);
        if ($stmt->rowCount() === 0) {
            $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Table not active!</div>';
        } else {
            $stmt = $pdo->prepare("
                SELECT id 
                FROM virtual_tables 
                WHERE table_id = ? AND status IN ('WAITING', 'RUNNING') 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$table_id]);
            $virtualTable = $stmt->fetch();

            if (!$virtualTable) {
                $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">No active virtual table found for this table!</div>';
            } elseif (!empty($_SESSION['admin_joined_virtual_table_id']) && $_SESSION['admin_joined_virtual_table_id'] !== $virtualTable['id']) {
                $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Dice control only allowed for the joined table instance.</div>';
            } else {
                $virtual_table_id = $virtualTable['id'];
                $stmt = $pdo->prepare("INSERT INTO dice_override (virtual_table_id, table_id, dice_value, set_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$virtual_table_id, $table_id, $dice_value, $_SESSION['user_id']]);
                $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">Dice successfully set to <strong>' . $dice_value . '</strong> for Table #' . $table_id . '!</div>';
            }
        }
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
    ORDER BY do.created_at DESC
    LIMIT 15
");
$overrides = $stmt->fetchAll();
?>

<style>
    .dice-panel {
        color: #e2e8f0;
    }

    .dice-header {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 24px;
    }

    .dice-subtitle {
        color: rgba(226, 232, 240, 0.7);
        font-size: 14px;
    }

    .panel-card {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 18px 40px rgba(8, 12, 32, 0.35);
        position: relative;
        overflow: hidden;
    }

    .panel-card::after {
        content: "";
        position: absolute;
        inset: auto -20% -50% auto;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0) 70%);
        opacity: 0.45;
        pointer-events: none;
    }

    .panel-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .panel-copy {
        color: rgba(226, 232, 240, 0.65);
        margin-bottom: 18px;
    }

    .panel-card select,
    .panel-card input[type="text"],
    .panel-card input[type="number"] {
        color: #0f172a;
    }

    .control-button {
        border-radius: 14px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .dice-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px;
    }

    .dice-cell {
        display: grid;
        place-items: center;
        height: 64px;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        background: rgba(15, 23, 42, 0.55);
        font-size: 22px;
        font-weight: 700;
        transition: transform 120ms ease, background 120ms ease;
    }

    .dice-cell:hover {
        transform: translateY(-2px);
    }

    .dice-grid .peer:checked + .dice-cell {
        background: rgba(79, 70, 229, 0.7);
        border-color: rgba(129, 140, 248, 0.7);
        color: #fff;
    }

    .history-card {
        border-radius: 24px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(15, 23, 42, 0.7);
        box-shadow: 0 18px 40px rgba(8, 12, 32, 0.35);
        overflow: hidden;
    }

    .history-title {
        padding: 18px 22px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        font-weight: 700;
        font-size: 18px;
    }

    .history-table {
        display: grid;
        gap: 12px;
        padding: 18px 22px 22px;
    }

    .history-row,
    .history-head {
        display: grid;
        grid-template-columns: 1fr 1.2fr 1fr 1.2fr;
        gap: 12px;
        align-items: center;
    }

    .history-head {
        color: rgba(226, 232, 240, 0.65);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }

    .history-row {
        background: rgba(15, 23, 42, 0.45);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 14px;
        padding: 12px 14px;
    }

    .history-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 34px;
        border-radius: 10px;
        background: rgba(79, 70, 229, 0.25);
        color: #c7d2fe;
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .history-row,
        .history-head {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="max-w-6xl mx-auto dice-panel">
    <div class="dice-header">
        <h2 class="text-3xl font-bold">Dice Control</h2>
        <p class="dice-subtitle">Monitor tables and control the next dice roll with clear history.</p>
    </div>

    <?= $message ?>

    <?php if ($joinedTableId): ?>
        <div class="mb-6 bg-white/10 border border-white/15 rounded-2xl p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-sm uppercase tracking-wider text-slate-300">Spectator Mode</div>
                <div class="text-lg font-semibold">Joined Table #<?= htmlspecialchars((string)$joinedTableId) ?></div>
            </div>
            <?php if ($spectatorVirtualTableId): ?>
                <a class="control-button bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl text-sm font-semibold"
                   href="../game.php?table_id=<?= urlencode((string)$joinedTableId) ?>&virtual_table_id=<?= urlencode((string)$spectatorVirtualTableId) ?>&spectator=1">
                    Open Spectator View
                </a>
            <?php else: ?>
                <div class="text-sm text-slate-300">No active match found for this table.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-8">
        <div class="panel-card">
            <div class="panel-title text-yellow-300">Select Table to Control</div>
            <p class="panel-copy">Link a live table for dice control without joining as a player.</p>

            <form method="POST">
                <input type="hidden" name="admin_select" value="1" />
                <div class="grid md:grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-yellow-200 mb-2">Select Table</label>
                        <select name="table_id_join" class="w-full bg-white/90 border border-yellow-500/40 rounded-xl px-4 py-3 focus:outline-none focus:border-yellow-400" required>
                            <option value="">-- Choose Table --</option>
                            <?php foreach ($tables as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    #<?= $t['id'] ?> - <?= $t['type'] ?> - <?= $t['time_limit'] ?> - Entry: Rs. <?= $t['entry_points'] ?> - Status: <?= ucfirst($t['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="control-button bg-yellow-500 hover:bg-yellow-400 text-slate-900 py-3 px-6">
                        Link Table
                    </button>
                </div>
            </form>
        </div>

        <div class="panel-card">
            <div class="panel-title text-indigo-300">Force Dice Value</div>
            <p class="panel-copy">Override the next dice roll for a live table.</p>

            <form method="POST">
                <input type="hidden" name="set_dice" value="1" />
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-indigo-200 mb-2">Select Table</label>
                    <select name="table_id" class="w-full bg-white/90 border border-indigo-500/40 rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-400" required>
                        <option value="">-- Choose Active Table --</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                #<?= $t['id'] ?> - <?= $t['type'] ?> - <?= $t['time_limit'] ?> - Entry: Rs. <?= $t['entry_points'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-indigo-200 mb-3">Choose Dice Value</label>
                    <div class="dice-grid">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="dice_value" value="<?= $i ?>" class="hidden peer" required />
                                <div class="dice-cell">
                                    <?= $i ?>
                                </div>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <button type="submit" class="control-button w-full bg-indigo-600 hover:bg-indigo-500 py-3">
                    Force Dice Value
                </button>
            </form>
        </div>
    </div>

    <div class="mt-10 history-card">
        <div class="history-title">Recent Dice Overrides</div>
        <?php if (empty($overrides)): ?>
            <p class="text-center text-slate-400 py-12 text-sm">No dice overrides recorded yet.</p>
        <?php else: ?>
            <div class="history-table">
                <div class="history-head">
                    <div>Dice</div>
                    <div>Table</div>
                    <div>Admin</div>
                    <div>Time</div>
                </div>
                <?php foreach ($overrides as $o): ?>
                    <div class="history-row">
                        <div><span class="history-pill"><?= $o['dice_value'] ?></span></div>
                        <div>#<?= $o['table_id'] ?> - <?= $o['type'] ?></div>
                        <div><?= htmlspecialchars($o['admin_name']) ?></div>
                        <div><?= date('d M Y, h:i A', strtotime($o['timestamp'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
