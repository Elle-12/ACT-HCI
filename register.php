<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

if (current_user() !== null) {
    header('Location: index.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    try {
        verify_csrf_token();
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Enter your name (up to 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Your password must be at least 8 characters long.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'The passwords do not match.';
        }

        if ($errors === []) {
            try {
                $statement = database()->prepare(
                    'INSERT INTO users (fullname, email, password) VALUES (:name, :email, :password_hash)'
                );
                $statement->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $errors[] = 'An account with that email already exists. Please sign in instead.';
                } else {
                    throw $exception;
                }
            }

            if ($errors === []) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int) database()->lastInsertId(), 'name' => $name, 'email' => $email];
                header('Location: index.php');
                exit;
            }
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
}

$pageTitle = 'Create an account';
$activePage = 'register';
$styleSheet = 'styles/app.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <section class="form-card">
        <h1>Create your account</h1>
        <p>Register to make and keep track of your Resto reservations.</p>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error" role="alert"><?= h($error) ?></div>
        <?php endforeach; ?>
        <form method="post" action="register.php" novalidate>
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
                <label for="password">Password</label>
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                <small>Use at least 8 characters.</small>
            </div>
            <div class="form-field">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
            </div>
            <button class="button" type="submit">Create account</button>
        </form>
        <p class="form-note">Already registered? <a href="login.php">Sign in</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
