<?php
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

require_once '../config.php';

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    header('Location: dashboard.php?error=invalid');
    exit();
}

try {
    $pdo->beginTransaction();

    // Get filenames to clean up
    $stmt = $pdo->prepare('SELECT current_cv_filename, job_description_filename FROM cv_submissions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        $pdo->rollBack();
        header('Location: dashboard.php?error=notfound');
        exit();
    }

    // Delete the row
    $delStmt = $pdo->prepare('DELETE FROM cv_submissions WHERE id = :id');
    $delStmt->execute([':id' => $id]);

    $pdo->commit();

    // Delete files (best-effort, after commit)
    $uploadDir = realpath(__DIR__ . '/../uploads');
    if ($uploadDir !== false) {
        foreach (['current_cv_filename', 'job_description_filename'] as $columnName) {
            $filename = $submission[$columnName] ?? '';
            if (!empty($filename)) {
                $safeName = basename($filename);
                $filePath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
        }
    }

    header('Location: dashboard.php?deleted=1');
    exit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: dashboard.php?error=1');
    exit();
}
 
