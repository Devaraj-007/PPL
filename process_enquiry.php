<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "laptop_enquiries";

// Email configuration
$smtp_host = "smtp.gmail.com";
$smtp_username = "devarajwebdev@gmail.com";
$smtp_password = "nhbd nlgg sfjr zzjt";
$admin_email = "hr@7siq.com";
$website_name = "Pro premium Laptops";
$from_email = "noreply@herbalproducts.com";
$website_url = "https://7siq.com/"; // Update with your actual website

try {
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get POST data with validation
    $form_type = $_POST['form_type'] ?? 'unknown';
    
    $required_fields = ['name', 'email', 'phone'];
    if ($form_type === 'main_contact') {
        $required_fields[] = 'service';
        $required_fields[] = 'message';
    } else {
        $required_fields[] = 'message';
    }

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Please fill in all required fields. Missing: $field");
        }
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $service = isset($_POST['service']) ? trim($_POST['service']) : 'General Enquiry';
    $message = trim($_POST['message']);
    $submission_date = date('Y-m-d H:i:s');
    $formatted_date = date('F j, Y, g:i a');

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }

    // Validate phone number (basic validation)
    if (!preg_match('/^[0-9+\-\s()]{10,}$/', $phone)) {
        throw new Exception('Please enter a valid phone number');
    }

    // Insert into database
    $sql = "INSERT INTO contact_enquiries (name, email, phone, service_type, message, form_type, submission_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("sssssss", $name, $email, $phone, $service, $message, $form_type, $submission_date);

    if (!$stmt->execute()) {
        throw new Exception('Error submitting enquiry: ' . $stmt->error);
    }

    $enquiry_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    // Send enhanced emails
    $email_status = '';
    try {
        $email_result = sendEnhancedEmails(
            $smtp_host,
            $smtp_username,
            $smtp_password,
            $admin_email,
            $name,
            $email,
            $phone,
            $service,
            $message,
            $formatted_date,
            $enquiry_id,
            $website_name,
            $from_email,
            $website_url,
            $form_type
        );

        if ($email_result) {
            $email_status = ' Confirmation email sent.';
        } else {
            $email_status = ' Email delivery failed but enquiry was saved.';
        }
    } catch (Exception $email_error) {
        $email_status = ' Email delivery failed but enquiry was saved.';
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your enquiry! We will get back to you soon.' . $email_status,
        'enquiry_id' => $enquiry_id
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sendEnhancedEmails($smtp_host, $smtp_user, $smtp_pass, $admin_email, $name, $email, $phone, $service, $message, $date, $enquiry_id, $website_name, $from_email, $website_url, $form_type)
{
    $mail_admin = new PHPMailer(true);

    try {
        // Server settings
        $mail_admin->isSMTP();
        $mail_admin->Host = $smtp_host;
        $mail_admin->SMTPAuth = true;
        $mail_admin->Username = $smtp_user;
        $mail_admin->Password = $smtp_pass;
        $mail_admin->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail_admin->Port = 587;
        $mail_admin->CharSet = 'UTF-8';

        // Recipients
        $mail_admin->setFrom($from_email, $website_name);
        $mail_admin->addAddress($admin_email);
        $mail_admin->addReplyTo($email, $name);

        // Content
        $mail_admin->isHTML(true);
        $mail_admin->Subject = "💻 New Laptop Enquiry - $website_name";
        $mail_admin->Body = getAdminEmailTemplate($name, $email, $phone, $service, $message, $date, $enquiry_id, $website_name, $website_url, $form_type);
        $mail_admin->AltBody = getAdminPlainText($name, $email, $phone, $service, $message, $date, $enquiry_id);

        $mail_admin->send();

        // Send confirmation to user
        $mail_user = new PHPMailer(true);

        $mail_user->isSMTP();
        $mail_user->Host = $smtp_host;
        $mail_user->SMTPAuth = true;
        $mail_user->Username = $smtp_user;
        $mail_user->Password = $smtp_pass;
        $mail_user->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail_user->Port = 587;
        $mail_user->CharSet = 'UTF-8';

        $mail_user->setFrom($from_email, $website_name);
        $mail_user->addAddress($email);
        $mail_user->addReplyTo($admin_email, $website_name);

        $mail_user->isHTML(true);
        $mail_user->Subject = "✅ Thank you for your enquiry - $website_name";
        $mail_user->Body = getUserEmailTemplate($name, $service, $date, $website_name, $website_url, $admin_email);
        $mail_user->AltBody = getUserPlainText($name, $service, $date, $website_name, $admin_email);

        $mail_user->send();

        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

function getAdminEmailTemplate($name, $email, $phone, $service, $message, $date, $enquiry_id, $website_name, $website_url, $form_type)
{
    $form_source = $form_type === 'main_contact' ? 'Main Contact Form' : 'Popup Contact Form';
    
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>New Laptop Enquiry</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f6f9fc; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { font-size: 24px; margin-bottom: 10px; }
            .badge { background: rgba(255, 255, 255, 0.2); padding: 5px 15px; border-radius: 20px; font-size: 12px; margin-top: 10px; display: inline-block; }
            .content { padding: 30px; }
            .section { margin-bottom: 25px; }
            .section-title { font-size: 18px; font-weight: 600; color: #667eea; margin-bottom: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; }
            .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
            .detail-item { background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #667eea; }
            .detail-label { font-weight: 600; color: #555; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
            .detail-value { font-size: 15px; color: #333; margin-top: 5px; }
            .message-box { background: #fff9e6; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 10px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .action-btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; margin: 10px 5px; font-weight: 600; }
            .priority { background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 4px; font-size: 12px; display: inline-block; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>💻 New Laptop Enquiry</h1>
                <div class="badge">Enquiry ID: #' . $enquiry_id . ' | Source: ' . $form_source . '</div>
            </div>
            
            <div class="content">
                <div class="section">
                    <div class="section-title">📋 Enquiry Summary</div>
                    <div class="priority">⏰ Please respond within 24 hours</div>
                </div>
                
                <div class="section">
                    <div class="section-title">👤 Customer Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value">' . htmlspecialchars($name) . '</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value">
                                <a href="mailto:' . htmlspecialchars($email) . '" style="color:#667eea;text-decoration:none;">' . htmlspecialchars($email) . '</a>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value">
                                <a href="tel:' . htmlspecialchars($phone) . '" style="color:#28a745;text-decoration:none;">' . htmlspecialchars($phone) . '</a>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Service Type</div>
                            <div class="detail-value">' . htmlspecialchars($service) . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">💬 Customer Message</div>
                    <div class="message-box">
                        ' . nl2br(htmlspecialchars($message)) . '
                    </div>
                </div>
                
                <div class="section">
                    <div class="detail-item" style="grid-column: span 2;">
                        <div class="detail-label">Enquiry Date & Time</div>
                        <div class="detail-value">' . $date . '</div>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="mailto:' . htmlspecialchars($email) . '?subject=Re: Your Laptop Enquiry&body=Dear%20' . urlencode($name) . '%2C%0A%0AThank%20you%20for%20your%20enquiry...\" class="action-btn">📧 Reply to Customer</a>
                    <a href="tel:' . htmlspecialchars($phone) . '" class="action-btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">📞 Call Customer</a>
                </div>
            </div>
            
            <div class="footer">
                <p>This enquiry was submitted through the ' . $website_name . ' website<br>
                <a href="' . $website_url . '" style="color: #667eea; text-decoration: none;">' . $website_url . '</a></p>
                <p style="margin-top: 10px; color: #999;">&copy; ' . date('Y') . ' ' . $website_name . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
}

function getUserEmailTemplate($name, $service, $date, $website_name, $website_url, $admin_email)
{
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Thank You for Your Enquiry - ' . $website_name . '</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
            
            * { 
                margin: 0; 
                padding: 0; 
                box-sizing: border-box; 
            }
            
            body { 
                font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.7; 
                color: #2d3748; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 20px; 
                overflow: hidden; 
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
            }
            
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 50px 30px; 
                text-align: center; 
                position: relative;
                overflow: hidden;
            }
            
            .header::before {
                content: "";
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
                background-size: 20px 20px;
                animation: float 20s infinite linear;
            }
            
            @keyframes float {
                0% { transform: translate(0, 0) rotate(0deg); }
                100% { transform: translate(-20px, -20px) rotate(360deg); }
            }
            
            .header-content {
                position: relative;
                z-index: 2;
            }
            
            .header h1 { 
                font-size: 2.5rem; 
                margin-bottom: 15px; 
                font-weight: 700;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .header p { 
                font-size: 1.2rem; 
                opacity: 0.9; 
                font-weight: 300;
            }
            
            .confirmation-badge {
                background: rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(10px);
                padding: 12px 25px;
                border-radius: 50px;
                font-size: 1rem;
                margin-top: 20px;
                display: inline-block;
                border: 1px solid rgba(255,255,255,0.3);
            }
            
            .content { 
                padding: 50px 40px; 
            }
            
            .welcome-section {
                text-align: center;
                margin-bottom: 40px;
            }
            
            .welcome-section h2 {
                font-size: 2rem;
                color: #2d3748;
                margin-bottom: 15px;
                font-weight: 600;
            }
            
            .welcome-section p {
                font-size: 1.1rem;
                color: #4a5568;
                line-height: 1.8;
            }
            
            .enquiry-card {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border-radius: 15px;
                padding: 30px;
                margin: 30px 0;
                border: 1px solid #e2e8f0;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            }
            
            .enquiry-card h3 {
                color: #667eea;
                font-size: 1.4rem;
                margin-bottom: 25px;
                text-align: center;
                font-weight: 600;
            }
            
            .enquiry-details {
                display: grid;
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: white;
                border-radius: 10px;
                border-left: 4px solid #667eea;
                transition: transform 0.2s ease;
            }
            
            .detail-row:hover {
                transform: translateX(5px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .detail-label {
                font-weight: 500;
                color: #4a5568;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .detail-value {
                font-weight: 600;
                color: #2d3748;
                font-size: 1.1rem;
            }
            
            .process-timeline {
                margin: 40px 0;
            }
            
            .process-timeline h3 {
                text-align: center;
                color: #2d3748;
                font-size: 1.5rem;
                margin-bottom: 30px;
                font-weight: 600;
            }
            
            .timeline {
                position: relative;
                padding-left: 30px;
            }
            
            .timeline::before {
                content: "";
                position: absolute;
                left: 15px;
                top: 0;
                bottom: 0;
                width: 2px;
                background: linear-gradient(to bottom, #667eea, #764ba2);
            }
            
            .timeline-step {
                position: relative;
                margin-bottom: 30px;
                padding-left: 30px;
            }
            
            .timeline-step::before {
                content: "";
                position: absolute;
                left: -8px;
                top: 5px;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                background: #667eea;
                border: 3px solid white;
                box-shadow: 0 0 0 3px #667eea;
            }
            
            .step-content h4 {
                color: #2d3748;
                margin-bottom: 8px;
                font-weight: 600;
            }
            
            .step-content p {
                color: #718096;
                line-height: 1.6;
            }
            
            .assurance-section {
                background: linear-gradient(135deg, #c3dafe 0%, #a3bffa 100%);
                padding: 25px;
                border-radius: 15px;
                text-align: center;
                margin: 30px 0;
                border: 1px solid #7f9cf5;
            }
            
            .assurance-section h4 {
                color: #2c5282;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .contact-section {
                background: #f7fafc;
                padding: 30px;
                border-radius: 15px;
                text-align: center;
                border: 1px solid #e2e8f0;
            }
            
            .contact-section h4 {
                color: #2d3748;
                margin-bottom: 20px;
                font-weight: 600;
            }
            
            .contact-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            
            .contact-item {
                padding: 15px;
                background: white;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
                transition: transform 0.2s ease;
            }
            
            .contact-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .contact-item a {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
            }
            
            .contact-item a:hover {
                color: #5a6fd8;
                text-decoration: underline;
            }
            
            .footer { 
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); 
                color: #a0aec0; 
                padding: 40px 30px; 
                text-align: center; 
            }
            
            .footer-logo {
                font-size: 1.5rem;
                font-weight: 700;
                color: white;
                margin-bottom: 15px;
            }
            
            .footer-links {
                margin: 20px 0;
            }
            
            .footer-links a {
                color: #a0aec0;
                text-decoration: none;
                margin: 0 15px;
                transition: color 0.3s ease;
            }
            
            .footer-links a:hover {
                color: white;
            }
            
            .social-links {
                margin: 25px 0;
            }
            
            .social-links a {
                display: inline-block;
                width: 40px;
                height: 40px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
                margin: 0 8px;
                text-align: center;
                line-height: 40px;
                color: white;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            
            .social-links a:hover {
                background: #667eea;
                transform: translateY(-2px);
            }
            
            @media (max-width: 600px) {
                .content { padding: 30px 20px; }
                .header { padding: 40px 20px; }
                .header h1 { font-size: 2rem; }
                .contact-info { grid-template-columns: 1fr; }
                .detail-row { flex-direction: column; align-items: flex-start; gap: 10px; }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header Section -->
            <div class="header">
                <div class="header-content">
                    <h1>Thank You, ' . htmlspecialchars($name) . '! 🎉</h1>
                    <p>Your laptop enquiry has been received successfully</p>
                    <div class="confirmation-badge">
                        ✅ Confirmation #' . time() . '
                    </div>
                </div>  
            </div>
            
            <!-- Main Content -->
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h2>Welcome to ' . $website_name . '! 💻</h2>
                    <p>Thank you for your interest in our premium laptop services. We\'re excited to help you find the perfect laptop solution.</p>
                </div>
                
                <!-- Enquiry Summary Card -->
                <div class="enquiry-card">
                    <h3>📋 Your Enquiry Summary</h3>
                    <div class="enquiry-details">
                        <div class="detail-row">
                            <span class="detail-label">🛠️ Service Type</span>
                            <span class="detail-value">' . htmlspecialchars($service) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">📅 Enquiry Date</span>
                            <span class="detail-value">' . $date . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">⏱️ Expected Response</span>
                            <span class="detail-value">Within 24 hours</span>
                        </div>
                    </div>
                </div>
                
                <!-- Process Timeline -->
                <div class="process-timeline">
                    <h3>🚀 What Happens Next?</h3>
                    <div class="timeline">
                        <div class="timeline-step">
                            <div class="step-content">
                                <h4>Expert Review</h4>
                                <p>Our laptop specialists are analyzing your requirements to provide the best possible solution.</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="step-content">
                                <h4>Personalized Response</h4>
                                <p>We\'ll prepare detailed information, quotes, or solutions tailored to your specific needs.</p>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <div class="step-content">
                                <h4>Direct Contact</h4>
                                <p>Our team will reach out to discuss details, answer questions, and provide expert guidance.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quality Assurance -->
                <div class="assurance-section">
                    <h4>🌟 Premium Service Guaranteed</h4>
                    <p>All our laptops are premium quality, thoroughly tested, and come with comprehensive warranty support.</p>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-section">
                    <h4>💬 Need Immediate Assistance?</h4>
                    <p>Our customer support team is here to help you every step of the way.</p>
                    
                    <div class="contact-info">
                        <div class="contact-item">
                            <strong>📧 Email</strong><br>
                            <a href="mailto:' . $admin_email . '">' . $admin_email . '</a>
                        </div>
                        <div class="contact-item">
                            <strong>📞 Phone</strong><br>
                            <a href="tel:+918122038045">+91 80561 79393</a>
                        </div>
                        <div class="contact-item">
                            <strong>🕒 Business Hours</strong><br>
                            Mon-Sat: 9AM - 7PM
                        </div>
                        <div class="contact-item">
                            <strong>📍 Address</strong><br>
                            Chennai, Tamil Nadu
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div class="footer-logo">' . $website_name . '</div>
                <p>Premium Laptops • Quality Service • Trusted Support</p>
                
                <div class="footer-links">
                    <a href="' . $website_url . '">Home</a>
                    <a href="' . $website_url . '/products">Our Products</a>
                    <a href="' . $website_url . '/services">Services</a>
                    <a href="' . $website_url . '/contact">Contact</a>
                </div>
                
                <div class="social-links">
                    <a href="#" title="Facebook">📘</a>
                    <a href="#" title="Instagram">📷</a>
                    <a href="#" title="Twitter">🐦</a>
                    <a href="#" title="LinkedIn">💼</a>
                </div>
                
                <p style="margin-top: 20px; font-size: 0.9rem; color: #718096;">
                    &copy; ' . date('Y') . ' ' . $website_name . '. All rights reserved.<br>
                    <a href="' . $website_url . '" style="color: #a0aec0; text-decoration: none;">' . $website_url . '</a>
                </p>
            </div>
        </div>
    </body>
    </html>';
}

// Plain text versions
function getAdminPlainText($name, $email, $phone, $service, $message, $date, $enquiry_id)
{
    return "
NEW LAPTOP ENQUIRY - Enquiry ID: #$enquiry_id

CUSTOMER INFORMATION:
Name: $name
Email: $email
Phone: $phone
Service Type: $service

MESSAGE:
$message

Date: $date

Please respond to this enquiry within 24 hours.
";
}

function getUserPlainText($name, $service, $date, $website_name, $admin_email)
{
    return "
Dear $name,

Thank you for your enquiry with $website_name!

ENQUIRY SUMMARY:
Service Type: $service
Enquiry Date: $date

Our team will review your requirements and contact you within 24 hours with our best solution.

If you have any urgent questions, please contact us at $admin_email.

Best regards,
The $website_name Team
";
}
?>