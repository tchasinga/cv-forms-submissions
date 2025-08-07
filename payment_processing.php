<?php
require_once 'config.php';

$submission_id = $_GET['submission_id'] ?? 0;

// Check submission status
try {
    $stmt = $pdo->prepare("SELECT * FROM cv_submissions WHERE id = :id");
    $stmt->execute([':id' => $submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$submission) {
    die("Invalid submission ID");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .success {
            color: #2ecc71;
            font-size: 24px;
            margin: 20px 0;
        }
        .instructions {
            text-align: left;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Payment Processing</h2>
        
        <?php if ($submission['payment_status'] == 'completed'): ?>
            <div class="success">
                <i class="fas fa-check-circle" style="font-size: 48px;"></i>
                <p>Payment received successfully!</p>
                <p>Thank you for your payment of KES <?= number_format($submission['amount_paid'], 2) ?>.</p>
                <p>We will process your CV request shortly.</p>
            </div>
        <?php else: ?>
            <div class="spinner"></div>
            <p>Waiting for payment confirmation...</p>
            
            <div class="instructions">
                <h3>Payment Instructions:</h3>
                <ol>
                    <li>Check your phone for an M-Pesa payment request</li>
                    <li>Enter your M-Pesa PIN when prompted</li>
                    <li>Wait for confirmation (this page will update automatically)</li>
                </ol>
                <p>If you don't receive the request within 2 minutes, please try again.</p>
            </div>
            
            <p>Payment for: <?= htmlspecialchars($submission['work_experience']) ?></p>
            <p>Amount: KES <?= number_format($submission['amount_paid'], 2) ?></p>
            
            <script>
                // Check payment status every 5 seconds
                function checkPaymentStatus() {
                    fetch('check_payment.php?submission_id=<?= $submission_id ?>')
                        .then(response => response.json())
                        .then(data => {
                            if (data.payment_status === 'completed') {
                                window.location.reload();
                            }
                        });
                }
                
                setInterval(checkPaymentStatus, 5000);
            </script>
        <?php endif; ?>
    </div>
</body>
</html>