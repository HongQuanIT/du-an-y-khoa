<?php

/**
 * MedLearn — Production environment checker.
 *
 * Truy cập: https://domain/check.php?token=YOUR_SECRET
 * Hoặc CLI: php public/check.php
 *
 * Bảo vệ: đặt CHECK_TOKEN trong môi trường / .env (hoặc truyền ?token=).
 * XÓA hoặc đổi tên file này sau khi kiểm tra xong trên production.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Security gate
// ---------------------------------------------------------------------------

$isCli = PHP_SAPI === 'cli';
$envToken = getenv('CHECK_TOKEN') ?: ($_ENV['CHECK_TOKEN'] ?? null);

if (! $isCli) {
    // Load .env nhẹ nếu có (không bootstrap Laravel).
    $envFile = dirname(__DIR__).'/.env';
    if (is_readable($envFile) && $envToken === null) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if ($k === 'CHECK_TOKEN') {
                $envToken = $v;
            }
            if (! array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
            }
        }
    }

    $provided = $_GET['token'] ?? $_SERVER['HTTP_X_CHECK_TOKEN'] ?? '';
    $expected = $envToken ?: 'medlearn-check-change-me';

    if (! hash_equals((string) $expected, (string) $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden. Pass ?token=... matching CHECK_TOKEN.\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** @var list<array{group:string,name:string,ok:bool,required:bool,detail:string}> */
$checks = [];

function check(string $group, string $name, bool $ok, string $detail = '', bool $required = true): void
{
    global $checks;
    $checks[] = compact('group', 'name', 'ok', 'required', 'detail');
}

function env_val(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);

    if ($v === false || $v === null || $v === '') {
        return $default;
    }

    return (string) $v;
}

function bytes_to_human(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($u) - 1) {
        $n /= 1024;
        $i++;
    }

    return round($n, 1).' '.$u[$i];
}

