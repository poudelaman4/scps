<?php
// scps1/index.php - Main student menu page for NFC-based ordering

// Start the session to manage the cart and login state
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

// Initialize student balance and name for display
$student_name = "Guest";
$student_balance = "N/A";
$is_logged_in = false;
$student_profile_url = "#"; // Initialize URL as a placeholder or non-link

// Check if student info is in session (meaning they are "logged in" via NFC)
if (isset($_SESSION['current_student_info']) && !empty($_SESSION['current_student_info']['student_id'])) {
    $student_name = htmlspecialchars($_SESSION['current_student_info']['student_name']);
    $student_balance = number_format($_SESSION['current_student_info']['student_balance'], 2);
    $is_logged_in = true;

    // IMPORTANT FIX: Set $_SESSION['student_id'] so profile.php can recognize the login
    $_SESSION['student_id'] = $_SESSION['current_student_info']['student_id'];

    // Set the dynamic profile URL when logged in
    // Assuming index.php is in the root and student/profile.php is in a subdirectory
    $student_profile_url = "./student/profile.php";
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
            --accent-color: #007BFF; /* Primary Blue */
            --accent-hover: #0056b3;
            --success-color: #28A745; /* Green */
            --success-hover: #1e7e34;
            --danger-color: #DC3545; /* Red */
            --danger-hover: #c82333;
            --info-color: #17A2B8; /* Teal/Info */
            --text-primary: #212529; /* Dark Gray for text */
            --text-secondary: #6C757D; /* Medium Gray for text */
            --bg-main: #F8F9FA; /* Light Gray page background */
            --bg-card: #FFFFFF; /* White for cards/panels */
            --border-color: #DEE2E6; /* Light border */
            --light-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            --standard-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .site-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--light-shadow);
        }
        .header-title {
            color: var(--accent-color);
            font-weight: 800; 
        }

        /* Main Content Titles */
        .section-title {
            color: var(--text-primary); 
            font-weight: 700;
        }
        
        /* Buttons */
        .btn {
            padding: 0.65rem 1.25rem; 
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
            letter-spacing: 0.5px;
        }
        .btn-accent {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        .btn-accent:hover {
            background-color: var(--accent-hover);
            border-color: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
        }
        .btn-success-custom {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        .btn-success-custom:hover {
            background-color: var(--success-hover);
            border-color: var(--success-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
        }
        .btn-danger-custom {
            background-color: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }
        .btn-danger-custom:hover {
            background-color: var(--danger-hover);
            border-color: var(--danger-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        /* Food Card */
        .food-card {
            background-color: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease-in-out;
            box-shadow: var(--light-shadow);
            border: 1px solid var(--border-color);
        }
        .food-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--standard-shadow);
            border-color: var(--accent-color);
        }
        .food-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .food-card:hover img {
            transform: scale(1.05);
        }
        .category-label {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 0.4rem 0.8rem;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: var(--accent-color); 
            color: white;
        }

        /* Cart Panel */
        .cart-panel {
            background-color: var(--bg-card);
            border-radius: 16px;
            box-shadow: var(--standard-shadow);
            border: 1px solid var(--border-color);
        }
        .student-info-bar {
            background-color: #e7f3ff; 
            border-radius: 8px;
            border: 1px solid #d0eaff;
            color: #004085;
        }
        .student-info-bar a {
            color: var(--accent-hover);
            font-weight: 500;
        }
        .student-info-bar a:hover {
            text-decoration: underline;
        }
        #studentNameDisplay .fas, #studentBalanceDisplay .fas { 
             color: var(--accent-color);
        }
        #studentBalanceDisplay .fas {
            color: var(--success-color);
        }
        #studentBalanceDisplay {
            font-weight: 700;
            color: var(--success-hover);
        }
        .cart-item {
            background-color: #f8f9fa; 
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .cart-item:hover {
            background-color: var(--bg-card);
            border-color: #ced4da;
            transform: translateX(2px);
        }
        #cartTotal {
            color: var(--accent-color);
            font-weight: 700;
        }
        .cart-panel .icon-color { 
             color: var(--accent-color);
        }

        /* Modals */
        .modal { 
            /* This is the full-screen overlay. */
            /* Visibility is primarily controlled by Tailwind's 'hidden' class on the modal div. */
            /* When 'hidden' is removed by JS, also add 'flex items-center justify-center' (Tailwind classes) */
            /* to this modal div to center the .modal-content. */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; /* Enable scroll if content is too long */
            background-color: rgba(0,0,0,0.4); /* Semi-transparent background */
        }
        
        .modal-content {
            background-color: var(--bg-card);
            margin: auto; /* Used for centering if parent isn't flex; works with flex too */
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            max-width: 90%;
            width: 400px;
            position: relative;
            border: 1px solid var(--border-color);
        }
        .modal-title { 
             color: var(--accent-color);
             font-weight: 700;
        }
        .modal-icon { 
            color: var(--accent-color); 
        }
        .modal-icon.success {
            color: var(--success-color);
        }
        .close-button {
            color: #aaa;
            position: absolute;
            top: 0.8rem;
            right: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .close-button:hover,
        .close-button:focus {
            color: var(--text-primary);
            transform: scale(1.05);
        }
        
        /* Inputs */
        input[type="text"], input[type="password"] {
            background-color: var(--bg-card);
            border: 1px solid #ced4da; 
            border-radius: 8px;
            padding: 0.75rem 1rem; 
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
            color: #495057; 
            width: 100%; 
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: var(--accent-color); 
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); 
            outline: none;
        }

        /* Confirmation Message Box */
        #confirmationMessageBox { /* Visibility controlled by Tailwind 'hidden' class */
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.8rem 1.5rem; 
            border-radius: 8px;
            font-weight: 500;
            color: white;
            z-index: 1000;
            box-shadow: var(--standard-shadow);
        }
        #confirmationMessageBox.success {
            background-color: var(--success-color);
            border: 1px solid var(--success-hover);
        }
        #confirmationMessageBox.error {
            background-color: var(--danger-color);
            border: 1px solid var(--danger-hover);
        }
        #confirmationMessageBox.info {
            background-color: var(--info-color);
            border: 1px solid #117a8b; 
        }
        
        /* Footer */
        .site-footer {
            background-color: #343A40; 
            color: var(--bg-main); 
        }

        /* Animations */
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); } 
        }
        .loading-shimmer {
            background: linear-gradient(90deg, #e9ecef 25%, #ced4da 50%, #e9ecef 75%); 
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Helper for icon colors */
        .icon-accent { color: var(--accent-color); }
        .icon-success { color: var(--success-color); }
        .icon-danger { color: var(--danger-color); }

    </style>
</head>
<body class="flex flex-col">

    <div id="confirmationMessageBox" class="hidden"></div>

    <header class="site-header p-5 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold flex items-center space-x-3 header-title">
                <i class="fas fa-utensils animate-float text-2xl"></i>
                <span>United Technical Khaja Ghar</span>
            </h1>
            <button id="authButton" class="btn btn-accent px-6 py-2 rounded-md font-semibold text-base">
                <?= $is_logged_in ? '<i class="fas fa-sign-out-alt mr-2"></i>Logout' : '<i class="fas fa-sign-in-alt mr-2"></i>Login' ?>
            </button>
        </div>
    </header>

    <main class="flex-grow container mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">

        <?php if ($db_connection_error): ?>
            <div class="col-span-full bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-md" role="alert">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-exclamation-triangle text-xl icon-danger"></i>
                    <div>
                        <strong class="font-bold">Connection Error!</strong>
                        <span class="block sm:inline">Could not connect to the database. Please try again later.</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="lg:col-span-2">
                <div class="flex items-center space-x-3 mb-5">
                    <i class="fas fa-hamburger text-2xl icon-accent"></i>
                    <h2 class="text-2xl section-title">Available Food Items</h2>
                </div>
                <div id="foodItemsContainer" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
                    <div class="col-span-full text-center text-gray-500 text-base py-12">
                        <i class="fas fa-spinner fa-spin text-3xl mb-3 icon-accent"></i>
                        <p>Loading delicious food items...</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1 cart-panel p-5 md:p-6 h-fit sticky top-4">
                <div class="flex items-center space-x-3 mb-5">
                    <i class="fas fa-shopping-cart text-xl icon-accent"></i>
                    <h2 class="text-xl section-title">Your Cart</h2>
                </div>
                
                <div class="student-info-bar flex flex-col sm:flex-row items-start sm:items-center justify-between text-xs font-semibold mb-5 p-3 space-y-2 sm:space-y-0">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-user"></i>
                        <span>Student: <span id="studentNameDisplay" class="inline-block">
                            <?php if ($is_logged_in): ?>
                                <a href="<?= $student_profile_url ?>" class="hover:underline cursor-pointer font-medium"><?= $student_name ?></a>
                            <?php else: ?>
                                <?= $student_name ?>
                            <?php endif; ?>
                        </span></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-wallet"></i>
                        <span>Balance: <span id="studentBalanceDisplay">Rs. <?= $student_balance ?></span></span>
                    </div>
                </div>
                
                <div id="cartItemsList" class="overflow-y-auto max-h-[calc(100vh-450px)] pr-1 space-y-2.5 mb-5">
                    <div class="text-center py-10">
                        <i class="fas fa-shopping-bag text-5xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 text-sm">Your cart is empty.</p>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-5">
                    <div class="flex justify-between items-center text-xl font-bold mb-5">
                        <span class="section-title">Total:</span>
                        <span id="cartTotal" class="text-2xl">Rs. 0.00</span>
                    </div>
                    <div class="space-y-2.5">
                        <button id="payOrderBtn" class="w-full btn btn-success-custom py-3 rounded-md text-base font-semibold">
                            <i class="fas fa-credit-card mr-1.5"></i>
                            Pay Now
                        </button>
                        <button id="clearCartBtn" class="w-full btn btn-danger-custom py-3 rounded-md text-base font-semibold">
                            <i class="fas fa-trash-alt mr-1.5"></i>
                            Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- 
        NFC Login Modal 
        IMPORTANT: This modal is hidden by default using Tailwind's 'hidden' class.
        Your JavaScript (script.js) should:
        1. To SHOW: 
           - Remove the 'hidden' class.
           - Add 'flex items-center justify-center' classes to this div for centering.
           Example: nfcLoginModal.classList.remove('hidden'); nfcLoginModal.classList.add('flex', 'items-center', 'justify-center');
        2. To HIDE: 
           - Add the 'hidden' class back.
           - Remove 'flex items-center justify-center' classes.
           Example: nfcLoginModal.classList.add('hidden'); nfcLoginModal.classList.remove('flex', 'items-center', 'justify-center');
        If this modal appears on page load, please double-check your script.js for any code that might be showing it prematurely,
        or inspect browser console for JavaScript errors or unexpected CSS overrides.
    -->
    <div id="nfcLoginModal" class="modal hidden"> 
        <div class="modal-content">
            <span class="close-button" id="closeNfcLoginModalBtn">&times;</span>
            <div class="text-center mb-5">
                <i class="fas fa-id-card text-4xl modal-icon mb-3"></i>
                <h2 class="text-2xl modal-title mb-1">NFC Login</h2>
                <p class="text-gray-600 text-sm">Please enter your NFC Card ID to log in.</p>
            </div>

            <div class="mb-5">
                <label for="loginNfcCardId" class="block text-gray-700 text-xs font-bold mb-2">NFC Card ID:</label>
                <input type="text" id="loginNfcCardId" placeholder="Enter NFC Card ID"
                       class="py-3 px-3 text-sm">
            </div>

            <button id="loginConfirmButton" class="w-full btn btn-accent py-3 rounded-md text-base">
                <i class="fas fa-sign-in-alt mr-1.5"></i>
                Login
            </button>

            <p id="loginMessage" class="text-center mt-3 text-xs font-medium"></p>
        </div>
    </div>

    <!-- 
        Password Only Modal for Payment 
        IMPORTANT: This modal is hidden by default using Tailwind's 'hidden' class.
        Follow the same JavaScript logic as nfcLoginModal for showing/hiding.
        If this modal appears on page load, please double-check your script.js for any code that might be showing it prematurely,
        or inspect browser console for JavaScript errors or unexpected CSS overrides.
    -->
    <div id="passwordOnlyModal" class="modal hidden"> 
        <div class="modal-content">
            <span class="close-button" id="closePasswordOnlyModalBtn">&times;</span>
            <div class="text-center mb-5">
                <i class="fas fa-lock text-4xl modal-icon success mb-3"></i>
                <h2 class="text-2xl modal-title mb-1">Confirm Payment</h2>
                <p class="text-gray-600 text-sm">Please enter your password to confirm the order.</p>
            </div>

            <div class="mb-6">
                <label for="paymentPassword" class="block text-gray-700 text-xs font-bold mb-2">Password:</label>
                <input type="password" id="paymentPassword" placeholder="Enter your password"
                       class="py-3 px-3 text-sm">
            </div>

            <button id="paymentConfirmButton" class="w-full btn btn-success-custom py-3 rounded-md text-base">
                <i class="fas fa-check-circle mr-1.5"></i>
                Confirm & Pay
            </button>

            <p id="paymentMessage" class="text-center mt-3 text-xs font-medium"></p>
        </div>
    </div>

    <footer class="site-footer p-5 text-center mt-auto">
        <div class="container mx-auto">
            <div class="flex items-center justify-center space-x-1.5 text-sm">
                <i class="fas fa-copyright"></i>
                <span>&copy; <?= date('Y') ?> Smart Canteen. All rights reserved.</span>
            </div>
        </div>
    </footer>

    <script src="./js/script.js"></script>
</body>
</html>
