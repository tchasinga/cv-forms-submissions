<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Create database table if not exists
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
    payment_status ENUM('Pending', 'Paid', 'Failed', 'completed') DEFAULT 'Pending',
    amount_paid VARCHAR(200),
    job_description_filename VARCHAR(255),
    turnaround_time ENUM('24–48 hours (Express)', '3–5 business days', '1 week'),
    budget_range VARCHAR(100),
    additional_notes TEXT,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = "Error creating table: " . $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file uploads
        $current_cv_filename = '';
        $job_description_filename = '';

        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Failed to create uploads directory");
            }
        }

        // Ensure directory is writable
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0777);
            if (!is_writable($upload_dir)) {
                throw new Exception("Uploads directory is not writable");
            }
        }

        // Process CV file upload
        if (isset($_FILES['current_cv']) && $_FILES['current_cv']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $file_type = $_FILES['current_cv']['type'];
            $file_ext = strtolower(pathinfo($_FILES['current_cv']['name'], PATHINFO_EXTENSION));

            if (in_array($file_type, $allowed_types) && in_array($file_ext, ['pdf', 'doc', 'docx'])) {
                $current_cv_filename = uniqid('cv_', true) . '.' . $file_ext;
                $upload_path = $upload_dir . $current_cv_filename;

                if (!move_uploaded_file($_FILES['current_cv']['tmp_name'], $upload_path)) {
                    $error_info = error_get_last();
                    $tmp_file_exists = file_exists($_FILES['current_cv']['tmp_name']);
                    $upload_dir_writable = is_writable($upload_dir);
                    $error_msg = "Failed to upload CV file. ";
                    $error_msg .= "Temp file exists: " . ($tmp_file_exists ? 'Yes' : 'No') . ". ";
                    $error_msg .= "Upload dir writable: " . ($upload_dir_writable ? 'Yes' : 'No') . ". ";
                    $error_msg .= "Error: " . ($error_info['message'] ?? 'Unknown error');
                    throw new Exception($error_msg);
                }
            } else {
                throw new Exception("Invalid file type for CV. Only PDF, DOC, and DOCX files are allowed.");
            }
        }

        // Process Job Description file upload
        if (isset($_FILES['job_description']) && $_FILES['job_description']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $file_type = $_FILES['job_description']['type'];
            $file_ext = strtolower(pathinfo($_FILES['job_description']['name'], PATHINFO_EXTENSION));

            if (in_array($file_type, $allowed_types) && in_array($file_ext, ['pdf', 'doc', 'docx'])) {
                $job_description_filename = uniqid('jd_', true) . '.' . $file_ext;
                $upload_path = $upload_dir . $job_description_filename;

                if (!move_uploaded_file($_FILES['job_description']['tmp_name'], $upload_path)) {
                    $error_info = error_get_last();
                    $tmp_file_exists = file_exists($_FILES['job_description']['tmp_name']);
                    $upload_dir_writable = is_writable($upload_dir);
                    $error_msg = "Failed to upload job description file. ";
                    $error_msg .= "Temp file exists: " . ($tmp_file_exists ? 'Yes' : 'No') . ". ";
                    $error_msg .= "Upload dir writable: " . ($upload_dir_writable ? 'Yes' : 'No') . ". ";
                    $error_msg .= "Error: " . ($error_info['message'] ?? 'Unknown error');
                    throw new Exception($error_msg);
                }
            } else {
                throw new Exception("Invalid file type for job description. Only PDF, DOC, and DOCX files are allowed.");
            }
        }

        // Extract amount from work_experience selection
        $work_experience = $_POST['work_experience'];
        $amount = 0;
        
        // Use the calculated amount from the hidden field if available
        if (isset($_POST['calculated_amount']) && !empty($_POST['calculated_amount'])) {
            $amount = (int)$_POST['calculated_amount'];
        } else {
            // Fallback to extracting from work_experience if hidden field is not available
            if (preg_match('/KES (\d+,\d+)/', $work_experience, $matches)) {
                $amount = (int)str_replace(',', '', $matches[1]);
                
                // Apply 15% increase for CV services when turnaround time is selected
                if (isset($_POST['turnaround_time']) && !empty($_POST['turnaround_time'])) {
                    if (strpos($work_experience, 'CV') !== false || strpos($work_experience, 'Cover Letter') !== false) {
                        $amount = round($amount * 1.5); // 15% = 1.5x
                    }
                }
            }
        }

        // Prepare SQL statement
        $sql = "INSERT INTO cv_submissions (
            full_name, email, phone, preferred_contact, current_location,
            education_level, field_of_study, current_job_title, work_experience,
            target_job_role, cv_service_required, current_cv_filename, job_description_filename,
            turnaround_time, budget_range, additional_notes, amount_paid, payment_status
        ) VALUES (
            :full_name, :email, :phone, :preferred_contact, :current_location,
            :education_level, :field_of_study, :current_job_title, :work_experience,
            :target_job_role, :cv_service_required, :current_cv_filename, :job_description_filename,
            :turnaround_time, :budget_range, :additional_notes, :amount_paid, :payment_status
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => htmlspecialchars($_POST['full_name']),
            ':email' => filter_var($_POST['email'], FILTER_SANITIZE_EMAIL),
            ':phone' => htmlspecialchars($_POST['phone']),
            ':preferred_contact' => htmlspecialchars($_POST['preferred_contact']),
            ':current_location' => htmlspecialchars($_POST['current_location']),
            ':education_level' => htmlspecialchars($_POST['education_level']),
            ':field_of_study' => htmlspecialchars($_POST['field_of_study']),
            ':current_job_title' => htmlspecialchars($_POST['current_job_title']),
            ':work_experience' => htmlspecialchars($work_experience),
            ':target_job_role' => htmlspecialchars($_POST['target_job_role']),
            ':cv_service_required' => htmlspecialchars($_POST['cv_service_required']),
            ':current_cv_filename' => $current_cv_filename,
            ':job_description_filename' => $job_description_filename,
            ':turnaround_time' => htmlspecialchars($_POST['turnaround_time']),
            ':budget_range' => htmlspecialchars($_POST['budget_range']),
            ':additional_notes' => htmlspecialchars($_POST['additional_notes']),
            ':amount_paid' => $amount,
            ':payment_status' => 'Pending'
        ]);

        $submission_id = $pdo->lastInsertId();

        // If form submitted with payment request
        if (isset($_POST['initiate_payment'])) {
            // Process M-Pesa payment
            $phone = $_POST['phone'];
            $Msisdn = '254' . substr($phone, -9); // Format phone number

            // M-Pesa credentials
            $shortcode = '453369';
            $consumerkey = "EvhuRPmj7cGW5NU012DJnK2IkCmgVv4W";
            $consumersecret = "l9mCfLBpBNYlzkLz";
            $passkey = "f505b69ebe1094e67d195c6086ca0ad1a43080a313c6aeacb98da1bb78a1782e";
            $callback_url = "https://yourdomain.com/payment_callback.php?submission_id=$submission_id";

            // Get access token
            $authenticationurl = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            $credentials = base64_encode($consumerkey . ':' . $consumersecret);
            $ch = curl_init($authenticationurl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result = json_decode($result);
            $access_token = $result->access_token;
            curl_close($ch);

            if ($access_token) {
                // Initiate STK push
                $t = date('YmdHis');
                $password = base64_encode($shortcode . $passkey . $t);

                $stk_url = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
                $curl_post_data = [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $t,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $Msisdn,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $Msisdn,
                    'CallBackURL' => $callback_url,
                    'AccountReference' => 'CVService',
                    'TransactionDesc' => 'CV Writing Service'
                ];

                $ch = curl_init($stk_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ]);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $response = curl_exec($ch);
                curl_close($ch);

                $message = "Payment request sent to your phone. Please complete the payment to proceed.";
                $messageType = 'success';

                // Redirect to thank you page or show payment instructions
                header("Location: payment_processing.php?submission_id=$submission_id");
                exit();
            } else {
                $message = "Failed to initiate payment. Please try again.";
                $messageType = 'error';
            }
        } else {
            $message = "Thank you! Your CV submission has been received successfully. Please proceed to payment.";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV Form Submission</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a6bff;
            --secondary-color: #6c5ce7;
            --success-color: #00b894;
            --error-color: #d63031;
            --warning-color: #fdcb6e;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #2d3436;
            --border-color: #dfe6e9;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px var(--shadow-color);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .form-container {
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .message.error {
            background: rgba(214, 48, 49, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .form-section {
            margin-bottom: 30px;
            padding: 25px;
            background: var(--light-color);
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .form-section:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .form-section h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.2);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .checkbox-option:hover {
            border-color: var(--primary-color);
            background: rgba(74, 107, 255, 0.05);
        }

        .checkbox-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: rgba(74, 107, 255, 0.05);
            border: 2px dashed var(--primary-color);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: rgba(74, 107, 255, 0.1);
            border-color: var(--secondary-color);
        }

        .file-upload-label i {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .file-name {
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        .payment-section {
            background: rgba(74, 107, 255, 0.05);
            border-left: 4px solid var(--success-color);
        }

        .amount-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--success-color);
            margin: 15px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .amount-breakdown {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .base-amount {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .increase-info {
            color: var(--warning-color);
            font-weight: 500;
            background: rgba(253, 203, 110, 0.1);
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid var(--warning-color);
        }
        
        .total-amount {
            color: var(--success-color);
            font-weight: 600;
            font-size: 1.2rem;
            border-top: 2px solid var(--border-color);
            padding-top: 10px;
            margin-top: 5px;
        }
        
        .pricing-note {
            background: rgba(74, 107, 255, 0.1);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: var(--primary-color);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pricing-note i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #00cec9 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }

        .required {
            color: var(--error-color);
            font-weight: bold;
        }

        .form-footer {
            margin-top: 30px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                border-radius: 0;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .form-container {
                padding: 20px;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-section {
            animation: fadeIn 0.5s ease forwards;
        }

        .form-section:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-section:nth-child(2) {
            animation-delay: 0.2s;
        }

        .form-section:nth-child(3) {
            animation-delay: 0.3s;
        }

        .form-section:nth-child(4) {
            animation-delay: 0.4s;
        }

        .form-section:nth-child(5) {
            animation-delay: 0.5s;
        }

        .form-section:nth-child(6) {
            animation-delay: 0.6s;
        }

        .form-section:nth-child(7) {
            animation-delay: 0.7s;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-alt"></i> Professional CV Services</h1>
            <p>Complete the form below to get started with your career transformation</p>
        </div>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="cvForm">
                <!-- Personal Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user-tie"></i> Personal Information</h3>

                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="preferred_contact">Preferred Contact Method <span class="required">*</span></label>
                        <select id="preferred_contact" name="preferred_contact" class="form-control" required>
                            <option value="">Select Preferred Contact Method</option>
                            <option value="Email">Email</option>
                            <option value="Phone">Phone</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="current_location">Current Location (City & Country)</label>
                        <input type="text" id="current_location" name="current_location" class="form-control" placeholder="e.g., Nairobi, Kenya">
                    </div>
                </div>

                <!-- Education Background -->
                <div class="form-section">
                    <h3><i class="fas fa-graduation-cap"></i> Education Background</h3>

                    <div class="form-group">
                        <label for="education_level">Highest Level of Education Achieved</label>
                        <select id="education_level" name="education_level" class="form-control">
                            <option value="">Select Education Level</option>
                            <option value="High School">High School</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Higher Diploma">Higher Diploma</option>
                            <option value="Bachelors">Bachelors</option>
                            <option value="Masters">Masters</option>
                            <option value="Past graduate">Past graduate</option>
                            <option value="PhD">PhD</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="field_of_study">Field of Study</label>
                        <input type="text" id="field_of_study" name="field_of_study" class="form-control" placeholder="e.g., Computer Science, Business Administration">
                    </div>
                </div>

                <!-- Career Information -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Career Information</h3>

                    <div class="form-group">
                        <label for="current_job_title">Current Job Title (if any)</label>
                        <input type="text" id="current_job_title" name="current_job_title" class="form-control" placeholder="e.g., Software Developer">
                    </div>

                    <div class="form-group">
                        <label for="work_experience">CV Service Required <span class="required">*</span></label>
                        <select id="work_experience" name="work_experience" class="form-control" required onchange="updateAmount()">
                            <option value="">Select one a serveice</option>
                            <option value="CV 0–5 years KES 3,000">CV 0–5 years KES 3,000</option>
                            <option value="CV 6– 15 years KES 6,000">CV 6 – 15 years KES 6,000</option>
                            <option value="CV Above 15 years KES 8,000">CV Above 15 years KES 8,000</option>
                            <option value="Stand Alone cover letter KES 1,500 (Without CV)">Stand Alone cover letter KES 1,500 (Without CV)</option>
                            <option value="Standard Fees KES 4,000">Standard Fees KES 4,000</option>
                            <option value="Interview Preparation (1Hr Zoom Meeting) KES 4,000">Interview Preparation (1Hr Zoom Meeting) KES 4,000</option>
                            <option value="Career Coaching (1Hr 30 Mins Zoom Meeting) KES 6,000">Career Coaching (1Hr 30 Mins Zoom Meeting) KES 6,000</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="target_job_role">Target Job Role / Industry</label>
                        <input type="text" id="target_job_role" name="target_job_role" class="form-control" placeholder="e.g., Senior Software Engineer, Marketing Manager">
                    </div>
                </div>

                <!-- CV Service Required -->
                <!-- <div class="form-section">
                    <h3><i class="fas fa-tools"></i> CV Service Required</h3>
                    <div class="form-group">
                        <label for="cv_service_required">Select Service <span class="required">*</span></label>
                        <select id="cv_service_required" name="cv_service_required" class="form-control" required>
                            <option value="">Select CV Service Required</option>
                            <option value="CV Writing (Fresh Graduate)">CV Writing (Fresh Graduate)</option>
                            <option value="CV Update (Professional)">CV Update (Professional)</option>
                            <option value="Cover Letter Writing">Cover Letter Writing</option>
                            <option value="LinkedIn Profile Optimization">LinkedIn Profile Optimization</option>
                            <option value="Career Coaching">Career Coaching</option>
                            <option value="Interview Preparation">Interview Preparation</option>
                            <option value="CV + Cover Letter + LinkedIn (Combo Package)">CV + Cover Letter + LinkedIn (Combo Package)</option>
                        </select>
                    </div>
                </div> -->

                <!-- Supporting Documents -->
                <div class="form-section">
                    <h3><i class="fas fa-paperclip"></i> Supporting Documents</h3>

                    <div class="form-group">
                        <label>Upload Your Current CV (if any)</label>
                        <div class="file-upload">
                            <input type="file" id="current_cv" name="current_cv" accept=".pdf,.doc,.docx">
                            <label for="current_cv" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose CV file (PDF, DOC, DOCX)</span>
                            </label>
                            <div id="currentCvName" class="file-name"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Upload Job Description (if targeting a specific job)</label>
                        <div class="file-upload">
                            <input type="file" id="job_description" name="job_description" accept=".pdf,.doc,.docx">
                            <label for="job_description" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Choose Job Description file (PDF, DOC, DOCX)</span>
                            </label>
                            <div id="jobDescName" class="file-name"></div>
                        </div>
                    </div>
                </div>

                <!-- Timeline & Budget -->
                <div class="form-section">
                    <h3><i class="fas fa-clock"></i> Timeline & Budget</h3>

                    <div class="form-group">
                        <label>Preferred Turnaround Time <span class="required">*</span></label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="turnaround_express" name="turnaround_time" value="24–48 hours (Express)" required>
                                <label for="turnaround_express">24–48 hours (Express)</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="turnaround_3_5" name="turnaround_time" value="3–5 business days">
                                <label for="turnaround_3_5">3–5 business days</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="turnaround_1_week" name="turnaround_time" value="1 week">
                                <label for="turnaround_1_week">1 week</label>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="form-group">
                        <label for="budget_range">Budget Range (Optional)</label>
                        <input type="text" id="budget_range" name="budget_range" class="form-control" placeholder="e.g., KES 3,000-5,000">
                    </div> -->
                </div>

                <!-- Payment Section -->
                <div class="form-section payment-section">
                    <h3><i class="fas fa-credit-card"></i> Payment Information</h3>

                    <div class="pricing-note">
                        <i class="fas fa-info-circle"></i>
                        <strong>Pricing Note:</strong> CV and Cover Letter services are charged at 1.5x the base rate when a turnaround time is selected.
                    </div>

                    <div id="amountDisplay" class="amount-display" style="display: none;">
                        <div class="amount-breakdown">
                            <div class="base-amount">Base Amount: <span id="baseAmount">0</span> KES</div>
                            <div id="increaseInfo" class="increase-info" style="display: none;">
                                <i class="fas fa-times"></i> 1.5x Express Service Rate: <span id="increaseAmount">0</span> KES
                            </div>
                            <div class="total-amount">Total Amount: <span id="totalAmount">0</span> KES</div>
                        </div>
                    </div>
                    
                    <!-- Hidden field to store the calculated amount -->
                    <input type="hidden" id="calculatedAmount" name="calculated_amount" value="0">

                    <div class="form-group">
                        <label>Payment Method <span class="required">*</span></label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="mpesa" name="payment_method" value="MPESA" checked required>
                                <label for="mpesa"><i class="fas fa-mobile-alt"></i> M-Pesa</label>
                            </div>
                        </div>
                        <p class="text-muted">You will receive a payment request on your phone after submitting the form.</p>
                    </div>

                    <input type="hidden" name="initiate_payment" value="1">
                </div>

                <!-- Additional Notes -->
                <div class="form-section">
                    <h3><i class="fas fa-edit"></i> Additional Notes</h3>

                    <div class="form-group">
                        <label for="additional_notes">Special Instructions/Requirements</label>
                        <textarea id="additional_notes" name="additional_notes" class="form-control" placeholder="Please provide any additional information, specific requirements, or special instructions..."></textarea>
                    </div>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Update amount display when work experience is selected
        function updateAmount() {
            const select = document.getElementById('work_experience');
            const amountDisplay = document.getElementById('amountDisplay');
            const totalAmountSpan = document.getElementById('totalAmount');

            if (select.value) {
                // Extract amount from the selected option
                const matches = select.value.match(/KES (\d+,\d+)/);
                if (matches) {
                    let baseAmount = parseInt(matches[1].replace(/,/g, ''));
                    
                    // Update base amount display
                    document.getElementById('baseAmount').textContent = matches[1];
                    
                    // Check if turnaround time is selected
                    const turnaroundTime = document.querySelector('input[name="turnaround_time"]:checked');
                    let finalAmount = baseAmount;
                    const increaseInfo = document.getElementById('increaseInfo');
                    const increaseAmountSpan = document.getElementById('increaseAmount');
                    
                    if (turnaroundTime) {
                        // Apply 15% increase for CV services when turnaround time is selected
                        if (select.value.includes('CV') || select.value.includes('Cover Letter')) {
                            finalAmount = Math.round(baseAmount * 1.5); // 15% = 1.5x
                            
                            // Show increase info
                            const increaseAmount = finalAmount - baseAmount;
                            increaseAmountSpan.textContent = increaseAmount.toLocaleString();
                            increaseInfo.style.display = 'block';
                            
                            totalAmountSpan.textContent = finalAmount.toLocaleString();
                        } else {
                            totalAmountSpan.textContent = matches[1];
                            increaseInfo.style.display = 'none';
                        }
                    } else {
                        totalAmountSpan.textContent = matches[1];
                        increaseInfo.style.display = 'none';
                    }
                    
                    // Update the hidden field with the calculated amount
                    document.getElementById('calculatedAmount').value = finalAmount;
                    amountDisplay.style.display = 'block';
                    
                    // Debug logging
                    console.log('Service:', select.value);
                    console.log('Base Amount:', baseAmount);
                    console.log('Final Amount:', finalAmount);
                    console.log('Turnaround Time:', turnaroundTime ? turnaroundTime.value : 'None');
                }
            } else {
                amountDisplay.style.display = 'none';
                // Reset hidden field
                document.getElementById('calculatedAmount').value = '0';
            }
        }

        // File upload preview
        document.getElementById('current_cv').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameDisplay = document.getElementById('currentCvName');

            if (file) {
                fileNameDisplay.textContent = `Selected: ${file.name}`;
                fileNameDisplay.style.color = 'var(--success-color)';
            } else {
                fileNameDisplay.textContent = '';
            }
        });

        document.getElementById('job_description').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameDisplay = document.getElementById('jobDescName');

            if (file) {
                fileNameDisplay.textContent = `Selected: ${file.name}`;
                fileNameDisplay.style.color = 'var(--success-color)';
            } else {
                fileNameDisplay.textContent = '';
            }
        });

        // Form validation
        document.getElementById('cvForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--error-color)';
                    isValid = false;

                    // Scroll to the first invalid field
                    if (isValid === false) {
                        field.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        isValid = true; // Prevent multiple scrolls
                    }
                } else {
                    field.style.borderColor = 'var(--border-color)';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
            }
        });

        // Initialize form with any existing values (for edit scenarios)
        document.addEventListener('DOMContentLoaded', function() {
            // Trigger amount display if already selected
            updateAmount();

            // Add event listeners for turnaround time radio buttons
            const turnaroundRadios = document.querySelectorAll('input[name="turnaround_time"]');
            turnaroundRadios.forEach(radio => {
                radio.addEventListener('change', updateAmount);
            });

            // Add animation class to form sections
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.animationDelay = `${0.1 + (index * 0.1)}s`;
            });
        });
    </script>
</body>

</html>