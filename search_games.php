<?php
require 'config.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT slug, title, cover_image, category, downloads_count FROM games WHERE title LIKE ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute(['%' . $q . '%']);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
