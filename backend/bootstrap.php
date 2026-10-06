<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Backend is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = require $configFile;

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset']
    );
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_response(['error' => 'Method not allowed'], 405);
    }
}

function random_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

function clean_filename(string $name): string {
    $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?? 'file';
    $name = trim($name, " ._-\t\n\r\0\x0B");
    return mb_substr($name ?: 'file', 0, 180);
}

function public_id(PDO $pdo): string {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(public_id, '-', -1) AS UNSIGNED)) AS n FROM submissions WHERE public_id LIKE ?");
    $stmt->execute(["CHGPU-{$year}-%"]);
    $next = ((int)($stmt->fetch()['n'] ?? 0)) + 1;
    return sprintf('CHGPU-%s-%04d', $year, $next);
}

function start_editor_session(): void {
    global $config;
    if (session_status() === PHP_SESSION_NONE) {
        session_name($config['app']['session_name']);
        session_set_cookie_params([
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function require_editor(): array {
    start_editor_session();
    if (empty($_SESSION['user_id'])) json_response(['error' => 'Unauthorized'], 401);
    $stmt = db()->prepare('SELECT id,email,full_name,role,is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) json_response(['error' => 'Unauthorized'], 401);
    return $user;
}
