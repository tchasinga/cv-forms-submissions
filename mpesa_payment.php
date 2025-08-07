<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
$amount = isset($_POST['amount']) ? $_POST['amount'] : null;
$phone = isset($_POST['phone']) ? $_POST['phone'] : null;
if (!$amount || !$phone) {
    echo json_encode(['success' => false, 'message' => 'Missing amount or phone']);
    exit;
}
// Simulate MPESA payment (replace with real API call in production)
// Here you would use cURL to call the MPESA API as in your provided code
// For demo, always return success
sleep(2); // Simulate network delay
// Example: if phone starts with 254, treat as success
if (strpos($phone, '254') === 0) {
    echo json_encode(['success' => true, 'message' => 'Payment initiated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
}
