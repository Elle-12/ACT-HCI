<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: schedule.php');
    exit;
}

try {
    verify_csrf_token();
} catch (InvalidArgumentException $exception) {
    header('Location: schedule.php?error=csrf');
    exit;
}
$reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
$returnTo = (string) ($_POST['return_to'] ?? 'schedule.php');
if (!in_array($returnTo, ['schedule.php', 'transaction.php'], true)) {
    $returnTo = 'schedule.php';
}
if (!$reservationId) {
    header('Location: ' . $returnTo . '?error=invalid');
    exit;
}

$delete = database()->prepare(
    'DELETE FROM reservations
     WHERE id = :id AND user_id = :user_id
       AND reservation_date >= CURDATE()
       AND status IN ("pending", "confirmed")
       AND NOT EXISTS (
           SELECT 1 FROM transactions
           WHERE transactions.reservation_id = reservations.id
       )'
);
$delete->execute(['id' => $reservationId, 'user_id' => $user['id']]);
header('Location: ' . $returnTo . ($delete->rowCount() > 0 ? '?deleted=1' : '?error=not_found'));
exit;
