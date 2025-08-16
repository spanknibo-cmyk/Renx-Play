<?php
require 'config.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT slug, title, cover_image, category, downloads_count FROM games WHERE title LIKE ? LIMIT 8");
$stmt->execute(['%' . $q . '%']);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($games as &$g) {
    $g['slug'] = preg_replace('/[^a-zA-Z0-9-_]/', '', $g['slug']);
}
unset($g);
echo json_encode($games);
?>
