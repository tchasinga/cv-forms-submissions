<?php
require_once 'config.php';

header('Content-Type: application/json');

$submission_id = $_GET['submission_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT payment_status FROM cv_submissions WHERE id = :id");
    $stmt->execute([':id' => $submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        echo json_encode([
            'payment_status' => $submission['payment_status']
        ]);
    } else {
        echo json_encode(['error' => 'Invalid submission ID']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>