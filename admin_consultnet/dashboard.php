<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once '../config.php';

// Handle filters
$where_conditions = [];
$params = [];

// Filter by full name
if (!empty($_GET['filter_name'])) {
    $where_conditions[] = "full_name LIKE :name";
    $params[':name'] = '%' . $_GET['filter_name'] . '%';
}

// Filter by phone
if (!empty($_GET['filter_phone'])) {
    $where_conditions[] = "phone LIKE :phone";
    $params[':phone'] = '%' . $_GET['filter_phone'] . '%';
}

// Filter by payment status
if (!empty($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
    $where_conditions[] = "payment_status = :status";
    $params[':status'] = $_GET['filter_status'];
}

// Filter by date range
if (!empty($_GET['filter_date_from'])) {
    $where_conditions[] = "DATE(submission_date) >= :date_from";
    $params[':date_from'] = $_GET['filter_date_from'];
}

if (!empty($_GET['filter_date_to'])) {
    $where_conditions[] = "DATE(submission_date) <= :date_to";
    $params[':date_to'] = $_GET['filter_date_to'];
}

// Build SQL query
$sql = "SELECT id, full_name, email, phone, current_job_title, work_experience, payment_status, submission_date FROM cv_submissions";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

$sql .= " ORDER BY submission_date DESC";

// Get filtered submissions
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

        /* Filter Styles */
        .filter-container {
            background-color: var(--bg-color);
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-top: 24px;
            overflow: hidden;
        }
        
        .filter-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .clear-filters-btn {
            background: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        
        .clear-filters-btn:hover {
            background-color: var(--hover-color);
            border-color: var(--text-color);
        }
        
        .filter-form {
            padding: 24px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-row:last-child {
            margin-bottom: 0;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.9rem;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: border-color 0.2s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--text-color);
        }
        
        .filter-actions {
            display: flex;
            align-items: end;
        }
        
        .apply-filters-btn {
            background-color: var(--text-color);
            color: var(--bg-color);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .apply-filters-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .results-count {
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.7;
            margin-left: 8px;
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
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .filter-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .clear-filters-btn {
                align-self: flex-end;
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
            
            .filter-container {
                margin-top: 16px;
            }
            
            .filter-form {
                padding: 16px;
            }
            
            .filter-row {
                gap: 12px;
            }
            
            .apply-filters-btn {
                width: 100%;
                justify-content: center;
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

        <!-- Filter Form -->
        <div class="filter-container">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Submissions</h3>
                <button type="button" id="clearFilters" class="clear-filters-btn">
                    <i class="fas fa-times"></i> Clear All Filters
                </button>
            </div>
            
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filter_name">Full Name</label>
                        <input type="text" id="filter_name" name="filter_name" 
                               value="<?php echo htmlspecialchars($_GET['filter_name'] ?? ''); ?>" 
                               placeholder="Search by name...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_phone">Phone Number</label>
                        <input type="text" id="filter_phone" name="filter_phone" 
                               value="<?php echo htmlspecialchars($_GET['filter_phone'] ?? ''); ?>" 
                               placeholder="Search by phone...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_status">Payment Status</label>
                        <select id="filter_status" name="filter_status">
                            <option value="all">All Statuses</option>
                            <option value="Pending" <?php echo ($_GET['filter_status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo ($_GET['filter_status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Failed" <?php echo ($_GET['filter_status'] ?? '') === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="completed" <?php echo ($_GET['filter_status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filter_date_from">Date From</label>
                        <input type="date" id="filter_date_from" name="filter_date_from" 
                               value="<?php echo htmlspecialchars($_GET['filter_date_from'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_date_to">Date To</label>
                        <input type="date" id="filter_date_to" name="filter_date_to" 
                               value="<?php echo htmlspecialchars($_GET['filter_date_to'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group filter-actions">
                        <label>&nbsp;</label>
                        <button type="submit" class="apply-filters-btn">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Submissions Table -->
        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-file-alt"></i> CV Submissions 
                    <span class="results-count">(<?php echo count($submissions); ?> results)</span>
                </h2>
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

    <script>
        // Clear all filters functionality
        document.getElementById('clearFilters').addEventListener('click', function() {
            // Clear all filter inputs
            document.getElementById('filter_name').value = '';
            document.getElementById('filter_phone').value = '';
            document.getElementById('filter_status').value = 'all';
            document.getElementById('filter_date_from').value = '';
            document.getElementById('filter_date_to').value = '';
            
            // Submit the form to refresh results
            document.getElementById('filterForm').submit();
        });

        // Real-time search for name and phone fields
        document.getElementById('filter_name').addEventListener('input', function() {
            // Add a small delay to avoid too many requests
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });

        document.getElementById('filter_phone').addEventListener('input', function() {
            // Add a small delay to avoid too many requests
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });

        // Auto-submit on status change
        document.getElementById('filter_status').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // Auto-submit on date changes
        document.getElementById('filter_date_from').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('filter_date_to').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // Show active filters count
        function getActiveFiltersCount() {
            let count = 0;
            if (document.getElementById('filter_name').value) count++;
            if (document.getElementById('filter_phone').value) count++;
            if (document.getElementById('filter_status').value !== 'all') count++;
            if (document.getElementById('filter_date_from').value) count++;
            if (document.getElementById('filter_date_to').value) count++;
            return count;
        }

        // Update clear button text with count
        const clearBtn = document.getElementById('clearFilters');
        const activeCount = getActiveFiltersCount();
        if (activeCount > 0) {
            clearBtn.innerHTML = `<i class="fas fa-times"></i> Clear Filters (${activeCount})`;
        }
    </script>
</body>
</html>