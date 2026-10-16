<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = require_login();
$errors = [];
$connection = database();

$profileQuery = $connection->prepare(
    'SELECT id, fullname, email, phone, address, password FROM users WHERE id = :id LIMIT 1'
);
$profileQuery->execute(['id' => $user['id']]);
$profile = $profileQuery->fetch();
if ($profile === false) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$name = $profile['fullname'];
$email = $profile['email'];
$phone = $profile['phone'] ?? '';
$address = $profile['address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token();
        $action = (string) ($_POST['action'] ?? 'update');
        if ($action === 'delete_account') {
            $password = (string) ($_POST['current_password'] ?? '');
            if (!password_verify($password, $profile['password'])) {
                $errors[] = 'Enter your current password to delete your account.';
            } else {
                $delete = $connection->prepare('DELETE FROM users WHERE id = :id');
                $delete->execute(['id' => $user['id']]);
                $_SESSION = [];
                session_destroy();
                header('Location: index.php?account_deleted=1');
                exit;
            }
        } elseif ($action === 'update') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));

            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'Enter your name (up to 100 characters).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                $errors[] = 'Enter a valid email address (up to 100 characters).';
            }
            if (mb_strlen($phone) > 20) {
                $errors[] = 'Phone number must be 20 characters or fewer.';
            }
            if (mb_strlen($address) > 2000) {
                $errors[] = 'Address must be 2,000 characters or fewer.';
            }

            if ($errors === []) {
                $update = $connection->prepare(
                    'UPDATE users SET fullname = :fullname, email = :email, phone = :phone, address = :address WHERE id = :id'
                );
                try {
                    $update->execute([
                        'fullname' => $name,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'address' => $address !== '' ? $address : null,
                        'id' => $user['id'],
                    ]);
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $errors[] = 'That email address is already used by another account.';
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
        } else {
            $errors[] = 'That profile action is not supported.';
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
    <div class="page-heading">
        <h1>Your profile</h1>
        <p>View and update the basic information for your Resto account.</p>
    </div>
    <section class="form-card profile-card">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success" role="status">Your profile has been updated.</div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <form method="post" action="profile.php">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
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
                <textarea id="address" name="address" maxlength="2000" rows="4" autocomplete="street-address"><?= h($address) ?></textarea>
            </div>
            <button class="button" type="submit">Save profile</button>
        </form>
    </section>
    <section class="form-card danger-zone">
        <h2>Delete account</h2>
        <p>Deleting your account removes your sign-in profile. Reservation/payment records may be retained without your profile where required for record keeping.</p>
        <form method="post" action="profile.php" onsubmit="return confirm('Delete your Resto account? This action cannot be undone.')">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete_account">
            <div class="form-field">
                <label for="current-password">Current password</label>
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <button class="button button-danger" type="submit">Delete my account</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
