// scps1/js/script.js - All frontend JavaScript logic

document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const foodItemsContainer = document.getElementById('foodItemsContainer');
    const cartItemsList = document.getElementById('cartItemsList');
    const cartTotalSpan = document.getElementById('cartTotal');
    const payOrderBtn = document.getElementById('payOrderBtn');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const confirmationMessageBox = document.getElementById('confirmationMessageBox');

    // Authentication elements
    const authButton = document.getElementById('authButton');
    const studentNameDisplay = document.getElementById('studentNameDisplay');
    const studentBalanceDisplay = document.getElementById('studentBalanceDisplay');

    // NFC Login Modal elements
    const nfcLoginModal = document.getElementById('nfcLoginModal');
    const closeNfcLoginModalBtn = document.getElementById('closeNfcLoginModalBtn');
    const loginNfcCardIdInput = document.getElementById('loginNfcCardId');
    const loginConfirmButton = document.getElementById('loginConfirmButton');
    const loginMessage = document.getElementById('loginMessage');

    // Password Only Payment Modal elements
    const passwordOnlyModal = document.getElementById('passwordOnlyModal');
    const closePasswordOnlyModalBtn = document.getElementById('closePasswordOnlyModalBtn');
    const paymentPasswordInput = document.getElementById('paymentPassword');
    const paymentConfirmButton = document.getElementById('paymentConfirmButton');
    const paymentMessage = document.getElementById('paymentMessage');

    // --- Global Cart Variable ---
    let cart = [];

    // --- Utility Functions ---

    /**
     * Displays a confirmation message to the user.
     * @param {string} message - The message to display.
     * @param {'success'|'error'|'info'} type - The type of message (for styling).
     */
    function showConfirmation(message, type) {
        confirmationMessageBox.textContent = message;
        confirmationMessageBox.className = `fixed bottom-20 left-1/2 transform -translate-x-1/2 p-4 rounded-lg font-semibold text-white z-50 shadow-lg ${type}`;
        confirmationMessageBox.style.display = 'block';
        setTimeout(() => {
            confirmationMessageBox.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }

    /**
     * Updates the UI to reflect login/logout state.
     * @param {boolean} isLoggedIn - True if user is logged in, false otherwise.
     * @param {string} studentName - The student's name.
     * @param {number|string} studentBalance - The student's balance.
     */
    function updateAuthUI(isLoggedIn, studentName = 'Guest', studentBalance = 'N/A') {
        if (isLoggedIn) {
            authButton.textContent = 'Logout';
            // Create the anchor tag for the student name
            const profileLink = document.createElement('a');
            profileLink.href = './student/profile.php'; // Path to student profile
            profileLink.classList.add('text-blue-600', 'hover:text-blue-800', 'hover:underline', 'cursor-pointer', 'inline-block');
            profileLink.textContent = studentName;
            
            // Clear existing content and append the new link
            studentNameDisplay.innerHTML = '';
            studentNameDisplay.appendChild(profileLink);

            studentBalanceDisplay.textContent = `Rs. ${parseFloat(studentBalance).toFixed(2)}`;
        } else {
            authButton.textContent = 'Login';
            studentNameDisplay.innerHTML = 'Guest'; // Set directly as text
            studentBalanceDisplay.textContent = 'Rs. N/A';
        }
    }

    /**
     * Fetches food items from the backend and displays them.
     */
    async function fetchAndDisplayFoodItems() {
        foodItemsContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 text-lg py-10">Loading food items...</div>';
        try {
            const response = await fetch('./api/get_food_items.php');
            // Check if the response is OK (status 200) before trying to parse JSON
            if (!response.ok) {
                const errorText = await response.text(); // Get raw text to see PHP errors
                console.error('Network response was not ok:', response.status, errorText);
                showConfirmation(`Server error: ${response.status}. See console for details.`, 'error');
                foodItemsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 text-lg py-10">Failed to load food items. Server responded with an error.</div>`;
                return;
            }

            const data = await response.json(); // Attempt to parse JSON

            if (data.success) {
                if (data.food_items.length === 0) {
                    foodItemsContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 text-lg py-10">No food items available at the moment.</div>';
                    return;
                }
                foodItemsContainer.innerHTML = ''; // Clear loading message
                data.food_items.forEach(item => {
                    // Determine category label class based on category name
                    const categoryClass = item.category === 'Veg' ? 'bg-green-500' : 'bg-red-500'; // Green for Veg, Red for Non-Veg
                    const foodCard = `
                        <div class="food-card bg-white rounded-xl shadow-md p-0 flex flex-col items-center text-center relative transform hover:scale-105 transition-transform duration-300 overflow-hidden">
                            <span class="category-label ${categoryClass}">${item.category}</span>
                            <img src="${item.image_path ? './' + item.image_path : 'https://placehold.co/300x200/E0E0E0/4A4A4A?text=No+Image'}"
                                 onerror="this.onerror=null;this.src='https://placehold.co/300x200/E0E0E0/4A4A4A?text=No+Image';"
                                 alt="${item.name}" class="w-full h-48 object-cover">
                            <div class="p-4 w-full"> <h3 class="text-xl font-semibold text-gray-800 mb-2">${item.name}</h3>
                                <p class="text-gray-600 text-sm mb-3">${item.description}</p>
                                <p class="text-2xl font-bold text-blue-600 mb-4">Rs. ${parseFloat(item.price).toFixed(2)}</p>
                                <button data-food-id="${item.food_id}"
                                        data-food-name="${item.name}"
                                        data-food-price="${item.price}"
                                        class="add-to-cart-btn bg-blue-500 text-white px-6 py-2 rounded-full hover:bg-blue-600 transition duration-200 shadow-md flex items-center justify-center space-x-2 w-full">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>
                    `;
                    foodItemsContainer.insertAdjacentHTML('beforeend', foodCard);
                });
                attachAddToCartListeners(); // Attach listeners after items are rendered
            } else {
                foodItemsContainer.innerHTML = `<div class="col-span-full text-center text-red-500 text-lg py-10">${data.message}</div>`;
            }
        } catch (error) {
            console.error('Error fetching food items:', error);
            // This is the SyntaxError (JSON parsing failure)
            showConfirmation('Failed to load food items. Invalid server response.', 'error');
            foodItemsContainer.innerHTML = '<div class="col-span-full text-center text-red-500 text-lg py-10">Failed to load food items. Please check server logs.</div>';
        }
    }

    /**
     * Attaches click listeners to "Add to Cart" buttons.
     */
    function attachAddToCartListeners() {
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.onclick = async function() {
                const foodId = this.dataset.foodId;
                const foodName = this.dataset.foodName;
                const foodPrice = this.dataset.foodPrice;

                try {
                    const response = await fetch('./api/add_to_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ food_id: foodId, food_name: foodName, price: foodPrice, quantity: 1 })
                    });
                    const result = await response.json();

                    if (result.success) {
                        showConfirmation(`${foodName} added to cart!`, 'success');
                        fetchAndDisplayCartItems(); // Refresh cart display
                    } else {
                        showConfirmation(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error adding to cart:', error);
                    showConfirmation('Failed to add item to cart.', 'error');
                }
            };
        });
    }

    /**
     * Fetches current cart items from the backend and updates the cart panel.
     */
    async function fetchAndDisplayCartItems() {
        try {
            const response = await fetch('./api/get_cart_items.php');
            const data = await response.json();

            if (data.success) {
                cart = data.cart; // Update global cart variable
                cartItemsList.innerHTML = ''; // Clear current cart display
                let total = 0;

                if (cart.length === 0) {
                    cartItemsList.innerHTML = '<p class="text-gray-500 text-center">Your cart is empty.</p>';
                } else {
                    cart.forEach(item => {
                        const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                        total += itemTotal;

                        const cartItemHtml = `
                            <div class="flex items-center justify-between bg-gray-100 p-3 rounded-lg shadow-sm">
                                <div class="flex-grow">
                                    <h4 class="font-semibold text-gray-800">${item.food_name}</h4>
                                    <p class="text-sm text-gray-600">Rs. ${parseFloat(item.price).toFixed(2)} x ${item.quantity}</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button class="update-quantity-btn text-blue-500 hover:text-blue-700 font-bold text-lg" data-food-id="${item.food_id}" data-action="decrease">-</button>
                                    <span class="text-lg font-medium">${item.quantity}</span>
                                    <button class="update-quantity-btn text-blue-500 hover:text-blue-700 font-bold text-lg" data-food-id="${item.food_id}" data-action="increase">+</button>
                                    <button class="remove-from-cart-btn text-red-500 hover:text-red-700 ml-4" data-food-id="${item.food_id}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        cartItemsList.insertAdjacentHTML('beforeend', cartItemHtml);
                    });
                }
                cartTotalSpan.textContent = `Rs. ${total.toFixed(2)}`;
                attachCartItemListeners(); // Attach listeners for update/remove
            } else {
                showConfirmation(data.message, 'error');
                cartItemsList.innerHTML = '<p class="text-gray-500 text-center">Failed to load cart items.</p>';
            }
        } catch (error) {
            console.error('Error fetching cart items:', error);
            showConfirmation('Failed to load cart items.', 'error');
            cartItemsList.innerHTML = '<p class="text-gray-500 text-center">Failed to load cart items.</p>';
        }
    }

    /**
     * Attaches listeners to cart item quantity controls and remove buttons.
     */
    function attachCartItemListeners() {
        document.querySelectorAll('.update-quantity-btn').forEach(button => {
            button.onclick = async function() {
                const foodId = this.dataset.foodId;
                const action = this.dataset.action; // 'increase' or 'decrease'

                try {
                    const response = await fetch('./api/update_cart_quantity.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ food_id: foodId, action: action })
                    });
                    const result = await response.json();

                    if (result.success) {
                        fetchAndDisplayCartItems(); // Refresh cart display
                    } else {
                        showConfirmation(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error updating cart quantity:', error);
                    showConfirmation('Failed to update item quantity.', 'error');
                }
            };
        });

        document.querySelectorAll('.remove-from-cart-btn').forEach(button => {
            button.onclick = async function() {
                const foodId = this.dataset.foodId;

                try {
                    const response = await fetch('./api/remove_from_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ food_id: foodId })
                    });
                    const result = await response.json();

                    if (result.success) {
                        showConfirmation('Item removed from cart.', 'info');
                        fetchAndDisplayCartItems(); // Refresh cart display
                    } else {
                        showConfirmation(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error removing from cart:', error);
                    showConfirmation('Failed to remove item from cart.', 'error');
                }
            };
        });
    }

    /**
     * Clears the cart on the server side.
     */
    async function clearCart() {
        console.log('Attempting to clear cart...');
        try {
            const response = await fetch('./api/clear_cart.php', { method: 'POST' });
            const result = await response.json();
            if (result.success) {
                console.log('Cart cleared successfully on server.');
                showConfirmation('Cart cleared successfully!', 'info');
                fetchAndDisplayCartItems(); // Refresh cart display (will show empty)
            } else {
                console.error('Failed to clear cart on server:', result.message);
                showConfirmation(result.message, 'error');
            }
        } catch (error) {
            console.error('Error clearing cart (network/fetch issue):', error);
            showConfirmation('Failed to clear cart. Please try again.', 'error');
        }
    }

    // --- Cart Action Buttons ---

    // Attach event listener to the "Clear Cart" button
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', clearCart);
    }

    // --- Authentication Logic ---

    // Handle Login/Logout button click
    authButton.addEventListener('click', async () => {
        if (authButton.textContent === 'Login') {
            // Open NFC Login Modal (NFC ID Only)
            nfcLoginModal.classList.remove('hidden');
            loginNfcCardIdInput.value = '';
            loginMessage.textContent = '';
            loginNfcCardIdInput.focus();
        } else {
            // Perform Logout
            try {
                const response = await fetch('./api/logout.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    showConfirmation('Logged out successfully!', 'info');
                    updateAuthUI(false); // Update UI to logged out state
                    clearCart(); // Clear cart on logout
                } else {
                    showConfirmation(result.message, 'error');
                }
            } catch (error) {
                console.error('Error during logout:', error);
                showConfirmation('Failed to logout. Please try again.', 'error');
            }
        }
    });

    // Close NFC Login Modal
    closeNfcLoginModalBtn.addEventListener('click', () => {
        nfcLoginModal.classList.add('hidden');
    });
    nfcLoginModal.addEventListener('click', (event) => {
        if (event.target === nfcLoginModal) {
            nfcLoginModal.classList.add('hidden');
        }
    });

    // Handle NFC Login confirmation (NFC ID Only)
    loginConfirmButton.addEventListener('click', async () => {
        const nfcId = loginNfcCardIdInput.value.trim();

        if (!nfcId) {
            loginMessage.textContent = 'Please enter your NFC Card ID.';
            loginMessage.style.color = 'red';
            return;
        }

        loginConfirmButton.disabled = true;
        loginConfirmButton.textContent = 'Logging in...';
        loginMessage.textContent = '';

        try {
            const response = await fetch('./api/nfc_login.php', { // New API for login
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nfc_id: nfcId }) // Only send NFC ID
            });
            const result = await response.json();

            if (result.success) {
                loginMessage.textContent = 'Login successful!';
                loginMessage.style.color = 'green';
                // Pass student name and balance to updateAuthUI
                updateAuthUI(true, result.student_name, result.student_balance);
                setTimeout(() => {
                    nfcLoginModal.classList.add('hidden');
                    showConfirmation('Welcome, ' + result.student_name + '!', 'success');
                }, 1000);
            } else {
                loginMessage.textContent = result.message;
                loginMessage.style.color = 'red';
            }
        } catch (error) {
            console.error('Error during NFC login:', error);
            loginMessage.textContent = 'An error occurred during login. Please try again.';
            loginMessage.style.color = 'red';
        } finally {
            loginConfirmButton.disabled = false;
            loginConfirmButton.textContent = 'Login';
        }
    });

    // --- Payment Logic ---

    // Attach event listener to the "Pay Now" button in the live cart
    if (payOrderBtn) {
        payOrderBtn.addEventListener('click', async function(event) {
            event.preventDefault();

            if (cart.length === 0) {
                showConfirmation('Your cart is empty. Please add items before confirming.', 'info');
                return;
            }

            // Check if user is logged in
            const authCheckResponse = await fetch('./api/check_auth.php'); // New API to check session auth
            const authCheckResult = await authCheckResponse.json();

            if (!authCheckResult.is_logged_in) {
                showConfirmation('Please login first to proceed with payment.', 'info');
                authButton.click(); // Simulate click on login button to open login modal
                return;
            }

            // If logged in, open password-only payment modal
            passwordOnlyModal.classList.remove('hidden');
            paymentPasswordInput.value = '';
            paymentMessage.textContent = '';
            paymentPasswordInput.focus();
        });
    }

    // Close Password Only Payment Modal
    closePasswordOnlyModalBtn.addEventListener('click', () => {
        passwordOnlyModal.classList.add('hidden');
    });
    passwordOnlyModal.addEventListener('click', (event) => {
        if (event.target === passwordOnlyModal) {
            passwordOnlyModal.classList.add('hidden');
        }
    });

    // Handle Password Only Payment confirmation
    paymentConfirmButton.addEventListener('click', async () => {
        const password = paymentPasswordInput.value.trim();

        if (!password) {
            paymentMessage.textContent = 'Please enter your password.';
            paymentMessage.style.color = 'red';
            return;
        }

        paymentConfirmButton.disabled = true;
        paymentConfirmButton.textContent = 'Confirming...';
        paymentMessage.textContent = '';

        try {
            // Send the password and cart items to the backend for final processing
            const response = await fetch('./api/process_nfc_order.php', { // Re-using process_nfc_order.php
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    password: password,
                    cart_items: cart // Send the current cart state
                })
            });

            const result = await response.json();

            if (result.success) {
                paymentMessage.textContent = 'Order confirmed! New balance: Rs. ' + parseFloat(result.new_balance).toFixed(2);
                paymentMessage.style.color = 'green';
                // Update balance display on the main page
                // We need to ensure the link is preserved after balance update
                updateAuthUI(true, result.student_name, result.new_balance); // Re-call updateAuthUI to refresh name/balance

                // Update session info after successful payment
                const updateSessionResponse = await fetch('./api/update_session_balance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ new_balance: result.new_balance })
                });
                await updateSessionResponse.json();


                // Clear cart and close modal after a short delay
                setTimeout(() => {
                    clearCart(); // Call the function to clear server-side cart
                    fetchAndDisplayCartItems(); // Refresh cart display (will show empty)
                    passwordOnlyModal.classList.add('hidden');
                    showConfirmation('Order placed successfully!', 'success');
                }, 1500);

            } else {
                paymentMessage.textContent = result.message;
                paymentMessage.style.color = 'red';
            }
        } catch (error) {
            console.error('Error during payment confirmation:', error);
            paymentMessage.textContent = 'An error occurred during payment. Please try again.';
            paymentMessage.style.color = 'red';
        } finally {
            paymentConfirmButton.disabled = false;
            paymentConfirmButton.textContent = 'Confirm & Pay';
        }
    });

    // --- Initial Load ---
    fetchAndDisplayFoodItems();
    fetchAndDisplayCartItems(); // Also fetch initial cart state on load
    // Check initial auth state to set Login/Logout button correctly
    fetch('./api/check_auth.php')
        .then(response => response.json())
        .then(data => {
            if (data.is_logged_in) {
                // Call updateAuthUI with the fetched data to correctly set the link
                updateAuthUI(true, data.student_name, data.student_balance);
            } else {
                updateAuthUI(false);
            }
        })
        .catch(error => {
            console.error('Error checking initial auth state:', error);
            updateAuthUI(false); // Default to logged out on error
        });
});
