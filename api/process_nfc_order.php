<?php
// scps1/api/process_nfc_order.php - Handles payment confirmation and sends email receipt.

// --- 1. SETUP PHPMAILER ---
// You must install PHPMailer. If you use Composer, run: composer require phpmailer/phpmailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Make sure the path to autoload.php is correct
require '../vendor/autoload.php';

session_start();
header('Content-Type: application/json');

require_once '../includes/db_connection.php';

if (!$link) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$password = $input['password'] ?? '';
$cartItems = $input['cart_items'] ?? [];

if (!isset($_SESSION['current_student_info']['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first to confirm your order.']);
    exit();
}

$student_id = $_SESSION['current_student_info']['student_id'];
$nfcId = $_SESSION['current_student_info']['nfc_id'];
$student_name = $_SESSION['current_student_info']['student_name'];

if (empty($password) || empty($cartItems)) {
    echo json_encode(['success' => false, 'message' => 'Missing password or cart data.']);
    exit();
}

// ... (Your existing code for fetching NFC data, verifying password, etc. remains the same) ...
// 1. Fetch NFC card data
$stmt = $link->prepare("SELECT nc.password_hash, nc.current_balance, nc.status FROM nfc_card nc WHERE nc.nfc_id = ? AND nc.student_id = ?");
if (!$stmt) {
    error_log("Payment Query Prepare Failed: " . $link->error);
    echo json_encode(['success' => false, 'message' => 'System error during payment verification.']);
    exit();
}
$stmt->bind_param("si", $nfcId, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Logged-in user data not found or NFC card mismatch. Please re-login.']);
    $stmt->close();
    unset($_SESSION['current_student_info']);
    exit();
}
$nfc_data = $result->fetch_assoc();
$stored_password_hash = $nfc_data['password_hash'];
$current_balance = $nfc_data['current_balance'];
$card_status = $nfc_data['status'];
$stmt->close();

if ($card_status !== 'Active') {
    echo json_encode(['success' => false, 'message' => 'Your NFC card is ' . $card_status . '. Cannot process order.']);
    exit();
}

// 2. Verify Password
if (!password_verify($password, $stored_password_hash)) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit();
}

// 3. Calculate Total Order Amount
// ... (Your code for calculating total_amount is perfect, no changes needed) ...
$total_amount = 0;
$food_ids = [];
foreach ($cartItems as $item) {
    $food_ids[] = $item['food_id'];
}
if (empty($food_ids)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}
$placeholders = implode(',', array_fill(0, count($food_ids), '?'));
$stmt = $link->prepare("SELECT food_id, name, price FROM food WHERE food_id IN ($placeholders)");
$types = str_repeat('i', count($food_ids));
$stmt->bind_param($types, ...$food_ids);
$stmt->execute();
$food_prices_result = $stmt->get_result();
$food_prices = [];
while ($row = $food_prices_result->fetch_assoc()) {
    $food_prices[$row['food_id']] = $row['price'];
}
$stmt->close();
foreach ($cartItems as $item) {
    if (isset($food_prices[$item['food_id']])) {
        $total_amount += $food_prices[$item['food_id']] * $item['quantity'];
    } else {
        echo json_encode(['success' => false, 'message' => 'One or more food items are invalid.']);
        exit();
    }
}


// 4. Check Balance
if ($current_balance < $total_amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance. Your current balance is Rs. ' . number_format($current_balance, 2) . '. Required: Rs. ' . number_format($total_amount, 2) . '.']);
    exit();
}

// 5. Process Transaction
$new_balance = $current_balance - $total_amount;
$link->begin_transaction();

