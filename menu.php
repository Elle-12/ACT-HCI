<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$dishes = menu_items();
$search = trim((string) ($_GET['search'] ?? ''));
$filteredDishes = array_filter($dishes, static function (array $dish) use ($search): bool {
    return $search === '' || stripos($dish['name'] . ' ' . $dish['description'], $search) !== false;
});

$pageTitle = 'Menu';
$activePage = 'menu';
$styleSheet = 'styles/menu.css';
require __DIR__ . '/includes/header.php';
?>
<main class="page-wrap">
    <div class="page-heading">
        <h1>Our culinary menu</h1>
        <p>Thoughtful bistro favorites, made fresh for your table.</p>
    </div>
    <form class="search-bar" method="get" action="menu.php" role="search">
        <label class="sr-only" for="menu-search">Search menu</label>
        <input id="menu-search" class="search-input" type="search" name="search" placeholder="Search dishes..." value="<?= h($search) ?>">
        <button class="button" type="submit">Search</button>
    </form>
    <?php if ($filteredDishes === []): ?>
        <div class="empty-state">
            <h2>No dishes found</h2>
            <p>Try another search term.</p>
        </div>
    <?php else: ?>
        <div class="menu-items">
            <?php foreach ($filteredDishes as $id => $dish): ?>
                <article class="menu-card">
                    <img class="menu-image" src="<?= h($dish['image']) ?>" alt="<?= h($dish['name']) ?>">
                    <div class="menu-card-content">
                        <h2><?= h($dish['name']) ?></h2>
                        <p><?= h($dish['description']) ?></p>
                        <div class="menu-card-bottom">
                            <strong class="price"><?= h(format_money($dish['price'])) ?></strong>
                            <a class="button" href="book.php?dish=<?= h($id) ?>">Add to reservation</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
