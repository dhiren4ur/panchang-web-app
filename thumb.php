<?php
// thumb.php
// Generates a cached thumbnail of a wallpaper image
// Usage: thumb.php?f=krishna_001.jpg&w=200
// Caches result in wallpapers/thumbs/ folder

$file  = basename(getParam('f', ''));   // filename only, no path traversal
$width = max(50, min(400, (int)getParam('w', 200)));  // 50-400px max

if (!$file) { http_response_code(400); exit; }

$src_path   = __DIR__ . '/wallpapers/' . $file;
$thumb_dir  = __DIR__ . '/wallpapers/thumbs/';
$thumb_path = $thumb_dir . $width . '_' . $file;

// Serve cached thumb if exists and newer than source
if (file_exists($thumb_path) && filemtime($thumb_path) >= filemtime($src_path)) {
    serveImage($thumb_path);
    exit;
}

// Source must exist
if (!file_exists($src_path)) { http_response_code(404); exit; }

// Create thumbs dir if needed
if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
}

// Get image info
$info = getimagesize($src_path);
if (!$info) { http_response_code(422); exit; }

[$src_w, $src_h, $type] = $info;

// Calculate height maintaining aspect ratio
$ratio  = $src_h / $src_w;
$height = (int)round($width * $ratio);

// Create source image
switch ($type) {
    case IMAGETYPE_JPEG: $src_img = imagecreatefromjpeg($src_path); break;
    case IMAGETYPE_PNG:  $src_img = imagecreatefrompng($src_path);  break;
    case IMAGETYPE_WEBP: $src_img = imagecreatefromwebp($src_path); break;
    default: http_response_code(415); exit;
}

if (!$src_img) { http_response_code(500); exit; }

// Create thumb canvas
$thumb = imagecreatetruecolor($width, $height);

// Handle transparency for PNG
if ($type === IMAGETYPE_PNG) {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
}

// Resize
imagecopyresampled($thumb, $src_img, 0, 0, 0, 0, $width, $height, $src_w, $src_h);
imagedestroy($src_img);

// Save to cache
switch ($type) {
    case IMAGETYPE_JPEG: imagejpeg($thumb, $thumb_path, 80); break;
    case IMAGETYPE_PNG:  imagepng($thumb,  $thumb_path, 7);  break;
    case IMAGETYPE_WEBP: imagewebp($thumb, $thumb_path, 80); break;
}
imagedestroy($thumb);

serveImage($thumb_path);

// ── Helpers ──────────────────────────────────────────────────

function serveImage(string $path) {
    $mime = mime_content_type($path);
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');  // cache 1 day in browser
    header('Content-Length: ' . filesize($path));
    readfile($path);
}

function getParam(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}
?>
