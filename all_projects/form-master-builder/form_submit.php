<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'form_id' => $_POST['form_id'],
        'data' => json_encode($_POST)
    ];
    
    $stmt = $pdo->prepare("INSERT INTO form_submissions (form_id, data) VALUES (:form_id, :data)");
    $stmt->execute($data);
    
    header("Location: form_view.php?id=".$_POST['form_id']."&success=1");
    exit();
}
?>