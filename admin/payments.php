<?php include 'includes/header.php'; ?>

<?php
$message = '';

// Approve or Reject
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pay_id = (int)$_GET['id'];
    $action = $_GET['action']; // approve or reject

    $stmt = $pdo->prepare("SELECT user_id, amount, status FROM offline_payments WHERE id = ?");
    $stmt->execute([$pay_id]);
    $pay = $stmt->fetch();

    if ($pay && $pay['status'] === 'pending') {
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();

                // Credit wallet
                $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                $stmt->execute([$pay['amount'], $pay['user_id']]);

                // Log transaction
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, reason) VALUES (?, ?, 'credit', ?)");
                $stmt->execute([$pay['user_id'], $pay['amount'], 'Offline payment approved']);

                // Update status
                $stmt = $pdo->prepare("UPDATE offline_payments SET status = 'approved' WHERE id = ?");
                $stmt->execute([$pay_id]);

                $pdo->commit();
                $message = '<div class="bg-green-600 p-4 rounded-xl mb-6">Payment approved and credited!</div>';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '<div class="bg-red-600 p-4 rounded-xl mb-6">Error approving</div>';
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE offline_payments SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$pay_id]);
            $message = '<div class="bg-orange-600 p-4 rounded-xl mb-6">Payment rejected</div>';
        }
    }
}

// List pending + recent
$stmt = $pdo->query("
    SELECT op.*, u.username, u.mobile, w.balance 
    FROM offline_payments op 
    JOIN users u ON op.user_id = u.id 
    LEFT JOIN wallets w ON u.id = w.user_id 
    ORDER BY op.timestamp DESC
");
$payments = $stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto">
    <h2 class="text-3xl font-bold mb-8">Offline Payment Requests</h2>

    <?= $message ?>

    <div class="bg-white/10 backdrop-blur rounded-2xl border border-white/20 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">User</th>
                        <th class="px-6 py-4 text-left">Amount</th>
                        <th class="px-6 py-4 text-left">Proof</th>
                        <th class="px-6 py-4 text-left">Current Balance</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400">No payment requests</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr class="border-t border-white/10 hover:bg-white/5">
                                <td class="px-6 py-4">#<?= $p['id'] ?></td>
                                <td class="px-6 py-4">
                                    <p class="font-medium"><?= htmlspecialchars($p['username']) ?></p>
                                    <p class="text-sm opacity-70"><?= htmlspecialchars($p['mobile']) ?></p>
                                </td>
                                <td class="px-6 py-4 font-bold text-xl">Rs. <?= $p['amount'] ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($p['qr_image_path']): ?>
                                        <a href="../<?= htmlspecialchars($p['qr_image_path']) ?>" target="_blank" class="text-p2 hover:underline">
                                            View Proof Image
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-bold">Rs. <?= number_format($p['balance'] ?? 0) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-4 py-2 rounded-full text-sm <?= 
                                        $p['status'] === 'approved' ? 'bg-green-600/30 text-green-300' :
                                        ($p['status'] === 'rejected' ? 'bg-red-600/30 text-red-300' : 'bg-yellow-600/30 text-yellow-300')
                                    ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm opacity-70">
                                    <?= date('d M Y, h:i A', strtotime($p['timestamp'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <a href="?action=approve&id=<?= $p['id'] ?>" 
                                           class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg mr-2"
                                           onclick="return confirm('Approve and credit Rs. <?= $p['amount'] ?>?')">
                                            Approve
                                        </a>
                                        <a href="?action=reject&id=<?= $p['id'] ?>" 
                                           class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg"
                                           onclick="return confirm('Reject this payment?')">
                                            Reject
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">Processed</span>
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