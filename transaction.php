<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = require_login();

$statement = database()->prepare(
    'SELECT r.id, r.reservation_date, r.reservation_time, r.party_size, r.status,
            COALESCE(SUM(ri.price * ri.quantity), 0) AS total,
            GROUP_CONCAT(mi.name ORDER BY mi.name SEPARATOR ", ") AS items,
            EXISTS(SELECT 1 FROM transactions t WHERE t.reservation_id = r.id) AS has_transaction
     FROM reservations r
     LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
     LEFT JOIN menu_items mi ON mi.id = ri.menu_item_id
     WHERE r.user_id = :user_id
     GROUP BY r.id, r.reservation_date, r.reservation_time, r.party_size, r.status
     ORDER BY r.reservation_date DESC, r.reservation_time DESC'
);
$statement->execute(['user_id' => $user['id']]);
$reservations = $statement->fetchAll();

$pageTitle = 'Reservation transactions';
$activePage = 'transaction';
$styleSheet = 'styles/app.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <div class="page-heading">
        <h1>Reservation transactions</h1>
        <p>Review the reservations and optional dishes associated with your account.</p>
    </div>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success" role="status">The reservation was deleted.</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-error" role="alert">That reservation could not be deleted. It may already be completed or unavailable.</div>
    <?php endif; ?>
    <div class="alert alert-success">Online payment is not enabled. Any listed amount is an estimate for selected dishes, payable at the restaurant.</div>
    <?php if ($reservations === []): ?>
        <div class="empty-state">
            <h2>Nothing to show yet</h2>
            <p>Your saved reservations and dish estimates will appear here.</p>
            <a class="button" href="book.php">Make a reservation</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Reservation</th><th>Date &amp; time</th><th>Party</th><th>Selected dishes</th><th>Estimated total</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td>#<?= (int) $reservation['id'] ?></td>
                            <td><?= h(date('M j, Y', strtotime($reservation['reservation_date']))) ?><br><?= h(date('g:i A', strtotime($reservation['reservation_time']))) ?></td>
                            <td><?= (int) $reservation['party_size'] ?></td>
                            <td><?= h($reservation['items'] ?: 'No dishes selected') ?></td>
                            <td><?= h(format_money((float) $reservation['total'])) ?></td>
                            <td><span class="status"><?= h(ucfirst($reservation['status'])) ?></span></td>
                            <td>
                                <?php if ((int) $reservation['has_transaction'] === 0 && in_array($reservation['status'], ['pending', 'confirmed'], true) && $reservation['reservation_date'] >= date('Y-m-d')): ?>
                                    <form method="post" action="reservation-delete.php" onsubmit="return confirm('Delete this reservation and its selected dishes?')">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                        <input type="hidden" name="return_to" value="transaction.php">
                                        <button class="button button-danger button-small" type="submit">Delete</button>
                                    </form>
                                <?php elseif ((int) $reservation['has_transaction'] !== 0): ?>
                                    <span>Payment record exists</span>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