function parse_size(string $val): int
{
    $val = trim($val);
    if ($val === '') {
        return 0;
    }
    $unit = strtolower(substr($val, -1));
    $num = (float) $val;
    return (int) match ($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
// 1. PHP runtime
// ---------------------------------------------------------------------------

$phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
check('PHP', 'Phiên bản PHP ≥ 8.3', $phpOk, 'Hiện tại: '.PHP_VERSION.' (khuyến nghị 8.4)', true);
check('PHP', 'SAPI', true, PHP_SAPI.' · '.php_uname('s').' '.php_uname('m'), false);
check('PHP', 'Timezone', date_default_timezone_get() !== '', 'date.timezone = '.ini_get('date.timezone').' · runtime = '.date_default_timezone_get(), false);

$memory = ini_get('memory_limit');
$memoryOk = $memory === '-1' || parse_size($memory) >= 256 * 1024 * 1024;
check('PHP', 'memory_limit ≥ 256M', $memoryOk, "Hiện tại: {$memory} (khuyến nghị 512M)", true);

$upload = ini_get('upload_max_filesize');
$post = ini_get('post_max_size');
$uploadOk = parse_size($upload) >= 32 * 1024 * 1024;
check('PHP', 'upload_max_filesize ≥ 32M', $uploadOk, "upload_max_filesize={$upload}, post_max_size={$post} (khuyến nghị 64M / 68M)", false);

$exec = (int) ini_get('max_execution_time');
check('PHP', 'max_execution_time', $exec === 0 || $exec >= 60, "Hiện tại: {$exec}s", false);
check('PHP', 'expose_php = Off', strtolower((string) ini_get('expose_php')) === '0' || ini_get('expose_php') === '' || ini_get('expose_php') === 'Off', 'expose_php='.var_export(ini_get('expose_php'), true), false);

// ---------------------------------------------------------------------------
// 2. Extensions (khớp docker/php/Dockerfile + Laravel)
// ---------------------------------------------------------------------------

$requiredExt = [
    'pdo' => 'PDO core',
    'pdo_mysql' => 'MySQL driver',
    'mbstring' => 'Multibyte strings',
    'openssl' => 'TLS / encryption',
    'tokenizer' => 'Laravel blade/compiler',
    'xml' => 'XML parsing',
    'ctype' => 'Character type checks',
    'json' => 'JSON',
    'fileinfo' => 'MIME detection',
    'curl' => 'HTTP client',
    'bcmath' => 'Billing / precision math',
    'intl' => 'i18n / collation',
    'zip' => 'Archives',
    'gd' => 'Image processing (thumbnails)',
    'exif' => 'Image metadata',
    'redis' => 'phpredis (cache/queue/session)',
    'pcntl' => 'Horizon / Reverb / queue signals',
];

$optionalExt = [
    'opcache' => 'OPcache (bắt buộc khuyến nghị production)',
    'sodium' => 'Modern crypto',
    'imagick' => 'Advanced image processing',
];

foreach ($requiredExt as $ext => $label) {
    $loaded = extension_loaded($ext);
    check('Extension (bắt buộc)', "{$ext} — {$label}", $loaded, $loaded ? 'loaded' : 'MISSING', true);
}

foreach ($optionalExt as $ext => $label) {
    $loaded = extension_loaded($ext);
    check('Extension (tuỳ chọn)', "{$ext} — {$label}", $loaded, $loaded ? 'loaded' : 'not loaded', false);
}

if (extension_loaded('opcache')) {
    $opEnabled = (bool) ini_get('opcache.enable');
    $validate = (string) ini_get('opcache.validate_timestamps');
    check('OPcache', 'opcache.enable = 1', $opEnabled, 'opcache.enable='.var_export(ini_get('opcache.enable'), true), false);
    check(
        'OPcache',
        'opcache.validate_timestamps = 0 (prod)',
        $validate === '0',
        "Hiện tại: {$validate} — production nên = 0 và warm cache sau deploy",
        false
    );
    check('OPcache', 'memory_consumption', true, ini_get('opcache.memory_consumption').' MB', false);
}

// ---------------------------------------------------------------------------
// 3. Filesystem & permissions
// ---------------------------------------------------------------------------

$paths = [
    'storage' => $root.'/storage',
    'storage/app' => $root.'/storage/app',
    'storage/framework' => $root.'/storage/framework',
    'storage/framework/cache' => $root.'/storage/framework/cache',
    'storage/framework/sessions' => $root.'/storage/framework/sessions',
    'storage/framework/views' => $root.'/storage/framework/views',
    'storage/logs' => $root.'/storage/logs',
    'bootstrap/cache' => $root.'/bootstrap/cache',
    'public' => $root.'/public',
];

foreach ($paths as $label => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    check('Filesystem', "{$label} tồn tại + writable", $writable, $exists ? ($writable ? $path : "không ghi được: {$path}") : "không tồn tại: {$path}", true);
}

$publicStorage = $root.'/public/storage';
$linkOk = is_link($publicStorage) || is_dir($publicStorage);
check('Filesystem', 'public/storage (storage:link)', $linkOk, $linkOk ? 'OK' : 'Chạy: php artisan storage:link', false);

$vendor = is_dir($root.'/vendor') && is_file($root.'/vendor/autoload.php');
check('Filesystem', 'vendor/ (composer install)', $vendor, $vendor ? 'OK' : 'Chạy: composer install --no-dev --optimize-autoloader', true);

$buildManifest = is_file($root.'/public/build/manifest.json');
check('Filesystem', 'public/build/manifest.json (Vite)', $buildManifest, $buildManifest ? 'OK' : 'Chạy: npm ci && npm run build', true);

$envExists = is_file($root.'/.env');
check('Filesystem', '.env tồn tại', $envExists, $envExists ? 'OK' : 'Copy từ .env.example và cấu hình production', true);

// Document root hint
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
if ($docRoot !== '') {
    $expectedPublic = realpath($root.'/public') ?: ($root.'/public');
    $docReal = realpath($docRoot) ?: $docRoot;
    check(
        'Web server',
        'Document root = public/',
        $docReal === $expectedPublic,
        "DOCUMENT_ROOT={$docReal} · expected={$expectedPublic}",
        true
    );
}

// ---------------------------------------------------------------------------
// 4. Application env (nếu có .env)
// ---------------------------------------------------------------------------

if ($envExists) {
    // Ensure .env loaded for CLI too
    if ($isCli) {
        foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if (! array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
            }
        }
    }

    $appEnv = env_val('APP_ENV', 'unknown');
    $appDebug = strtolower((string) env_val('APP_DEBUG', 'false'));
    $appKey = env_val('APP_KEY', '');
    $appUrl = env_val('APP_URL', '');

    check('App config', 'APP_ENV = production', $appEnv === 'production', "APP_ENV={$appEnv}", false);
    check('App config', 'APP_DEBUG = false', in_array($appDebug, ['false', '0', ''], true), "APP_DEBUG={$appDebug}", $appEnv === 'production');
    check('App config', 'APP_KEY đã set', $appKey !== null && $appKey !== '' && $appKey !== 'base64:', "APP_KEY ".($appKey ? 'present ('.strlen($appKey).' chars)' : 'EMPTY'), true);
    check('App config', 'APP_URL đã set', $appUrl !== null && $appUrl !== '', "APP_URL={$appUrl}", true);

    $requiredEnv = [
        'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        'REDIS_HOST', 'CACHE_STORE', 'QUEUE_CONNECTION', 'SESSION_DRIVER',
        'SCOUT_DRIVER', 'MEILISEARCH_HOST', 'MEILISEARCH_KEY',
        'FILESYSTEM_DISK',
    ];
    foreach ($requiredEnv as $key) {
        $v = env_val($key);
        check('App config', "{$key}", $v !== null && $v !== '', $v !== null && $v !== '' ? (str_contains(strtolower($key), 'password') || str_contains(strtolower($key), 'key') || str_contains(strtolower($key), 'secret') ? '***' : $v) : 'MISSING', true);
    }
}

// ---------------------------------------------------------------------------
// 5. Connectivity (best-effort, không fail cứng nếu extension thiếu)
// ---------------------------------------------------------------------------

$dbHost = env_val('DB_HOST');
$dbPort = (int) (env_val('DB_PORT', '3306') ?: 3306);
$dbName = env_val('DB_DATABASE');
$dbUser = env_val('DB_USERNAME');
$dbPass = env_val('DB_PASSWORD', '');

if ($dbHost && extension_loaded('pdo_mysql')) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        $pdo = new PDO($dsn, (string) $dbUser, (string) $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        check('Connectivity', 'MySQL kết nối', true, "Server: {$ver}", true);
    } catch (Throwable $e) {
        check('Connectivity', 'MySQL kết nối', false, $e->getMessage(), true);
    }
} else {
    check('Connectivity', 'MySQL kết nối', false, 'Thiếu DB_* hoặc pdo_mysql', false);
}

