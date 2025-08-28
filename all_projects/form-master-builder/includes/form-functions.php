<?php
function saveForm($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO forms (title, description, structure) VALUES (?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $data['description'] ?? '',
        json_encode($data['fields'])
    ]);
    return $pdo->lastInsertId();
}

function getForms($pdo) {
    $stmt = $pdo->query("SELECT * FROM forms ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getForm($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>