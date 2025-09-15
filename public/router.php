<?php
// Development router for PHP built-in server
// Prefer index.html (landing) at "/", while allowing access to existing PHP endpoints and static files.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = __DIR__ . $uri;
$projectRoot = dirname(__DIR__);

// Dev convenience: forward /api/* to the real API directory outside public/
if (strpos($uri, '/api/') === 0) {
    $apiPath = $projectRoot . $uri; // e.g., /project/api/xyz.php
    if (is_file($apiPath)) {
        // Let the API script handle the response
        require $apiPath;
        return true;
    } else {
        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'API endpoint not found']);
        return true;
    }
}

// Admin tools under /admin (served from project root /admin)
if ($uri === '/admin' || $uri === '/admin/') {
    $adminIndex = $projectRoot . '/admin/index.php';
    if (is_file($adminIndex)) {
        require $adminIndex;
        return true;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Admin index not found\n";
    return true;
}

if (strpos($uri, '/admin/') === 0) {
    $adminPath = $projectRoot . $uri; // e.g., /project/admin/foo.php or /project/admin/dir/
    if (is_dir($adminPath)) {
        // Try directory index.php
        $indexPhp = rtrim($adminPath, '/') . '/index.php';
        if (is_file($indexPhp)) {
            require $indexPhp;
            return true;
        }
    } elseif (is_file($adminPath)) {
        require $adminPath;
        return true;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Admin page not found: " . $uri . "\n";
    return true;
}

// 1) If the requested path is an existing file (asset, php, html), let the server handle it
if ($uri !== '/' && file_exists($path) && is_file($path)) {
    return false; // delegate to built-in server
}

// 2) Serve landing page for root
if ($uri === '/' || $uri === '') {
    // Redirect to /landing/ so relative assets like css/, js/, images/ resolve correctly
    header('Location: /landing/', true, 302);
    return true;
}

// 3) Handle /landing/* requests
if (strpos($uri, '/landing/') === 0) {
    $landingFile = __DIR__ . $uri;
    if (file_exists($landingFile) && is_file($landingFile)) {
        return false; // let built-in server handle static files
    }
}

// 4) If a directory with its own index.html is requested, serve it
if (is_dir($path) && file_exists($path . '/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($path . '/index.html');
    return true;
}

// 4) Fallback 404
http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo "Not Found: " . $uri . "\n";
