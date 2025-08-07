<?php
require_once 'config.php';

$message = '';
$messageType = '';

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

        if (isset($_FILES['current_cv']) && $_FILES['current_cv']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $current_cv_filename = time() . '_' . $_FILES['current_cv']['name'];
            move_uploaded_file($_FILES['current_cv']['tmp_name'], $upload_dir . $current_cv_filename);
        }

        if (isset($_FILES['job_description']) && $_FILES['job_description']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $job_description_filename = time() . '_' . $_FILES['job_description']['name'];
            move_uploaded_file($_FILES['job_description']['tmp_name'], $upload_dir . $job_description_filename);
        }

        // Extract amount from work_experience selection
        $work_experience = $_POST['work_experience'];
        $amount = 0;
        if (preg_match('/KES (\d+,\d+)/', $work_experience, $matches)) {
            $amount = (int)str_replace(',', '', $matches[1]);
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
            ':full_name' => $_POST['full_name'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':preferred_contact' => $_POST['preferred_contact'],
            ':current_location' => $_POST['current_location'],
            ':education_level' => $_POST['education_level'],
            ':field_of_study' => $_POST['field_of_study'],
            ':current_job_title' => $_POST['current_job_title'],
            ':work_experience' => $work_experience,
            ':target_job_role' => $_POST['target_job_role'],
            ':cv_service_required' => $_POST['cv_service_required'],
            ':current_cv_filename' => $current_cv_filename,
            ':job_description_filename' => $job_description_filename,
            ':turnaround_time' => $_POST['turnaround_time'],
            ':budget_range' => $_POST['budget_range'],
            ':additional_notes' => $_POST['additional_notes'],
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
    } catch (PDOException $e) {
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-container {
            padding: 40px;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 15px;
            border-left: 5px solid #667eea;
        }

        .form-section h3 {
            color: #333;
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
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
            transition: all 0.3s ease;
        }

        .checkbox-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .checkbox-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
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
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: #e8f0fe;
            border-color: #4a90e2;
        }

        .payment-section {
            background: #f0f8ff;
            border-left: 5px solid #4a90e2;
        }

        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 15px 0;
            padding: 10px;
            background: #e8f4fc;
            border-radius: 8px;
            text-align: center;
        }

        .pay-now-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }

        .pay-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.3);
        }

        .payment-method {
            margin-top: 20px;
        }

        .payment-method label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .payment-method input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #4a90e2;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .required {
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-alt"></i> CV Form Submission</h1>
            <p>Complete the form below to get started with your professional CV services</p>
        </div>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>

                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label>Preferred Contact Method</label>
                        <select id="preferred_contact" name="preferred_contact">
                            <option value="">Select Preferred Contact Method</option>
                            <option value="Email">Email</option>
                            <option value="Phone">Phone</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="current_location">Current Location (City & Country)</label>
                        <input type="text" id="current_location" name="current_location" placeholder="e.g., London, UK">
                    </div>
                </div>

                <!-- Education Background -->
                <div class="form-section">
                    <h3><i class="fas fa-graduation-cap"></i> Education Background</h3>

                    <div class="form-group">
                        <label for="education_level">Highest Level of Education Achieved</label>
                        <select id="education_level" name="education_level">
                            <option value="">Select Education Level</option>
                            <option value="High School">High School</option>
                            <option value="Associate's Degree">Associate's Degree</option>
                            <option value="Bachelor's Degree">Bachelor's Degree</option>
                            <option value="Master's Degree">Master's Degree</option>
                            <option value="PhD">PhD</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="field_of_study">Field of Study</label>
                        <input type="text" id="field_of_study" name="field_of_study" placeholder="e.g., Computer Science, Business Administration">
                    </div>
                </div>

                <!-- Career Information -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Career Information</h3>

                    <div class="form-group">
                        <label for="current_job_title">Current Job Title (if any)</label>
                        <input type="text" id="current_job_title" name="current_job_title" placeholder="e.g., Software Developer">
                    </div>

                    <div class="form-group">
                        <label>Years of Work Experience</label>
                        <select id="work_experience" name="work_experience" required onchange="updateAmount()">
                            <option value="">Select Work Experience</option>
                            <option value="0–5 years KES 3,000">0–5 years KES 3,000</option>
                            <option value="6 – 15 years KES 6,000">6 – 15 years KES 6,000</option>
                            <option value="Above 15 years KES 8,000">Above 15 years KES 8,000</option>
                            <option value="Stand Alone cover letter KES 1,500 (Without CV)">Stand Alone cover letter KES 1,500 (Without CV)</option>
                            <option value="Standard Fees KES 4,000">Standard Fees KES 4,000</option>
                            <option value="Interview Preparation (1Hr Zoom Meeting) KES 4,000">Interview Preparation (1Hr Zoom Meeting) KES 4,000</option>
                            <option value="Career Coaching (1Hr 30 Mins Zoom Meeting) KES 6,000">Career Coaching (1Hr 30 Mins Zoom Meeting) KES 6,000</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="target_job_role">Target Job Role / Industry</label>
                        <input type="text" id="target_job_role" name="target_job_role" placeholder="e.g., Senior Software Engineer, Marketing Manager">
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="form-section payment-section">
                    <h3><i class="fas fa-credit-card"></i> Payment Information</h3>

                    <div id="amountDisplay" class="amount-display" style="display: none;">
                        Total Amount: <span id="totalAmount">0</span> KES
                    </div>

                    <div class="payment-method">
                        <label>
                            <input type="radio" name="payment_method" value="MPESA" checked>
                            <i class="fas fa-mobile-alt"></i> M-Pesa
                        </label>
                        <p>You will receive a payment request on your phone after submitting the form.</p>
                    </div>

                    <input type="hidden" name="initiate_payment" value="1">
                    <button type="submit" class="pay-now-btn" id="payNowBtn" style="display: none;">
                        <i class="fas fa-lock"></i> Pay Now
                    </button>
                </div>

                <!-- CV Service Required -->
                <div class="form-section">
                    <h3><i class="fas fa-tools"></i> CV Service Required (select all that apply)</h3>

                    <select name="cv_service_required" id="cv_service_required">
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

                <!-- Supporting Documents -->
                <div class="form-section">
                    <h3><i class="fas fa-file-upload"></i> Supporting Documents</h3>

                    <div class="form-group">
                        <label for="current_cv">Upload Your Current CV (if any)</label>
                        <div class="file-upload">
                            <input type="file" id="current_cv" name="current_cv" accept=".pdf,.doc,.docx">
                            <label for="current_cv" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i> Choose file (PDF, DOC, DOCX)
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="job_description">Upload Job Description (if targeting a specific job)</label>
                        <div class="file-upload">
                            <input type="file" id="job_description" name="job_description" accept=".pdf,.doc,.docx">
                            <label for="job_description" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i> Choose file (PDF, DOC, DOCX)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Timeline & Budget -->
                <div class="form-section">
                    <h3><i class="fas fa-clock"></i> Timeline & Budget</h3>

                    <div class="form-group">
                        <label>Preferred Turnaround Time</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="turnaround_express" name="turnaround_time" value="24–48 hours (Express)">
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

                    <div class="form-group">
                        <label for="budget_range">Budget Range (Optional)</label>
                        <input type="text" id="budget_range" name="budget_range" placeholder="e.g., $100-200, £150-300">
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="form-section">
                    <h3><i class="fas fa-sticky-note"></i> Additional Notes/Instructions</h3>

                    <div class="form-group">
                        <label for="additional_notes">Additional Notes/Instructions</label>
                        <textarea id="additional_notes" name="additional_notes" rows="5" placeholder="Please provide any additional information, specific requirements, or special instructions..."></textarea>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit CV Form
                </button>
            </form>
        </div>
    </div>

    <script>
        // Update amount display when work experience is selected
        function updateAmount() {
            const select = document.getElementById('work_experience');
            const amountDisplay = document.getElementById('amountDisplay');
            const payNowBtn = document.getElementById('payNowBtn');
            const totalAmountSpan = document.getElementById('totalAmount');

            if (select.value) {
                // Extract amount from the selected option
                const matches = select.value.match(/KES (\d+,\d+)/);
                if (matches) {
                    totalAmountSpan.textContent = matches[1];
                    amountDisplay.style.display = 'block';
                    payNowBtn.style.display = 'block';
                }
            } else {
                amountDisplay.style.display = 'none';
                payNowBtn.style.display = 'none';
            }
        }

        // File upload preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const label = this.nextElementSibling;
                    label.innerHTML = `<i class="fas fa-check"></i> ${file.name}`;
                    label.style.background = '#d4edda';
                    label.style.color = '#155724';
                }
            });
        });

        // Form validation
        document.getElementById('cvForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e1e5e9';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
            }
        });
    </script>
</body>

</html>