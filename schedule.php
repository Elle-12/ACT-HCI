<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = require_login();
$connection = database();
$dishes = menu_items();
$times = ['11:30', '12:00', '13:00', '14:00', '19:00', '20:00'];
$errors = [];
$editing = null;
$selectedDishIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();
        $reservationId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
        $reservationDate = (string) ($_POST['reservation_date'] ?? '');
        $reservationTime = (string) ($_POST['reservation_time'] ?? '');
        $partySize = (string) ($_POST['party_size'] ?? '');
        $specialRequests = trim((string) ($_POST['special_requests'] ?? ''));
        $postedOrders = $_POST['orders'] ?? [];
        $selectedDishIds = is_array($postedOrders)
            ? array_values(array_unique(array_filter($postedOrders, static fn ($id): bool => is_string($id) && isset($dishes[$id]))))
            : [];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reservationDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDate = $date !== false
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $date->format('Y-m-d') === $reservationDate
            && $reservationDate >= date('Y-m-d');

        if (!$reservationId) {
            $errors[] = 'The reservation could not be identified.';
        }
        if (!$validDate) {
            $errors[] = 'Choose today or a future date.';
        }
        if (!in_array($reservationTime, $times, true)) {
            $errors[] = 'Choose one of the available reservation times.';
        }
        if (!in_array($partySize, ['1', '2', '3', '4', '5', '6'], true)) {
            $errors[] = 'Choose a party size between 1 and 6.';
        }
        if (mb_strlen($specialRequests) > 1000) {
            $errors[] = 'Special requests must be 1,000 characters or fewer.';
        }

        if ($errors === []) {
            $connection->beginTransaction();
            try {
                $check = $connection->prepare(
                    'SELECT id FROM reservations
                     WHERE id = :id AND user_id = :user_id
                       AND reservation_date >= CURDATE()
                       AND status IN ("pending", "confirmed")
                     FOR UPDATE'
                );
                $check->execute(['id' => $reservationId, 'user_id' => $user['id']]);
                if ($check->fetch() === false) {
                    $errors[] = 'This reservation is no longer available to edit.';
                    $connection->rollBack();
                } else {
                    $update = $connection->prepare(
                        'UPDATE reservations
                         SET reservation_date = :reservation_date,
                             reservation_time = :reservation_time,
                             party_size = :party_size,
                             special_requests = :special_requests
                         WHERE id = :id AND user_id = :user_id'
                    );
                    $update->execute([
                        'reservation_date' => $reservationDate,
                        'reservation_time' => $reservationTime,
                        'party_size' => (int) $partySize,
                        'special_requests' => $specialRequests,
                        'id' => $reservationId,
                        'user_id' => $user['id'],
                    ]);
                    $removeItems = $connection->prepare('DELETE FROM reservation_items WHERE reservation_id = :id');
                    $removeItems->execute(['id' => $reservationId]);
                    if ($selectedDishIds !== []) {
                        $addItem = $connection->prepare(
                            'INSERT INTO reservation_items (reservation_id, menu_item_id, quantity, price)
                             VALUES (:reservation_id, :menu_item_id, 1, :price)'
                        );
                        foreach ($selectedDishIds as $dishId) {
                            $addItem->execute([
                                'reservation_id' => $reservationId,
                                'menu_item_id' => (int) $dishId,
                                'price' => $dishes[$dishId]['price'],
                            ]);
                        }
                    }
                    $connection->commit();
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            }

            if ($errors === []) {
                header('Location: schedule.php?updated=1');
                exit;
            }
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($editId) {
        $editQuery = $connection->prepare(
            'SELECT id, reservation_date, reservation_time, party_size, special_requests
             FROM reservations
             WHERE id = :id AND user_id = :user_id AND status IN ("pending", "confirmed")
               AND reservation_date >= CURDATE()'
        );
        $editQuery->execute(['id' => $editId, 'user_id' => $user['id']]);
        $editing = $editQuery->fetch() ?: null;
        if ($editing !== null) {
            $itemQuery = $connection->prepare('SELECT menu_item_id FROM reservation_items WHERE reservation_id = :id');
            $itemQuery->execute(['id' => $editId]);
            $selectedDishIds = array_map('strval', $itemQuery->fetchAll(PDO::FETCH_COLUMN));
        }
    }
    if ($editing === null) {
        $errors[] = 'This reservation is no longer available to edit.';
    }
}

$statement = $connection->prepare(
    'SELECT id, reservation_date, reservation_time, party_size, special_requests, status
     FROM reservations
     WHERE user_id = :user_id
     ORDER BY reservation_date DESC, reservation_time DESC'
);
$statement->execute(['user_id' => $user['id']]);
$reservations = $statement->fetchAll();

$pageTitle = 'Your schedule';
$activePage = 'schedule';
$styleSheet = 'styles/app.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <div class="page-heading">
        <h1>Your reservation schedule</h1>
        <p>Reservations made with your account appear here.</p>
    </div>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success" role="status">Your reservation has been updated.</div>
    <?php endif; ?>
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success" role="status">Your reservation has been saved.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error" role="alert"><?= h($error) ?></div>
    <?php endforeach; ?>
    <?php if ($editing !== null || ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== [])): ?>
        <?php $formReservation = $editing ?? [
            'id' => $_POST['reservation_id'] ?? '',
            'reservation_date' => $_POST['reservation_date'] ?? '',
            'reservation_time' => $_POST['reservation_time'] ?? '',
            'party_size' => $_POST['party_size'] ?? '',
            'special_requests' => $_POST['special_requests'] ?? '',
        ]; ?>
        <form method="post" action="schedule.php" class="form-card schedule-edit-form">
            <h2>Edit reservation</h2>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="reservation_id" value="<?= h($formReservation['id']) ?>">
            <div class="form-field">
                <label for="reservation-date">Date</label>
                <input id="reservation-date" type="date" name="reservation_date" min="<?= h(date('Y-m-d')) ?>" required value="<?= h($formReservation['reservation_date']) ?>">
            </div>
            <div class="form-field">
                <label for="reservation-time">Time</label>
                <select id="reservation-time" name="reservation_time" required>
                    <?php foreach ($times as $time): ?>
                        <option value="<?= h($time) ?>"<?= substr((string) $formReservation['reservation_time'], 0, 5) === $time ? ' selected' : '' ?>><?= h(date('g:i A', strtotime($time))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="party-size">Party size</label>
                <select id="party-size" name="party_size" required>
                    <?php for ($size = 1; $size <= 6; $size++): ?>
                        <option value="<?= $size ?>"<?= (string) $formReservation['party_size'] === (string) $size ? ' selected' : '' ?>><?= $size ?> <?= $size === 1 ? 'person' : 'people' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="special-requests">Special requests</label>
                <textarea id="special-requests" name="special_requests" maxlength="1000" rows="3"><?= h($formReservation['special_requests']) ?></textarea>
            </div>
            <fieldset class="form-field">
                <legend>Dishes for your table</legend>
                <div class="order-options">
                    <?php foreach ($dishes as $id => $dish): ?>
                        <label class="order-option">
                            <input type="checkbox" name="orders[]" value="<?= h($id) ?>"<?= in_array($id, $selectedDishIds, true) ? ' checked' : '' ?>>
                            <span>
                                <strong><?= h($dish['name']) ?></strong>
                                <span><?= h($dish['description']) ?></span>
                                <b><?= h(format_money($dish['price'])) ?></b>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="action-buttons">
                <button class="button" type="submit">Save changes</button>
                <a class="button button-muted" href="schedule.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($reservations === []): ?>
        <div class="empty-state">
            <h2>No reservations yet</h2>
            <p>Book a table and it will appear here.</p>
            <a class="button" href="book.php">Book a table</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Time</th><th>Party</th><th>Special requests</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?= h(date('M j, Y', strtotime($reservation['reservation_date']))) ?></td>
                            <td><?= h(date('g:i A', strtotime($reservation['reservation_time']))) ?></td>
                            <td><?= (int) $reservation['party_size'] ?> <?= (int) $reservation['party_size'] === 1 ? 'person' : 'people' ?></td>
                            <td><?= $reservation['special_requests'] !== '' ? nl2br(h($reservation['special_requests'])) : '—' ?></td>
                            <td><span class="status"><?= h(ucfirst($reservation['status'])) ?></span></td>
                            <td class="row-actions">
                                <?php if (in_array($reservation['status'], ['pending', 'confirmed'], true) && $reservation['reservation_date'] >= date('Y-m-d')): ?>
                                    <a class="button button-small" href="schedule.php?edit=<?= (int) $reservation['id'] ?>">Edit</a>
                                    <form method="post" action="reservation-delete.php" onsubmit="return confirm('Delete this reservation and its selected dishes?')">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                                        <input type="hidden" name="return_to" value="schedule.php">
                                        <button class="button button-danger button-small" type="submit">Delete</button>
                                    </form>
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