$redisHost = env_val('REDIS_HOST');
$redisPort = (int) (env_val('REDIS_PORT', '6379') ?: 6379);
$redisPass = env_val('REDIS_PASSWORD');

if ($redisHost && extension_loaded('redis')) {
    try {
        $redis = new Redis();
        $connected = @$redis->connect($redisHost, $redisPort, 2.0);
        if ($connected && $redisPass && strtolower($redisPass) !== 'null') {
            $redis->auth($redisPass);
        }
        $pong = $connected ? $redis->ping() : false;
        $ok = $pong === true || $pong === '+PONG' || $pong === 'PONG';
        check('Connectivity', 'Redis PING', $ok, $ok ? "{$redisHost}:{$redisPort}" : 'ping failed', true);
    } catch (Throwable $e) {
        check('Connectivity', 'Redis PING', false, $e->getMessage(), true);
    }
} else {
    check('Connectivity', 'Redis PING', false, 'Thiếu REDIS_HOST hoặc ext-redis', false);
}

$meiliHost = env_val('MEILISEARCH_HOST');
if ($meiliHost) {
    $url = rtrim($meiliHost, '/').'/health';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $ok = $body !== false && str_contains($body, 'available');
    check('Connectivity', 'Meilisearch /health', $ok, $ok ? $url : ($body === false ? "unreachable: {$url}" : "unexpected: {$body}"), true);
}

