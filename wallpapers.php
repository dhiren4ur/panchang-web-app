<?php
// wallpapers.php
// Scans wallpapers/ folder, returns image list as JSON.
// Just upload images — no code changes ever needed.

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$dir     = __DIR__ . '/wallpapers/';
$allowed = ['jpg','jpeg','png','webp'];
$images  = [];

if (!is_dir($dir)) {
    echo json_encode(['success'=>false,'message'=>'wallpapers/ folder not found','images'=>[]]);
    exit;
}

foreach (scandir($dir) as $file) {
    if ($file === '.' || $file === '..') continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) continue;

    $name    = pathinfo($file, PATHINFO_FILENAME); // e.g. radha_krishna_001
    $images[] = [
        'file'  => $file,
        'name'  => $name,
        'label' => makeLabel($name),
    ];
}

// Sort alphabetically — groups same-name images together
usort($images, fn($a,$b) => strcmp(strtolower($a['file']), strtolower($b['file'])));

echo json_encode(['success'=>true,'count'=>count($images),'images'=>$images]);

function makeLabel(string $name): string {
    $name = preg_replace('/_\d+$/', '', $name); // remove _001
    $name = str_replace('_', ' ', $name);        // underscores → spaces
    return ucwords(strtolower(trim($name)));      // Title Case
}
?>
