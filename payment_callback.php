<?php
require_once 'config.php';

// Get the callback data
$callbackData = json_decode(file_get_contents('php://input'), true);

// Extract relevant information
$submission_id = $_GET['submission_id'] ?? 0;
$resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? 1;
$resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? 'Payment failed';
$merchantRequestID = $callbackData['Body']['stkCallback']['MerchantRequestID'] ?? '';
$checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? '';

// Check if payment was successful
if ($resultCode == 0) {
    $paymentStatus = 'completed';
    
    // Get amount from callback metadata
    $amount = 0;
    foreach ($callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
        if ($item['Name'] == 'Amount') {
            $amount = $item['Value'];
            break;
        }
    }
    
    // Update the database
    try {
        $stmt = $pdo->prepare("UPDATE cv_submissions 
                              SET payment_status = :status, amount_paid = :amount
                              WHERE id = :id");
        $stmt->execute([
            ':status' => $paymentStatus,
            ':amount' => $amount,
            ':id' => $submission_id
        ]);
        
        // You can also send a confirmation email here
    } catch(PDOException $e) {
        // Log the error
        error_log("Database update failed: " . $e->getMessage());
    }
} else {
    // Payment failed
    try {
        $stmt = $pdo->prepare("UPDATE cv_submissions 
                              SET payment_status = 'Failed'
                              WHERE id = :id");
        $stmt->execute([':id' => $submission_id]);
    } catch(PDOException $e) {
        error_log("Database update failed: " . $e->getMessage());
    }
}

// Send response to M-Pesa
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);
?>