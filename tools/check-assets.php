<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

echo '<h1>Assets Diagnostic</h1>';

$assets = dirname(__DIR__) . '/assets';
$css = dirname(__DIR__) . '/assets/css';

echo '<h2>Assets folder</h2>';
echo '<p>' . htmlspecialchars($assets) . '</p>';

if (is_dir($assets)) {
    echo '<p style="color:green;"><strong>ASSETS EXISTS</strong></p>';
    echo '<pre>';
    print_r(scandir($assets));
    echo '</pre>';
} else {
    echo '<p style="color:red;"><strong>ASSETS NOT FOUND</strong></p>';
}

echo '<h2>CSS folder</h2>';
echo '<p>' . htmlspecialchars($css) . '</p>';

if (is_dir($css)) {
    echo '<p style="color:green;"><strong>CSS FOLDER EXISTS</strong></p>';
    echo '<pre>';
    print_r(scandir($css));
    echo '</pre>';
} else {
    echo '<p style="color:red;"><strong>CSS FOLDER NOT FOUND</strong></p>';
}

echo '<h2>Style file</h2>';

$style = $css . '/style.css';

echo '<p>' . htmlspecialchars($style) . '</p>';

if (is_file($style)) {
    echo '<p style="color:green;"><strong>STYLE.CSS EXISTS</strong></p>';
} else {
    echo '<p style="color:red;"><strong>STYLE.CSS NOT FOUND</strong></p>';
}