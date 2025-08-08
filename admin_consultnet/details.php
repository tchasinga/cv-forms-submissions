<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once '../config.php';

$id = $_GET['id'] ?? 0;

// Get submission details
try {
    $stmt = $pdo->prepare("SELECT * FROM cv_submissions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        header('Location: dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details - CV Submissions</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 300;
        }

        .back-btn, .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s ease;
            margin-left: 10px;
        }

        .back-btn:hover, .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .details-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            color: #333;
            font-size: 1.5rem;
            margin: 0;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .details-content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section h3 {
            color: #667eea;
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: #333;
            font-size: 1rem;
            word-break: break-word;
        }

        .file-links {
            margin-top: 10px;
        }

        .file-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 10px;
            margin-bottom: 5px;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .file-link:hover {
            background: #5a6fd8;
        }

        .file-link.disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .file-link.disabled:hover {
            background: #6c757d;
        }

        .notes-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .notes-section h4 {
            color: #495057;
            margin-bottom: 10px;
        }

        .notes-content {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .details-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-user"></i> Submission Details</h1>
            <div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="details-card">
            <div class="card-header">
                <h2>Submission #<?php echo $submission['id']; ?> - <?php echo htmlspecialchars($submission['full_name']); ?></h2>
                <span class="status-badge status-<?php echo strtolower($submission['payment_status']); ?>">
                    <?php echo htmlspecialchars($submission['payment_status']); ?>
                </span>
            </div>
            
            <div class="details-content">
                <!-- Personal Information -->
                <div class="section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['full_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['phone']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Preferred Contact</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['preferred_contact']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current Location</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['current_location']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Education & Experience -->
                <div class="section">
                    <h3><i class="fas fa-graduation-cap"></i> Education & Experience</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Education Level</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['education_level']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Field of Study</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['field_of_study']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Current Job Title</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['current_job_title']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Target Job Role</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['target_job_role']); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Service Details -->
                <div class="section">
                    <h3><i class="fas fa-tools"></i> Service Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Work Experience & Pricing</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['work_experience']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">CV Service Required</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['cv_service_required']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Turnaround Time</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['turnaround_time']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Budget Range</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['budget_range']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Amount Paid</div>
                            <div class="info-value">KES <?php echo number_format($submission['amount_paid'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Files -->
                <div class="section">
                    <h3><i class="fas fa-file"></i> Uploaded Files</h3>
                    <div class="file-links">
                        <?php if (!empty($submission['current_cv_filename'])): ?>
                            <a href="../uploads/<?php echo htmlspecialchars($submission['current_cv_filename']); ?>" 
                               class="file-link" target="_blank">
                                <i class="fas fa-download"></i> Current CV
                            </a>
                        <?php else: ?>
                            <span class="file-link disabled">No CV uploaded</span>
                        <?php endif; ?>

                        <?php if (!empty($submission['job_description_filename'])): ?>
                            <a href="../uploads/<?php echo htmlspecialchars($submission['job_description_filename']); ?>" 
                               class="file-link" target="_blank">
                                <i class="fas fa-download"></i> Job Description
                            </a>
                        <?php else: ?>
                            <span class="file-link disabled">No job description uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Notes -->
                <?php if (!empty($submission['additional_notes'])): ?>
                    <div class="section">
                        <h3><i class="fas fa-sticky-note"></i> Additional Notes</h3>
                        <div class="notes-section">
                            <div class="notes-content">
                                <?php echo nl2br(htmlspecialchars($submission['additional_notes'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Submission Info -->
                <div class="section">
                    <h3><i class="fas fa-info-circle"></i> Submission Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Submission Date</div>
                            <div class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($submission['submission_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Submission ID</div>
                            <div class="info-value">#<?php echo $submission['id']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
