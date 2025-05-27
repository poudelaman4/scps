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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="./css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff; /* Changed to white */
        }
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #ffffff;
            margin: auto;
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 90%;
            width: 400px; /* Fixed width for modal */
            position: relative;
        }
        .close-button {
            color: #aaa;
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.75rem;
            font-weight: bold;
            cursor: pointer;
        }
        .close-button:hover,
        .close-button:focus {
            color: #333;
            text-decoration: none;
        }
        /* Custom styles for confirmation message box */
        #confirmationMessageBox {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            color: white;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none; /* Hidden by default */
        }
        #confirmationMessageBox.success {
            background-color: #10B981; /* Green */
        }
        #confirmationMessageBox.error {
            background-color: #EF4444; /* Red */
        }
        #confirmationMessageBox.info {
            background-color: #3B82F6; /* Blue */
        }
        /* Style for rectangular food item images */
        .food-card img {
            width: 100%; /* Occupy 100% width of parent */
            height: 150px; /* Fixed height for consistency, adjust as needed */
            object-fit: cover; /* Cover the area, cropping if necessary */
            border-radius: 0.5rem; /* Slightly rounded corners for rectangular images */
            margin-bottom: 1rem; /* Add some space below the image */
        }
        .food-card {
            padding: 1.5rem; /* Adjust padding for better spacing with larger images */
        }
        /* Category label styling */
        .category-label {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 10;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <div id="confirmationMessageBox" class="hidden"></div>

    <header class="bg-white text-blue-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold">Smart Canteen</h1>
            <button id="authButton" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 transition duration-200 font-semibold">
                <?= $is_logged_in ? 'Logout' : 'Login' ?>
            </button>
        </div>
    </header>

    <main class="flex-grow container mx-auto p-4 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <?php if ($db_connection_error): ?>
            <div class="col-span-full bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">Could not connect to the database. Please try again later.</span>
            </div>
        <?php else: ?>
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Available Food Items</h2>
                <div id="foodItemsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div class="col-span-full text-center text-gray-500 text-lg py-10">Loading food items...</div>
                </div>
            </div>

            <div class="lg:col-span-1 bg-white rounded-xl shadow-md p-6 h-fit sticky top-4">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Cart</h2>
                <div class="flex items-center justify-between text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                    <span>Student: <span id="studentNameDisplay" class="inline-block">
                        <?php if ($is_logged_in): ?>
                            <a href="<?= $student_profile_url ?>" class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer inline-block"><?= $student_name ?></a>
                        <?php else: ?>
                            <?= $student_name ?>
                        <?php endif; ?>
                    </span></span>
                    <span>Balance: <span id="studentBalanceDisplay">Rs. <?= $student_balance ?></span></span>
                </div>
                <div id="cartItemsList" class="overflow-y-auto max-h-[calc(100vh-300px)] pr-2 space-y-4">
                    <p class="text-gray-500 text-center">Your cart is empty.</p>
                </div>
                <div class="p-4 border-t bg-gray-50 -mx-6 -mb-6 mt-4 rounded-bl-xl rounded-br-xl">
                    <div class="flex justify-between items-center text-xl font-bold text-gray-800 mb-4">
                        <span>Total:</span>
                        <span id="cartTotal">Rs. 0.00</span>
                    </div>
                    <button id="payOrderBtn" class="w-full bg-green-600 text-white py-3 rounded-lg text-lg font-semibold hover:bg-green-700 transition duration-200 shadow-md mb-2">
                        Pay Now
                    </button>
                    <button id="clearCartBtn" class="w-full bg-red-500 text-white py-3 rounded-lg text-lg font-semibold hover:bg-red-600 transition duration-200 shadow-md">
                        Clear Cart
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <div id="nfcLoginModal" class="modal hidden">
        <div class="modal-content">
            <span class="close-button" id="closeNfcLoginModalBtn">&times;</span>
            <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">NFC Login</h2>
            <p class="text-gray-600 text-center mb-6">Please enter your NFC Card ID to log in.</p>

            <div class="mb-4">
                <label for="loginNfcCardId" class="block text-gray-700 text-sm font-bold mb-2">NFC Card ID:</label>
                <input type="text" id="loginNfcCardId" placeholder="Enter NFC Card ID"
                       class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <button id="loginConfirmButton" class="w-full bg-blue-600 text-white py-3 rounded-lg text-lg font-semibold hover:bg-blue-700 transition duration-200 shadow-md">
                Login
            </button>

            <p id="loginMessage" class="text-center mt-4 text-sm font-medium"></p>
        </div>
    </div>

    <div id="passwordOnlyModal" class="modal hidden">
        <div class="modal-content">
            <span class="close-button" id="closePasswordOnlyModalBtn">&times;</span>
            <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Confirm Payment</h2>
            <p class="text-gray-600 text-center mb-6">Please enter your password to confirm the order.</p>

            <div class="mb-6">
                <label for="paymentPassword" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="paymentPassword" placeholder="Enter your password"
                       class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline">
            </div>

            <button id="paymentConfirmButton" class="w-full bg-green-600 text-white py-3 rounded-lg text-lg font-semibold hover:bg-green-700 transition duration-200 shadow-md">
                Confirm & Pay
            </button>

            <p id="paymentMessage" class="text-center mt-4 text-sm font-medium"></p>
        </div>
    </div>

    <footer class="bg-gray-800 text-white p-4 text-center mt-auto">
        <div class="container mx-auto">
            &copy; <?= date('Y') ?> Smart Canteen. All rights reserved.
        </div>
    </footer>

    <script src="./js/script.js"></script>
</body>
</html>
