<?php
// scps1/index.php - Main student menu page for NFC-based ordering

// Start the session to manage the cart
session_start();

// Include database connection
require_once './includes/db_connection.php';

// Check database connection
if (!$link || mysqli_connect_errno()) {
    error_log("index.php: Database connection failed: " . mysqli_connect_error());
    $db_connection_error = true;
} else {
    $db_connection_error = false;
}

// Initialize cart in session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Canteen - Order Food</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --accent-color: #007BFF; --accent-hover: #0056b3;
            --success-color: #28A745; --success-hover: #1e7e34;
            --danger-color: #DC3545; --danger-hover: #c82333;
            --text-primary: #212529; --text-secondary: #6C757D;
            --bg-main: #F8F9FA; --bg-card: #FFFFFF;
            --border-color: #DEE2E6; --light-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-main); color: var(--text-primary); }
        .header-title { color: var(--accent-color); font-weight: 800; }
        .section-title { color: var(--text-primary); font-weight: 700; }
        .btn { padding: 0.65rem 1.25rem; border-radius: 8px; font-weight: 600; transition: all 0.2s; cursor: pointer; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-success-custom { background-color: var(--success-color); color: white; }
        .btn-success-custom:hover:not(:disabled) { background-color: var(--success-hover); transform: translateY(-1px); }
        .btn-danger-custom { background-color: var(--danger-color); color: white; }
        .btn-danger-custom:hover:not(:disabled) { background-color: var(--danger-hover); transform: translateY(-1px); }
        .btn-accent { background-color: var(--accent-color); color: white; }
        .btn-accent:hover:not(:disabled) { background-color: var(--accent-hover); transform: translateY(-1px); }
        .food-card { background-color: var(--bg-card); border-radius: 12px; overflow: hidden; transition: all 0.3s; box-shadow: var(--light-shadow); border: 1px solid var(--border-color); }
        .food-card:hover { transform: translateY(-4px); border-color: var(--accent-color); }
        .food-card img { width: 100%; height: 180px; object-fit: cover; }
        .category-label { position: absolute; top: 10px; left: 10px; padding: 0.4rem 0.8rem; border-radius: 16px; font-size: 0.7rem; font-weight: 600; z-index: 10; text-transform: uppercase; color: white; }
        .cart-panel { background-color: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); }
        #cartTotal { color: var(--accent-color); font-weight: 700; }
        .modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: var(--bg-card); margin: auto; padding: 2rem; border-radius: 12px; max-width: 95%; width: 500px; position: relative; }
        .modal-title { color: var(--accent-color); font-weight: 700; }
        .close-button { color: #aaa; position: absolute; top: 0.8rem; right: 1rem; font-size: 1.5rem; font-weight: bold; cursor: pointer; }
        input[type="text"], input[type="password"] { border: 1px solid #ced4da; border-radius: 8px; padding: 0.75rem 1rem; width: 100%; }
        input[type="text"]:focus, input[type="password"]:focus { border-color: var(--accent-color); box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); outline: none; }
        #confirmationMessageBox { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 500; color: white; z-index: 1001; }
        #confirmationMessageBox.success { background-color: var(--success-color); }
        #confirmationMessageBox.error { background-color: var(--danger-color); }
        #confirmationMessageBox.info { background-color: var(--info-color); }
        .animate-float { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-8px); } }

        /* FIXED 3-COLUMN BILL LAYOUT STYLES */
        #paymentBillDetails { max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        .bill-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        .bill-item:last-child { border-bottom: none; }
        /* Left Column: Image + Name */
        .bill-item-col-left {
            display: flex;
            align-items: center;
            flex-grow: 1; /* Takes up available space */
        }
        .bill-item-img {
            width: 45px; height: 45px; border-radius: 6px;
            object-fit: cover; margin-right: 12px; flex-shrink: 0;
        }
        .bill-item-name {
            font-weight: 600;
        }
        /* Middle Column: Quantity */
        .bill-item-col-mid {
            width: 60px; /* Fixed width */
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
            flex-shrink: 0;
        }
        /* Right Column: Price */
        .bill-item-col-right {
            width: 90px; /* Fixed width */
            text-align: right;
            font-weight: 700;
            flex-shrink: 0;
        }
        .bill-item-img {
        width: 45px !important;
        height: 45px !important;
        object-fit: cover !important;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <div id="confirmationMessageBox" class="hidden"></div>

    <header class="site-header p-5 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold flex items-center space-x-3 header-title">
                <i class="fas fa-utensils animate-float text-2xl"></i>
                <span>United Technical Khaja Ghar</span>
            </h1>
        </div>
    </header>

    <main class="flex-grow container mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">

        <?php if ($db_connection_error): ?>
            <div class="col-span-full bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <strong class="font-bold">Connection Error!</strong> Could not connect to the database.
            </div>
        <?php else: ?>
            <div class="lg:col-span-2">
                <h2 class="text-2xl section-title mb-5 flex items-center"><i class="fas fa-hamburger text-2xl mr-3 text-accent-color"></i>Available Food Items</h2>
                <div id="foodItemsContainer" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div class="col-span-full text-center text-gray-500 py-12"><i class="fas fa-spinner fa-spin text-3xl"></i><p>Loading...</p></div>
                </div>
            </div>

            <div class="lg:col-span-1 cart-panel p-6 h-fit sticky top-6">
                <h2 class="text-xl section-title mb-5 flex items-center"><i class="fas fa-shopping-cart text-xl mr-3 text-accent-color"></i>Your Cart</h2>
                <div id="cartItemsList" class="overflow-y-auto max-h-[calc(100vh-400px)] pr-1 space-y-2.5 mb-5">
                    <div class="text-center py-10"><i class="fas fa-shopping-bag text-5xl text-gray-300 mb-3"></i><p class="text-gray-500">Your cart is empty.</p></div>
                </div>
                <div class="border-t border-gray-200 pt-5">
                    <div class="flex justify-between items-center text-xl font-bold mb-5">
                        <span class="section-title">Total:</span>
                        <span id="cartTotal" class="text-2xl">Rs. 0.00</span>
                    </div>
                    <div class="space-y-3">
                        <button id="payOrderBtn" class="w-full btn btn-success-custom py-3 text-base"><i class="fas fa-credit-card mr-2"></i>Pay Now</button>
                        <button id="clearCartBtn" class="w-full btn btn-danger-custom py-3 text-base"><i class="fas fa-trash-alt mr-2"></i>Clear Cart</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Multi-Step Payment Modal -->
    <div id="paymentFlowModal" class="modal hidden items-center justify-center"> 
        <div class="modal-content">
            <span class="close-button" id="closePaymentModalBtn">&times;</span>

            <!-- Step 1: Scan NFC -->
            <div id="step1_scanNfc">
                <div class="text-center mb-5"><i class="fas fa-id-card text-4xl text-accent-color mb-3"></i><h2 class="text-2xl modal-title">Step 1: Scan Card</h2><p class="text-gray-600">Enter NFC Card ID.</p></div>
                <div class="mb-5"><label for="paymentNfcIdInput" class="block font-bold mb-2">NFC Card ID:</label><input type="text" id="paymentNfcIdInput" placeholder="Enter NFC ID"></div>
                <button id="nfcScanProceedBtn" class="w-full btn btn-accent py-3"><i class="fas fa-arrow-right mr-2"></i>Proceed</button>
                <p id="step1_message" class="text-center mt-3 text-xs font-medium text-red-600"></p>
            </div>

            <!-- Step 2: Confirm Details -->
            <div id="step2_confirmDetails" class="hidden">
                <div class="text-center mb-5"><i class="fas fa-user-check text-4xl text-green-500 mb-3"></i><h2 class="text-2xl modal-title">Step 2: Confirm Order</h2><p class="text-gray-600">Review the order details below.</p></div>
                <div class="bg-gray-50 rounded-lg p-4 border mb-4">
                    <div class="flex justify-between font-semibold mb-3"><span>Student:</span><span id="paymentStudentName"></span></div>
                    <div class="flex justify-between font-semibold text-green-600"><span>Card Balance:</span><span id="paymentCurrentBalance"></span></div>
                </div>
                <!-- Detailed Bill Container -->
                <div id="paymentBillDetails" class="mb-4"></div>
                <div class="flex justify-between text-xl font-bold border-t pt-4"><span>Total Bill:</span><span id="paymentTotalBill" class="text-red-600"></span></div>
                <div class="flex space-x-3 mt-6">
                    <button id="confirmCancelBtn" class="w-full btn btn-danger-custom py-3"><i class="fas fa-times mr-2"></i>Cancel</button>
                    <button id="confirmProceedBtn" class="w-full btn btn-success-custom py-3"><i class="fas fa-arrow-right mr-2"></i>Confirm</button>
                </div>
            </div>

            <!-- Step 3: Enter Password -->
            <div id="step3_enterPassword" class="hidden">
                <div class="text-center mb-5"><i class="fas fa-lock text-4xl text-accent-color mb-3"></i><h2 class="text-2xl modal-title">Step 3: Enter Password</h2><p class="text-gray-600">Enter password to finalize payment.</p></div>
                <div class="mb-6"><label for="paymentPasswordInput" class="block font-bold mb-2">Password:</label><input type="password" id="paymentPasswordInput" placeholder="Enter password"></div>
                <button id="finalPayBtn" class="w-full btn btn-success-custom py-3"><i class="fas fa-check-circle mr-2"></i>Confirm & Pay</button>
                <p id="step3_message" class="text-center mt-3 text-xs font-medium text-red-600"></p>
            </div>
        </div>
    </div>

    <footer class="site-footer p-5 text-center mt-auto">
        <div class="container mx-auto text-sm">&copy; <?= date('Y') ?> Smart Canteen. All rights reserved.</div>
    </footer>

    <script src="./js/script.js"></script>
</body>
</html>