try {
    // ... (Your existing DB updates: UPDATE nfc_card, INSERT transaction, INSERT transaction_item, INSERT activity_log) ...
    // This part is unchanged.
    $stmt = $link->prepare("UPDATE nfc_card SET current_balance = ? WHERE nfc_id = ?");
    $stmt->bind_param("ds", $new_balance, $nfcId);
    $stmt->execute();
    $stmt->close();
    $stmt = $link->prepare("INSERT INTO `transaction` (student_id, nfc_id, transaction_time, total_amount, status) VALUES (?, ?, NOW(), ?, 'success')");
    $stmt->bind_param("isd", $student_id, $nfcId, $total_amount);
    $stmt->execute();
    $txn_id = $link->insert_id;
    $stmt->close();
    $stmt_item = $link->prepare("INSERT INTO transaction_item (txn_id, food_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    foreach ($cartItems as $item) {
        $stmt_item->bind_param("iiid", $txn_id, $item['food_id'], $item['quantity'], $food_prices[$item['food_id']]);
        $stmt_item->execute();
    }
    $stmt_item->close();
    $activity_description = "Student $student_name (ID: $student_id) purchased items via NFC. Total: Rs. " . number_format($total_amount, 2);
    $stmt_log = $link->prepare("INSERT INTO activity_log (timestamp, activity_type, description, user_id, related_id) VALUES (NOW(), 'Order', ?, ?, ?)");
    $stmt_log->bind_param("sii", $activity_description, $student_id, $txn_id);
    $stmt_log->execute();
    $stmt_log->close();

    $link->commit();

    // --- 2. SEND EMAIL RECEIPT AFTER SUCCESSFUL TRANSACTION ---
    // Fetch parent's email
    $stmt_email = $link->prepare("SELECT parent_email FROM student WHERE student_id = ?");
    $stmt_email->bind_param("i", $student_id);
    $stmt_email->execute();
    $email_result = $stmt_email->get_result();
    $student_data = $email_result->fetch_assoc();
    $parent_email = $student_data['parent_email'] ?? null;
    $stmt_email->close();

    if ($parent_email) {
        // Construct email body
        $items_html = '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                        <thead style="background-color: #f2f2f2;"><tr><th>Item</th><th>Quantity</th><th>Price</th></tr></thead><tbody>';
        foreach ($cartItems as $item) {
            $item_name = htmlspecialchars($item['food_name'] ?? 'Unknown Item');
            $item_total = $food_prices[$item['food_id']] * $item['quantity'];
            $items_html .= "<tr><td>{$item_name}</td><td style='text-align:center;'>{$item['quantity']}</td><td style='text-align:right;'>Rs. " . number_format($item_total, 2) . "</td></tr>";
        }
        $items_html .= '</tbody></table>';

        $email_body = "
            <h1 style='color: #0056b3;'>Canteen Receipt</h1>
            <p>Dear Parent,</p>
            <p>This is a notification for a purchase made by your child, <strong>" . htmlspecialchars($student_name) . "</strong>, at the United Technical Khaja Ghar.</p>
            <h3>Order Details (Transaction ID: {$txn_id})</h3>
            {$items_html}
            <p style='font-size: 1.2em; font-weight: bold; text-align: right;'>Total: Rs. " . number_format($total_amount, 2) . "</p>
            <p style='font-size: 1.2em; font-weight: bold; text-align: right;'>New Card Balance: Rs. " . number_format($new_balance, 2) . "</p>
            <hr><p style='font-size: 0.9em; color: #6c757d;'>This is an automated message. Please do not reply.</p>";

        $mail = new PHPMailer(true);
        try {
            // No need for debug output in production, so comment these out
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            // $mail->Debugoutput = 'error_log';

            // --- 3. CONFIGURE YOUR EMAIL SETTINGS HERE ---
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'poudelaman4@gmail.com';
            $mail->Password   = 'mhvulrfdlpyiiite'; // No spaces
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            //Recipients
            $mail->setFrom('poudelaman4@gmail.com', 'United Technical Khaja Ghar');
            $mail->addAddress($parent_email);

            // Add a Reply-To header to make it seem more interactive/legitimate
            // Use an email address that you actually monitor for replies
            $mail->addReplyTo('poudelaman4@gmail.com', 'Canteen Support'); // <--- ADDED THIS LINE

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Canteen Purchase Receipt for ' . htmlspecialchars($student_name);
            $mail->Body    = $email_body;
            $mail->AltBody = 'This is the plain text version for non-HTML email clients. Your child, ' . htmlspecialchars($student_name) . ', made a purchase at United Technical Khaja Ghar. Total: Rs. ' . number_format($total_amount, 2) . '. New Card Balance: Rs. ' . number_format($new_balance, 2) . '.';

            $mail->send();
        } catch (Exception $e) {
            // If email fails, don't stop the script. Just log the error.
            error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
    // --- END OF EMAIL LOGIC ---

    $_SESSION['current_student_info']['student_balance'] = $new_balance;

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'new_balance' => $new_balance,
        'student_name' => $student_name,
        'transaction_id' => $txn_id
    ]);

} catch (Exception $e) {
    $link->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

mysqli_close($link);
?>
