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

// Create table if it doesn't exist
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
    work_experience ENUM('Less than 1 year', '1–3 years', '3–5 years', '5+ years'),
    target_job_role VARCHAR(255),
    cv_writing_fresh_graduate BOOLEAN DEFAULT FALSE,
    cv_update_professional BOOLEAN DEFAULT FALSE,
    cover_letter_writing BOOLEAN DEFAULT FALSE,
    linkedin_optimization BOOLEAN DEFAULT FALSE,
    career_coaching BOOLEAN DEFAULT FALSE,
    combo_package BOOLEAN DEFAULT FALSE,
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
