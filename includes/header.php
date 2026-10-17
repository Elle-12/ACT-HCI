<?php
$pageTitle = $pageTitle ?? 'Resto Bistro';
$activePage = $activePage ?? '';
$styleSheet = $styleSheet ?? 'styles/app.css';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Resto Bistro</title>
    <link rel="stylesheet" href="styles/app.css">
    <link rel="stylesheet" href="<?= h($styleSheet) ?>">
</head>
<body>
<header class="site-header">
    <nav class="site-nav" aria-label="Main navigation">
        <a class="logo" href="index.php">RESTO</a>
        <ul class="nav-links">
            <li><a href="index.php"<?= $activePage === 'home' ? ' class="active"' : '' ?>>Home</a></li>
            <li><a href="book.php"<?= $activePage === 'book' ? ' class="active"' : '' ?>>Book</a></li>
            <li><a href="schedule.php"<?= $activePage === 'schedule' ? ' class="active"' : '' ?>>Schedule</a></li>
            <?php if ($user !== null): ?>
                <li><a href="profile.php"<?= $activePage === 'profile' ? ' class="active"' : '' ?>>Profile</a></li>
                <li class="nav-welcome">Hi, <?= h($user['name']) ?></li>
                <li>
                    <form action="logout.php" method="post" class="logout-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="nav-button" type="submit">Sign out</button>
                    </form>
                </li>
            <?php else: ?>
                <li><a href="login.php"<?= $activePage === 'login' ? ' class="active"' : '' ?>>Sign in</a></li>
                <li><a class="nav-register" href="register.php"<?= $activePage === 'register' ? ' aria-current="page"' : '' ?>>Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<!--  -->