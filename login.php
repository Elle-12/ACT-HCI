<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$allowedNextPages = ['index.php', 'book.php', 'schedule.php', 'profile.php'];
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? 'index.php');
if (!in_array($next, $allowedNextPages, true)) {
    $next = 'index.php';
}

if (current_user() !== null) {
    header('Location: ' . $next);
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    try {
        verify_csrf_token();
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address and password.';
        } else {
            $statement = database()->prepare('SELECT id, fullname AS name, email, password AS password_hash FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if ($user === false || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'The email address or password is incorrect.';
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $update = database()->prepare('UPDATE users SET password = :password_hash WHERE id = :id');
                    $update->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
                }
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']];
                header('Location: ' . $next);
                exit;
            }
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
}

$pageTitle = 'Sign in';
$activePage = 'login';
$styleSheet = 'styles/app.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <section class="form-card">
        <h1>Welcome back</h1>
        <p>Sign in to manage your reservations.</p>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success" role="status">Your account is ready. Please sign in.</div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <form method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= h($email) ?>">
            </div>
            <div class="form-field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="button" type="submit">Sign in</button>
        </form>
        <p class="form-note">New to Resto? <a href="register.php">Create an account</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
