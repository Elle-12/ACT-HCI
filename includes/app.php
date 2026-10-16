<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_NAME') ?: 'resto_bistro';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    try {
        $connection = new PDO(
            "mysql:host={$host};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        error_log('Resto database connection failed: ' . $exception->getMessage());
        throw new RuntimeException(
            'The database is unavailable. Start MySQL in XAMPP and import database.sql into phpMyAdmin.',
            0,
            $exception
        );
    }

    return $connection;
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($known) || $known === '' || !hash_equals($known, $submitted)) {
        throw new InvalidArgumentException('Your session has expired. Refresh the page and try again.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.php?next=' . rawurlencode(basename($_SERVER['PHP_SELF'] ?? 'index.php')));
        exit;
    }

    return $user;
}

function menu_items(): array
{
    $statement = database()->query(
        'SELECT id, name, description, price, image_url
         FROM menu_items
         WHERE is_available = 1
         ORDER BY name'
    );
    $items = [];

    foreach ($statement->fetchAll() as $item) {
        $items[(string) $item['id']] = [
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'price' => (float) $item['price'],
            'image' => $item['image_url'] ?: 'img/2.png',
        ];
    }

    return $items;
}

function format_money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $message = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Something went wrong while loading this page. Please try again.';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Resto Bistro - Error</title>
        <link rel="stylesheet" href="styles/app.css">
    </head>
    <body class="message-page">
        <main class="message-card">
            <h1>We couldn't load this page</h1>
            <p><?= h($message) ?></p>
            <a class="button" href="index.php">Return home</a>
        </main>
    </body>
    </html>
    <?php
});
