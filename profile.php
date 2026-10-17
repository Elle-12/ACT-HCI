<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = require_login();
$statement = database()->prepare(
    'SELECT fullname AS name, email, phone, address FROM users WHERE id = :id LIMIT 1'
);
$statement->execute(['id' => $user['id']]);
$profile = $statement->fetch();

if ($profile === false) {
    unset($_SESSION['user']);
    header('Location: login.php');
    exit;
}

$errors = [];
$name = $profile['name'];
$email = $profile['email'];
$phone = $profile['phone'] ?? '';
$address = $profile['address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    try {
        verify_csrf_token();
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Enter your name (up to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($phone) > 20) {
            $errors[] = 'Your phone number must be 20 characters or fewer.';
        }
        if (strlen($address) > 65535) {
            $errors[] = 'Your address is too long.';
        }

        if ($errors === []) {
            try {
                $update = database()->prepare(
                    'UPDATE users SET fullname = :name, email = :email, phone = :phone, address = :address WHERE id = :id'
                );
                $update->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'address' => $address !== '' ? $address : null,
                    'id' => $user['id'],
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'An account with that email already exists.';
                } else {
                    throw $exception;
                }
            }

            if ($errors === []) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                header('Location: profile.php?updated=1');
                exit;
            }
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
}

$pageTitle = 'Your profile';
$activePage = 'profile';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <section class="form-card">
        <h1>Your profile</h1>
        <p>Manage the contact details on your account.</p>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success" role="status">Your profile has been updated.</div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <form method="post" action="profile.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="form-field">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" maxlength="100" autocomplete="name" required value="<?= h($name) ?>">
            </div>
            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="100" autocomplete="email" required value="<?= h($email) ?>">
            </div>
            <div class="form-field">
                <label for="phone">Phone number</label>
                <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= h($phone) ?>">
            </div>
            <div class="form-field">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="4" autocomplete="street-address"><?= h($address) ?></textarea>
            </div>
            <button class="button" type="submit">Save profile</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>