$awsEndpoint = env_val('AWS_ENDPOINT');
$filesystemDisk = env_val('FILESYSTEM_DISK', 'local');
if ($filesystemDisk === 's3' && $awsEndpoint && function_exists('curl_init')) {
    $ch = curl_init(rtrim($awsEndpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    // S3 có thể trả 403/400 khi HEAD root — quan trọng là TCP/HTTP phản hồi.
    check('Connectivity', 'S3 endpoint reachable', $code > 0, $code > 0 ? "HTTP {$code} @ {$awsEndpoint}" : ($err ?: 'no response'), false);
} else {
    $storageRoot = dirname(__DIR__).'/storage/app';
    $writable = is_dir($storageRoot) && is_writable($storageRoot);
    check('Connectivity', 'Local storage writable', $writable, $writable ? $storageRoot : "not writable: {$storageRoot}", true);
}

// ---------------------------------------------------------------------------
// 6. Process / binary hints
// ---------------------------------------------------------------------------

$binaries = ['composer' => false, 'node' => false, 'npm' => false, 'mysql' => false, 'redis-cli' => false];
foreach (array_keys($binaries) as $bin) {
    $which = trim((string) shell_exec('command -v '.escapeshellarg($bin).' 2>/dev/null'));
    $binaries[$bin] = $which !== '';
    check('CLI tools', $bin, $binaries[$bin], $binaries[$bin] ? $which : 'not in PATH', false);
}

// ---------------------------------------------------------------------------
// Aggregate
// ---------------------------------------------------------------------------

$requiredFail = 0;
$optionalFail = 0;
foreach ($checks as $c) {
    if ($c['ok']) {
        continue;
    }
    if ($c['required']) {
        $requiredFail++;
    } else {
        $optionalFail++;
    }
}
$allOk = $requiredFail === 0;
$status = $allOk ? ($optionalFail === 0 ? 'PASS' : 'PASS_WITH_WARNINGS') : 'FAIL';

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

if ($isCli) {
    echo "MedLearn environment check — {$status}\n";
    echo str_repeat('=', 72)."\n";
    $current = '';
    foreach ($checks as $c) {
        if ($c['group'] !== $current) {
            $current = $c['group'];
            echo "\n[{$current}]\n";
        }
        $mark = $c['ok'] ? 'OK  ' : ($c['required'] ? 'FAIL' : 'WARN');
        echo sprintf("  [%s] %-45s %s\n", $mark, $c['name'], $c['detail']);
    }
    echo "\n".str_repeat('=', 72)."\n";
    echo "Required failures: {$requiredFail} · Warnings: {$optionalFail}\n";
    echo "Xóa public/check.php sau khi kiểm tra xong trên production.\n";
    exit($allOk ? 0 : 1);
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$byGroup = [];
foreach ($checks as $c) {
    $byGroup[$c['group']][] = $c;
}

$statusColor = match ($status) {
    'PASS' => '#15803d',
    'PASS_WITH_WARNINGS' => '#a16207',
    default => '#b91c1c',
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>MedLearn — Environment Check</title>
    <style>
        :root { --bg:#0f172a; --card:#1e293b; --text:#e2e8f0; --muted:#94a3b8; --ok:#22c55e; --fail:#ef4444; --warn:#eab308; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        .sub { color: var(--muted); margin-bottom: 1.5rem; font-size: .9rem; }
        .badge { display:inline-block; padding:.35rem .75rem; border-radius:.5rem; font-weight:700; letter-spacing:.04em; background: <?= htmlspecialchars($statusColor) ?>; color:#fff; }
        .meta { margin: 1rem 0 1.5rem; color: var(--muted); font-size: .85rem; }
        section { background: var(--card); border-radius: .75rem; padding: 1rem 1.1rem; margin-bottom: 1rem; }
        h2 { margin: 0 0 .75rem; font-size: 1rem; color: #cbd5e1; }
        table { width:100%; border-collapse: collapse; font-size: .875rem; }
        th, td { text-align:left; padding: .45rem .35rem; border-bottom: 1px solid #334155; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        .mark { font-weight: 700; font-family: ui-monospace, monospace; }
        .ok { color: var(--ok); }
        .fail { color: var(--fail); }
        .warn { color: var(--warn); }
        .detail { color: var(--muted); word-break: break-word; }
        .alert { background:#7f1d1d; color:#fecaca; padding:.75rem 1rem; border-radius:.5rem; margin-bottom:1.25rem; font-size:.875rem; }
        code { background:#0f172a; padding:.1rem .35rem; border-radius:.25rem; font-size:.8rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>MedLearn — Environment Check</h1>
    <p class="sub">Kiểm tra PHP, extension, quyền thư mục và kết nối hạ tầng trước/sau deploy production.</p>
    <span class="badge"><?= htmlspecialchars($status) ?></span>
    <p class="meta">
        PHP <?= htmlspecialchars(PHP_VERSION) ?> · <?= htmlspecialchars(PHP_SAPI) ?> ·
        Required fail: <?= (int) $requiredFail ?> · Warnings: <?= (int) $optionalFail ?> ·
        <?= htmlspecialchars(date('c')) ?>
    </p>
    <div class="alert">
        <strong>Bảo mật:</strong> Xóa hoặc vô hiệu hóa <code>public/check.php</code> ngay sau khi kiểm tra xong.
        Đặt <code>CHECK_TOKEN</code> mạnh trong <code>.env</code> và truy cập bằng <code>?token=...</code>.
    </div>
    <?php foreach ($byGroup as $group => $items): ?>
        <section>
            <h2><?= htmlspecialchars($group) ?></h2>
            <table>
                <thead>
                <tr><th style="width:4rem">Status</th><th>Check</th><th>Detail</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $c): ?>
                    <?php
                    $cls = $c['ok'] ? 'ok' : ($c['required'] ? 'fail' : 'warn');
                    $mark = $c['ok'] ? 'OK' : ($c['required'] ? 'FAIL' : 'WARN');
                    ?>
                    <tr>
                        <td class="mark <?= $cls ?>"><?= $mark ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td class="detail"><?= htmlspecialchars($c['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</div>
</body>
</html>
