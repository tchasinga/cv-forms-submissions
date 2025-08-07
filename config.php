<?php
// Database configuration
$host = 'localhost';
$dbname = 'formsubmit';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$sql = "CREATE TABLE IF NOT EXISTS cv_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    preferred_contact ENUM('Email', 'Phone', 'WhatsApp'),
    current_location VARCHAR(255),
    education_level VARCHAR(100),
    field_of_study VARCHAR(255),
    current_job_title VARCHAR(255),
    work_experience ENUM(
        '0–5 years KES 3,000', 
        '6 – 15 years KES 6,000', 
        'Above 15 years KES 8,000', 
        'Stand Alone cover letter KES 1,500 (Without CV)', 
        'Standard Fees KES 4,000', 
        'Interview Preparation (1Hr Zoom Meeting) KES 4,000',
        'Career Coaching (1Hr 30 Mins Zoom Meeting) KES 6,000'
    ),
    target_job_role VARCHAR(255),
    cv_service_required VARCHAR(255),
    current_cv_filename VARCHAR(255),
    job_description_filename VARCHAR(255),
    turnaround_time ENUM('24–48 hours (Express)', '3–5 business days', '1 week'),
    budget_range VARCHAR(100),
    additional_notes TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql);
} catch(PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}
?>