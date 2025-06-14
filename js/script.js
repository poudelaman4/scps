document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const foodItemsContainer = document.getElementById('foodItemsContainer');
    const cartItemsList = document.getElementById('cartItemsList');
    const cartTotalSpan = document.getElementById('cartTotal');
    const payOrderBtn = document.getElementById('payOrderBtn');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const confirmationMessageBox = document.getElementById('confirmationMessageBox');

    // --- Payment Modal Elements ---
    const paymentFlowModal = document.getElementById('paymentFlowModal');
    const closePaymentModalBtn = document.getElementById('closePaymentModalBtn');
    
    // Step 1
    const step1Div = document.getElementById('step1_scanNfc');
    const nfcIdInput = document.getElementById('paymentNfcIdInput');
    const nfcScanProceedBtn = document.getElementById('nfcScanProceedBtn');
    const step1Message = document.getElementById('step1_message');
    
    // Step 2
    const step2Div = document.getElementById('step2_confirmDetails');
    const studentNameSpan = document.getElementById('paymentStudentName');
    const currentBalanceSpan = document.getElementById('paymentCurrentBalance');
    const paymentBillDetails = document.getElementById('paymentBillDetails');
    const totalBillSpan = document.getElementById('paymentTotalBill');
    const confirmProceedBtn = document.getElementById('confirmProceedBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');

    // Step 3
    const step3Div = document.getElementById('step3_enterPassword');
    const passwordInput = document.getElementById('paymentPasswordInput');
    const finalPayBtn = document.getElementById('finalPayBtn');
    const step3Message = document.getElementById('step3_message');
    
    
    // --- Global State ---
    let cart = [];
    let paymentData = {}; 

    // --- Utility Functions ---
    function showConfirmation(message, type) {
        confirmationMessageBox.textContent = message;
        confirmationMessageBox.className = `fixed bottom-5 left-1/2 -translate-x-1/2 p-3 rounded-lg font-semibold text-white z-50 shadow-lg ${type}`;
        setTimeout(() => { confirmationMessageBox.className += ' hidden'; }, 3000);
    }

    // --- Core Application Logic ---

    async function fetchAndDisplayFoodItems() {
        foodItemsContainer.innerHTML = '<div class="col-span-full text-center p-10"><i class="fas fa-spinner fa-spin text-3xl text-blue-500"></i></div>';
        try {
            const response = await fetch('./api/get_food_items.php');
            const data = await response.json();
            if (data.success) {
                foodItemsContainer.innerHTML = '';
                if (data.food_items.length === 0) {
                    foodItemsContainer.innerHTML = '<p class="col-span-full text-center text-gray-500">No items available.</p>';
                    return;
                }
                data.food_items.forEach(item => {
                    const categoryClass = item.category === 'Veg' ? 'bg-green-500' : 'bg-red-500';
                    const imageSrc = item.image_path ? `./${item.image_path}` : 'https://placehold.co/300x200/E0E0E0/4A4A4A?text=No+Image';
                    const foodCard = `
                        <div class="food-card flex flex-col">
                            <div class="relative">
                                <span class="category-label ${categoryClass}">${item.category}</span>
                                <img src="${imageSrc}" onerror="this.src='https://placehold.co/300x200/E0E0E0/4A4A4A?text=Error'" alt="${item.name}">
                            </div>
                            <div class="p-4 flex flex-col flex-grow">
                                <h3 class="text-xl font-semibold mb-2">${item.name}</h3>
                                <p class="text-gray-600 text-sm mb-4 flex-grow">${item.description || ''}</p>
                                <p class="text-2xl font-bold text-blue-600 mb-4">Rs. ${parseFloat(item.price).toFixed(2)}</p>
                                <button data-food-id="${item.food_id}" 
                                        data-food-name="${item.name}" 
                                        data-food-price="${item.price}" 
                                        data-image-path="${imageSrc}" 
                                        class="add-to-cart-btn btn btn-accent w-full mt-auto">
                                    <i class="fas fa-plus-circle mr-2"></i>Add to Cart
                                </button>
                            </div>
                        </div>`;
                    foodItemsContainer.insertAdjacentHTML('beforeend', foodCard);
                });
                attachAddToCartListeners();
            } else {
                foodItemsContainer.innerHTML = `<p class="col-span-full text-red-500 text-center">${data.message}</p>`;
            }
        } catch (error) {
            foodItemsContainer.innerHTML = '<p class="col-span-full text-red-500 text-center">Failed to load items.</p>';
        }
    }

    function attachAddToCartListeners() {
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.onclick = async function() {
                const foodId = this.dataset.foodId;
                const foodName = this.dataset.foodName;
                const foodPrice = this.dataset.foodPrice;
                // **FIX**: Get the image path from the data attribute
                const imagePath = this.dataset.imagePath;

                try {
                    const response = await fetch('./api/add_to_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        // **FIX**: Send the image path to the backend
                        body: JSON.stringify({ food_id: foodId, food_name: foodName, price: foodPrice, quantity: 1, image_path: imagePath })
                    });
                    const result = await response.json();
                    if (result.success) {
                        showConfirmation(`${foodName} added to cart!`, 'success');
                        fetchAndDisplayCartItems();
                    } else {
                        showConfirmation(result.message, 'error');
                    }
                } catch (error) {
                    showConfirmation('Failed to add item.', 'error');
                }
            };
        });
    }

    async function fetchAndDisplayCartItems() {
        try {
            const response = await fetch('./api/get_cart_items.php');
            const data = await response.json();
            if (data.success) {
                cart = data.cart;
                cartItemsList.innerHTML = '';
                let total = 0;

                if (cart.length === 0) {
                    cartItemsList.innerHTML = '<div class="text-center py-10"><i class="fas fa-shopping-bag text-5xl text-gray-300 mb-3"></i><p class="text-gray-500">Cart is empty.</p></div>';
                } else {
                    cart.forEach(item => {
                        total += parseFloat(item.price) * parseInt(item.quantity);
                        cartItemsList.innerHTML += `
                            <div class="flex items-center p-2 rounded-lg bg-gray-50">
                                <div class="flex-grow">
                                    <h4 class="font-semibold">${item.food_name}</h4>
                                    <p class="text-sm text-gray-600">Rs. ${parseFloat(item.price).toFixed(2)}</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <button class="update-quantity-btn text-blue-500 font-bold" data-food-id="${item.food_id}" data-action="decrease">-</button>
                                    <span>${item.quantity}</span>
                                    <button class="update-quantity-btn text-blue-500 font-bold" data-food-id="${item.food_id}" data-action="increase">+</button>
                                    <button class="remove-from-cart-btn text-red-500" data-food-id="${item.food_id}"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>`;
                    });
                }
                cartTotalSpan.textContent = `Rs. ${total.toFixed(2)}`;
                attachCartItemListeners();
            }
        } catch (error) {
            cartItemsList.innerHTML = '<p class="text-red-500 text-center">Error loading cart.</p>';
        }
    }

    function attachCartItemListeners() {
        document.querySelectorAll('.update-quantity-btn, .remove-from-cart-btn').forEach(button => {
            button.onclick = async function() {
                const foodId = this.dataset.foodId;
                const isUpdate = this.classList.contains('update-quantity-btn');
                const url = isUpdate ? './api/update_cart_quantity.php' : './api/remove_from_cart.php';
                const body = isUpdate ? { food_id: foodId, action: this.dataset.action } : { food_id: foodId };

                try {
                    const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                    const result = await response.json();
                    if (result.success) {
                        fetchAndDisplayCartItems();
                    } else { showConfirmation(result.message, 'error'); }
                } catch { showConfirmation('Failed to update cart.', 'error'); }
            };
        });
    }

    async function clearCart() {
        try {
            await fetch('./api/clear_cart.php', { method: 'POST' });
            showConfirmation('Cart cleared!', 'info');
            fetchAndDisplayCartItems();
        } catch { showConfirmation('Failed to clear cart.', 'error'); }
    }

    // --- Payment Flow & Printing ---

    function resetPaymentModal() {
        step1Div.classList.remove('hidden');
        step2Div.classList.add('hidden');
        step3Div.classList.add('hidden');
        nfcIdInput.value = '';
        passwordInput.value = '';
        step1Message.textContent = '';
        step3Message.textContent = '';
        paymentData = {};
    }

    payOrderBtn.addEventListener('click', () => {
        if (cart.length === 0) {
            showConfirmation('Cart is empty.', 'info');
            return;
        }
        resetPaymentModal();
        paymentFlowModal.classList.remove('hidden');
        nfcIdInput.focus();
    });

    function closePaymentModal() { paymentFlowModal.classList.add('hidden'); }
    closePaymentModalBtn.addEventListener('click', closePaymentModal);
    confirmCancelBtn.addEventListener('click', closePaymentModal);

    nfcScanProceedBtn.addEventListener('click', async () => {
        const nfcId = nfcIdInput.value.trim();
        if (!nfcId) {
            step1Message.textContent = 'NFC ID cannot be empty.';
            return;
        }
        nfcScanProceedBtn.disabled = true;
        nfcScanProceedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        try {
            const response = await fetch('./api/nfc_login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nfc_id: nfcId }) });
            const result = await response.json();

            if (result.success) {
                paymentData = { nfcId, studentName: result.student_name, balance: parseFloat(result.student_balance), bill: parseFloat(cartTotalSpan.textContent.replace('Rs. ', '')) };
                studentNameSpan.textContent = paymentData.studentName;
                currentBalanceSpan.textContent = `Rs. ${paymentData.balance.toFixed(2)}`;
                totalBillSpan.textContent = `Rs. ${paymentData.bill.toFixed(2)}`;
                
                paymentBillDetails.innerHTML = '';
                cart.forEach(item => {
                    // **FIX**: Using the new 3-column layout classes
                    paymentBillDetails.innerHTML += `
                        <div class="bill-item">
                            <div class="bill-item-col-left">
                                <img src="${item.image_path}" class="bill-item-img" alt="${item.food_name}">
                                <span class="bill-item-name">${item.food_name}</span>
                            </div>
                            <div class="bill-item-col-mid">x ${item.quantity}</div>
                            <div class="bill-item-col-right">Rs. ${(item.quantity * item.price).toFixed(2)}</div>
                        </div>`;
                });

                step1Div.classList.add('hidden');
                step2Div.classList.remove('hidden');
            } else {
                step1Message.textContent = result.message || 'Invalid NFC ID.';
            }
        } catch (e) {
            step1Message.textContent = 'An error occurred.';
        } finally {
            nfcScanProceedBtn.disabled = false;
            nfcScanProceedBtn.innerHTML = '<i class="fas fa-arrow-right mr-2"></i>Proceed';
        }
    });

    confirmProceedBtn.addEventListener('click', () => {
        if (paymentData.balance < paymentData.bill) {
            showConfirmation('Insufficient balance!', 'error');
            closePaymentModal();
            return;
        }
        step2Div.classList.add('hidden');
        step3Div.classList.remove('hidden');
        passwordInput.focus();
    });

    finalPayBtn.addEventListener('click', async () => {
        const password = passwordInput.value;
        if (!password) {
            step3Message.textContent = 'Password is required.';
            return;
        }
        finalPayBtn.disabled = true;
        finalPayBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        try {
            const response = await fetch('./api/process_nfc_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nfc_id: paymentData.nfcId, password, cart_items: cart }) });
            const result = await response.json();

            if (result.success) {
                showConfirmation('Order successful!', 'success');
                closePaymentModal();
                generatePrintableCoupon(result.student_name, result.transaction_id, cart); 
                clearCart();
            } else {
                step3Message.textContent = result.message || 'Payment failed.';
            }
        } catch (e) {
            step3Message.textContent = 'A critical error occurred.';
        } finally {
            finalPayBtn.disabled = false;
            finalPayBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Confirm & Pay';
        }
    });

   function generatePrintableCoupon(studentName, orderId, orderedItems) {
    // 1. Create receipt HTML
    let itemsHtml = orderedItems.map(item => `
        <tr>
            <td style="padding: 2px 3px; text-align: left; border-bottom: 1px dashed #ccc;">${item.food_name}</td>
            <td style="padding: 2px 3px; text-align: center; border-bottom: 1px dashed #ccc;">${item.quantity}</td>
            <td style="padding: 2px 3px; text-align: right; border-bottom: 1px dashed #ccc;">Rs. ${(item.price * item.quantity).toFixed(2)}</td>
        </tr>
    `).join('');

    const total = orderedItems.reduce((sum, item) => sum + (item.price * item.quantity), 0).toFixed(2);

    const receiptHtml = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Order Receipt #${orderId}</title>
        <style>
            @page {
                size: 80mm auto;
                margin: 3mm; /* Controls the outer margins, removing browser headers/footers */
            }
            body {
                font-family: 'monospace', 'Arial', sans-serif; /* Monospace fonts are good for alignment */
                width: 100%;
                color: #000;
                font-size: 8.5pt; /* Slightly smaller base font size */
                line-height: 1.2; /* Tighter line spacing */
            }
            .header, .footer { text-align: center; }
            .header { border-bottom: 1px dashed #000; padding-bottom: 3px; margin-bottom: 8px; } /* Reduced padding/margin */
            .header h2 { margin: 0; font-size: 12pt; } /* Further adjusted header font size */
            .header p, .info p { margin: 1px 0; } /* Tighter margins for info lines */
            table { width: 100%; border-collapse: collapse; margin-top: 5px; }
            /* Adjusted padding for table headers and cells for better spacing */
            th, td {
                padding: 2px 3px; /* Slightly reduced padding to gain space */
                border-bottom: 1px dashed #000; /* Changed to dashed for more traditional receipt look */
                white-space: nowrap; /* Prevent wrapping for price and quantity */
            }
            th {
                text-align: right; /* Default align right for price and quantity columns */
                font-size: 9pt; /* Slightly smaller font for headers */
            }
            th:first-child { text-align: left; } /* Align first header to the left */

            /* Specific column widths for better control */
            table thead tr th:nth-child(1),
            table tbody tr td:nth-child(1) {
                width: 55%; /* Allocate more width for the item name */
            }
            table thead tr th:nth-child(2),
            table tbody tr td:nth-child(2) {
                width: 15%; /* Width for quantity */
                text-align: center;
            }
            table thead tr th:nth-child(3),
            table tbody tr td:nth-child(3) {
                width: 30%; /* Width for price */
                text-align: right;
            }

            .total {
                font-weight: bold;
                text-align: right;
                margin-top: 8px; /* Reduced margin */
                font-size: 10.5pt; /* Adjusted total font size */
                padding-right: 3px; /* Ensure padding on the right for total */
            }
            .footer { margin-top: 10px; } /* Reduced margin */
        </style>
    </head>
    <body>
        <div class="header">
            <h2>United Technical Khaja Ghar</h2>
            <p>Order Receipt</p>
        </div>
        <div class="info">
            <p><strong>Order #:</strong> ${orderId}</p>
            <p><strong>Student:</strong> ${studentName}</p>
            <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                </tr>
            </thead>
            <tbody>${itemsHtml}</tbody>
        </table>
        <div class="total">Total: Rs. ${total}</div>
        <div class="footer"><p>Thank you for your order!</p></div>
    </body>
    </html>`;

    // 2. Create a hidden iframe and print its content
    const iframe = document.createElement('iframe');
    iframe.style.position = 'absolute';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = 'none';
    iframe.style.left = '-9999px';
    document.body.appendChild(iframe);

    iframe.contentDocument.open();
    iframe.contentDocument.write(receiptHtml);
    iframe.contentDocument.close();

    // Wait for content to load before printing
    iframe.onload = function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000); // Give a little time for the print dialog to appear before removing
    };
}


    clearCartBtn.addEventListener('click', clearCart);
    fetchAndDisplayFoodItems();
    fetchAndDisplayCartItems();
});
