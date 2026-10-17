<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$pageTitle = 'Home';
$activePage = 'home';
$styleSheet = 'styles/home.css';
require __DIR__ . '/includes/header.php';
$dishes = menu_items();
?>
<main>
    <?php if (isset($_GET['account_deleted'])): ?>
        <div class="alert alert-success account-deleted-notice" role="status">Your account was deleted.</div>
    <?php endif; ?>
    <section class="hero">
        <div class="hero-content">
            <p class="eyebrow">A TABLE WORTH GATHERING AROUND</p>
            <h1>Welcome to Resto</h1>
            <p class="hero-copy">An elegant culinary escape delivering stellar bistro-inspired dining and seamless reservations for discerning food lovers.</p>
            <div class="hero-buttons">
                <a href="#favorites" class="button">See house favorites</a>
                <a href="book.php" class="button button-light">Book a table</a>
            </div>
        </div>
    </section>
    <section class="home-intro">
        <h2>Good food. Better company.</h2>
        <p>Join us for a relaxed bistro experience, from a quick lunch to a special evening out.</p>
        <div class="home-actions">
            <a href="book.php">Reserve a table</a>
            <a href="schedule.php">View your schedule</a>
        </div>
    </section>
    <section class="menu-highlights" id="favorites">
        <div class="section-heading">
            <p class="eyebrow">FROM OUR KITCHEN</p>
            <h2>House favorites</h2>
        </div>
        <div class="home-menu-grid">
            <?php foreach (array_slice($dishes, 0, 3, true) as $id => $dish): ?>
                <article class="home-menu-item">
                    <img src="<?= h($dish['image']) ?>" alt="<?= h($dish['name']) ?>">
                    <div class="home-menu-info">
                        <h3><?= h($dish['name']) ?></h3>
                        <p><?= h($dish['description']) ?></p>
                        <strong><?= h(format_money($dish['price'])) ?></strong>
                        <a href="book.php?dish=<?= h($id) ?>">Add to reservation</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
