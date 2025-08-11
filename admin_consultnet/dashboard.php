<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once '../config.php';

// Get all submissions
try {
    $stmt = $pdo->query("SELECT id, full_name, email, phone, current_job_title, work_experience, payment_status, submission_date FROM cv_submissions ORDER BY submission_date DESC");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CV Submissions</title>
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
            gap: 20px;
        }

        .welcome-text {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .logout-btn {
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

        .logout-btn:hover {
            background-color: var(--hover-color);
            border-color: var(--text-color);
        }

        .container {
            max-width: 1400px;
            margin: 32px auto;
            padding: 0 24px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-color);
            padding: 24px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .stat-card p {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .table-container {
            background: var(--bg-color);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.8;
        }

        tr:not(:last-child) {
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background-color: var(--hover-color);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            opacity: var(--badge-opacity);
        }

        .status-pending {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--text-color);
        }

        .status-paid {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--text-color);
        }

        .status-failed {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--text-color);
        }

        .status-completed {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--text-color);
        }

        .view-btn {
            background: transparent;
            color: var(--text-color);
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .view-btn:hover {
            background-color: var(--hover-color);
            border-color: var(--text-color);
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
        }

        .no-data i {
            font-size: 3rem;
            opacity: 0.2;
            margin-bottom: 20px;
        }

        .no-data h3 {
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .no-data p {
            opacity: 0.6;
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
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 12px 16px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .table-header {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-columns"></i> Dashboard</h1>
            <div class="header-actions">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo count($submissions); ?></h3>
                <p>Total Submissions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($submissions, function($s) { return $s['payment_status'] === 'completed'; })); ?></h3>
                <p>Completed Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($submissions, function($s) { return $s['payment_status'] === 'Pending'; })); ?></h3>
                <p>Pending Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($submissions, function($s) { return $s['payment_status'] === 'Failed'; })); ?></h3>
                <p>Failed Payments</p>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-file-alt"></i> CV Submissions</h2>
            </div>
            
            <div class="table-responsive">
                <?php if (empty($submissions)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <h3>No submissions found</h3>
                        <p>There are no CV submissions in the database yet.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Current Job</th>
                                <th>Payment Status</th>
                                <th>Submission Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td>#<?php echo $submission['id']; ?></td>
                                    <td><?php echo htmlspecialchars($submission['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['email']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($submission['current_job_title']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($submission['payment_status']); ?>">
                                            <?php echo htmlspecialchars($submission['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?></td>
                                    <td>
                                        <a href="details.php?id=<?php echo $submission['id']; ?>" class="view-btn">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>