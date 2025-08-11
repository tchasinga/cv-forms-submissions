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
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #e0e0e0;
            --hover-color: #f5f5f5;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --header-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            --badge-opacity: 0.9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.2s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .header {
            background-color: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            padding: 18px 0;
            box-shadow: var(--header-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .welcome-text {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .back-btn, .logout-btn {
            background: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .back-btn:hover, .logout-btn:hover {
            background-color: var(--hover-color);
            border-color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 24px;
        }

        .details-card {
            background: var(--bg-color);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            opacity: var(--badge-opacity);
            background-color: rgba(0, 0, 0, 0.1);
        }

        .details-content {
            padding: 24px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 16px;
        }

        .info-label {
            font-weight: 500;
            opacity: 0.8;
            margin-bottom: 6px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 0.95rem;
            word-break: break-word;
            line-height: 1.5;
        }

        .file-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .file-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            color: var(--text-color);
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .file-link:hover {
            background-color: var(--hover-color);
            border-color: var(--text-color);
        }

        .file-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .notes-section {
            background: var(--hover-color);
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            border: 1px solid var(--border-color);
        }

        .notes-content {
            line-height: 1.6;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .details-content {
                padding: 16px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }
            
            .file-links {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .file-link {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-file-alt"></i> Submission Details</h1>
            <div class="header-actions">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
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
                <h2>#<?php echo $submission['id']; ?> - <?php echo htmlspecialchars($submission['full_name']); ?></h2>
                <span class="status-badge">
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
                            <div class="info-value">CV <?php echo htmlspecialchars($submission['work_experience']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">CV Service Required</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['cv_service_required']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Turnaround Time</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['turnaround_time']); ?></div>
                        </div>
                        <!-- <div class="info-item">
                            <div class="info-label">Budget Range</div>
                            <div class="info-value"><?php echo htmlspecialchars($submission['budget_range']); ?></div>
                        </div> -->
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
                            <span class="file-link disabled">
                                <i class="fas fa-times-circle"></i> No CV uploaded
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($submission['job_description_filename'])): ?>
                            <a href="../uploads/<?php echo htmlspecialchars($submission['job_description_filename']); ?>" 
                               class="file-link" target="_blank">
                                <i class="fas fa-download"></i> Job Description
                            </a>
                        <?php else: ?>
                            <span class="file-link disabled">
                                <i class="fas fa-times-circle"></i> No job description uploaded
                            </span>
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