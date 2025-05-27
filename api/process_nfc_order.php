<?php
// scps1/api/process_nfc_order.php - Handles payment confirmation (password only) after a student is logged in.

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

// Ensure user is logged in before processing payment
if (!isset($_SESSION['current_student_info']['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first to confirm your order.']);
    exit();
}

// Retrieve necessary student info from session
$student_id = $_SESSION['current_student_info']['student_id'];
$nfcId = $_SESSION['current_student_info']['nfc_id']; // Retrieve NFC ID from session
$student_name = $_SESSION['current_student_info']['student_name']; // Retrieve student name from session

if (empty($password) || empty($cartItems)) {
    // This check is now more specific, as NFC ID is from session
    echo json_encode(['success' => false, 'message' => 'Missing password or cart data.']);
    exit();
}

// 1. Fetch NFC card data for the logged-in student using NFC ID from session
$stmt = $link->prepare("SELECT nc.password_hash, nc.current_balance, nc.status FROM nfc_card nc WHERE nc.nfc_id = ? AND nc.student_id = ?");
if (!$stmt) {
    error_log("Payment Query Prepare Failed: " . $link->error);
    echo json_encode(['success' => false, 'message' => 'System error during payment verification.']);
    exit();
}
$stmt->bind_param("si", $nfcId, $student_id); // Use NFC ID from session
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // This could happen if session data is stale or manipulated
    echo json_encode(['success' => false, 'message' => 'Logged-in user data not found or NFC card mismatch. Please re-login.']);
    $stmt->close();
    // Clear session to force re-login
    unset($_SESSION['current_student_info']);
    exit();
}

$nfc_data = $result->fetch_assoc();
$stored_password_hash = $nfc_data['password_hash'];
$current_balance = $nfc_data['current_balance'];
$card_status = $nfc_data['status'];
$stmt->close();

// Check card status
if ($card_status !== 'Active') { // Ensure 'Active' matches enum in DB
    echo json_encode(['success' => false, 'message' => 'Your NFC card is ' . $card_status . '. Cannot process order.']);
    exit();
}

// 2. Verify Password
if (!password_verify($password, $stored_password_hash)) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit();
}

// 3. Calculate Total Order Amount (from cart items)
$total_amount = 0;
$food_ids = [];
foreach ($cartItems as $item) {
    $food_ids[] = $item['food_id'];
}

if (empty($food_ids)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

// Fetch prices from DB to prevent tampering from frontend
$placeholders = implode(',', array_fill(0, count($food_ids), '?'));
$stmt = $link->prepare("SELECT food_id, name, price FROM food WHERE food_id IN ($placeholders)");
if (!$stmt) {
    error_log("Food Price Query Prepare Failed: " . $link->error);
    echo json_encode(['success' => false, 'message' => 'System error during order calculation.']);
    exit();
}
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
        echo json_encode(['success' => false, 'message' => 'One or more food items in your cart are invalid or no longer available.']);
        exit();
    }
}

// 4. Check Balance
if ($current_balance < $total_amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance. Your current balance is Rs. ' . number_format($current_balance, 2) . '. Required: Rs. ' . number_format($total_amount, 2) . '.']);
    exit();
}

// 5. Process Transaction (deduct balance, record transaction)
$new_balance = $current_balance - $total_amount;

$link->begin_transaction();

try {
    // Deduct balance from NFC card
    $stmt = $link->prepare("UPDATE nfc_card SET current_balance = ? WHERE nfc_id = ?");
    if (!$stmt) {
        throw new Exception("Update balance prepare failed: " . $link->error);
    }
    $stmt->bind_param("ds", $new_balance, $nfcId);
    $stmt->execute();
    $stmt->close();

    // Record main transaction
    $stmt = $link->prepare("INSERT INTO `transaction` (student_id, nfc_id, transaction_time, total_amount, status) VALUES (?, ?, NOW(), ?, 'success')");
    if (!$stmt) {
        throw new Exception("Insert transaction prepare failed: " . $link->error);
    }
    $stmt->bind_param("isd", $student_id, $nfcId, $total_amount);
    $stmt->execute();
    $txn_id = $link->insert_id;
    $stmt->close();

    // Record transaction items
    $stmt_item = $link->prepare("INSERT INTO transaction_item (txn_id, food_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    if (!$stmt_item) {
        throw new Exception("Insert transaction item prepare failed: " . $link->error);
    }
    foreach ($cartItems as $item) {
        $food_id = $item['food_id'];
        $quantity = $item['quantity'];
        $unit_price = $food_prices[$food_id];
        $stmt_item->bind_param("iiid", $txn_id, $food_id, $quantity, $unit_price);
        $stmt_item->execute();
    }
    $stmt_item->close();

    // Record activity log
    $activity_description = "Student $student_name (ID: $student_id) purchased items via NFC. Total: Rs. " . number_format($total_amount, 2);
    $stmt_log = $link->prepare("INSERT INTO activity_log (timestamp, activity_type, description, user_id, related_id) VALUES (NOW(), 'Order', ?, ?, ?)");
    if (!$stmt_log) {
        throw new Exception("Activity log prepare failed: " . $link->error);
    }
    $stmt_log->bind_param("sii", $activity_description, $student_id, $txn_id);
    $stmt_log->execute();
    $stmt_log->close();

    $link->commit();

    // Update student info in session with new balance
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
