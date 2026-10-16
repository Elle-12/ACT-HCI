<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = require_login();
$dishes = menu_items();
$times = ['11:30', '12:00', '13:00', '14:00', '19:00', '20:00'];
$errors = [];

$selectedDish = (string) ($_GET['dish'] ?? '');
$selectedDishIds = isset($dishes[$selectedDish]) ? [$selectedDish] : [];
$reservationDate = '';
$partySize = '';
$specialRequests = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationDate = (string) ($_POST['reservation_date'] ?? '');
    $reservationTime = (string) ($_POST['reservation_time'] ?? '');
    $partySize = (string) ($_POST['party_size'] ?? '');
    $specialRequests = trim((string) ($_POST['special_requests'] ?? ''));
    $postedOrders = $_POST['orders'] ?? [];
    $selectedDishIds = is_array($postedOrders)
        ? array_values(array_unique(array_filter($postedOrders, static fn ($id): bool => is_string($id) && isset($dishes[$id]))))
        : [];

    try {
        verify_csrf_token();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reservationDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDate = $date !== false
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $date->format('Y-m-d') === $reservationDate
            && $reservationDate >= date('Y-m-d');
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
            $connection = database();
            $connection->beginTransaction();
            $insertReservation = $connection->prepare(
                'INSERT INTO reservations (user_id, reservation_date, reservation_time, party_size, special_requests, status)
                 VALUES (:user_id, :reservation_date, :reservation_time, :party_size, :special_requests, "confirmed")'
            );
            $insertReservation->execute([
                'user_id' => $user['id'],
                'reservation_date' => $reservationDate,
                'reservation_time' => $reservationTime,
                'party_size' => (int) $partySize,
                'special_requests' => $specialRequests,
            ]);
            $reservationId = (int) $connection->lastInsertId();

            if ($selectedDishIds !== []) {
                $insertItem = $connection->prepare(
                    'INSERT INTO reservation_items (reservation_id, menu_item_id, quantity, price) VALUES (:reservation_id, :menu_item_id, 1, :price)'
                );
                foreach ($selectedDishIds as $dishId) {
                    $dish = $dishes[$dishId];
                    $insertItem->execute([
                        'reservation_id' => $reservationId,
                        'menu_item_id' => (int) $dishId,
                        'price' => $dish['price'],
                    ]);
                }
            }
            $connection->commit();
            header('Location: schedule.php?created=1');
            exit;
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

$pageTitle = 'Book a table';
$activePage = 'book';
$styleSheet = 'styles/book.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <div class="page-heading">
        <h1>Book a table</h1>
        <p>Choose your preferred date, time, and party size. Signed in as <?= h($user['name']) ?>.</p>
    </div>
    <form method="post" action="book.php" class="booking-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <div class="form-field">
            <label for="reservation-date">Reservation date</label>
            <input id="reservation-date" type="date" name="reservation_date" min="<?= h(date('Y-m-d')) ?>" required value="<?= h($reservationDate) ?>">
        </div>
        <fieldset class="form-field">
            <legend>Available time</legend>
            <div class="time-slots">
                <?php foreach ($times as $time): ?>
                    <label class="time-slot">
                        <input type="radio" name="reservation_time" value="<?= h($time) ?>" required<?= ($_POST['reservation_time'] ?? '') === $time ? ' checked' : '' ?>>
                        <?= h(date('g:i A', strtotime($time))) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="form-field">
            <label for="party-size">Party size</label>
            <select id="party-size" name="party_size" required>
                <option value="">Select party size</option>
                <?php for ($size = 1; $size <= 6; $size++): ?>
                    <option value="<?= $size ?>"<?= $partySize === (string) $size ? ' selected' : '' ?>><?= $size ?> <?= $size === 1 ? 'person' : 'people' ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <fieldset class="form-field">
            <legend>Optional dishes for your table</legend>
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
        <div class="form-field">
            <label for="special-requests">Special requests (optional)</label>
            <textarea id="special-requests" name="special_requests" maxlength="1000" rows="4" placeholder="Let us know about any dietary needs or requests..."><?= h($specialRequests) ?></textarea>
        </div>
        <p class="booking-note">No payment is collected online. Selected dishes are saved with the reservation for reference.</p>
        <button class="button" type="submit">Confirm reservation</button>
    </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
