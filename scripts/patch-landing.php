<?php
// Patch script to ensure public/index.html includes our persistent overrides.css
// Usage: php scripts/patch-landing.php

$indexPath = __DIR__ . '/../public/index.html';
if (!file_exists($indexPath)) {
    fwrite(STDERR, "Error: public/index.html not found\n");
    exit(1);
}

$html = file_get_contents($indexPath);
if ($html === false) {
    fwrite(STDERR, "Error: Could not read public/index.html\n");
    exit(1);
}

$overridesTag = '<link href="css/overrides.css" rel="stylesheet" type="text/css">';

if (strpos($html, $overridesTag) !== false) {
    echo "overrides.css already present. No changes made.\n";
    exit(0);
}

// Try to insert right after the Webflow project CSS line if found
$pattern = '/(<link\s+href=\"css\/atex-latam---calculadora-de-losas\.webflow\.css\"[^>]*>)/i';
if (preg_match($pattern, $html)) {
    $patched = preg_replace($pattern, "$1\n  $overridesTag", $html, 1);
} else {
    // Fallback: insert before </head>
    $patched = preg_replace('/<\/head>/i', "  $overridesTag\n</head>", $html, 1);
}

if ($patched === null) {
    fwrite(STDERR, "Error: Failed to patch HTML.\n");
    exit(1);
}

if (file_put_contents($indexPath, $patched) === false) {
    fwrite(STDERR, "Error: Could not write changes to public/index.html\n");
    exit(1);
}

echo "Patched: linked css/overrides.css in public/index.html\n";
