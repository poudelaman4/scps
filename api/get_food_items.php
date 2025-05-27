<?php
// scps1/api/get_food_items.php - Fetches all food items from the database

header('Content-Type: application/json'); // Respond with JSON

// Include database connection
require_once '../includes/db_connection.php'; // Correct path from api/ folder

// Check database connection
if (!$link) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$food_items = [];
// REVISED: Select 'category' directly from the 'food' table
$sql = "SELECT food_id, name, description, price, image_path, category, is_available 
        FROM food 
        WHERE is_available = 1 
        ORDER BY name ASC";
$result = mysqli_query($link, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $food_items[] = $row;
    }
    echo json_encode(['success' => true, 'food_items' => $food_items]);
} else {
    error_log("Error fetching food items: " . mysqli_error($link));
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve food items. Database query error.']);
}

mysqli_close($link);
?>
