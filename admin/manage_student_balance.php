<?php
date_default_timezone_set('Asia/Kathmandu');
session_start();

if (!isset($_SESSION['admin_id'])){
    header('Location: login.php');
    exit();
}

include '../includes/packages.php';
include '../includes/admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Student Balance</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            transition: background-color 0.3s, color 0.3s;
            background-color: #f8f8f8; /* Explicitly set light background */
            color: #1a202c; /* Explicitly set dark text color */
        }

        .form-container {
            max-width: 560px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s, box-shadow 0.3s;
        }

        /* Removed .dark .form-container */

        .form-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
            color: #1a202c;
        }

        /* Removed .dark .form-title */

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #4a5568;
        }

        /* Removed .dark .form-group label */

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #fff;
            color: #2d3748;
        }

        .form-input::placeholder {
            color: #a0aec0;
            font-style: italic;
        }

        /* Removed .dark .form-input */
        /* Removed .dark .form-input::placeholder */

        .form-input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5);
            outline: none;
        }

        .input-description {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 0.25rem;
        }

        /* Removed .dark .input-description */

        .student-info-display {
            background-color: #f7fafc;
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            overflow: hidden;
            color: #2d3748;
        }

        /* Removed .dark .student-info-display */

        .student-info-display.hidden-visually {
            opacity: 0;
            max-height: 0;
            padding: 0;
            margin-bottom: 0;
            border-width: 0;
        }

        .student-info-display p {
            margin-bottom: 0.375rem;
        }

        .student-info-display strong {
            color: #1f2937;
            font-weight: 600;
        }

        /* Removed .dark .student-info-display strong */

        .student-info-display .loading-text {
            font-style: italic;
            color: #6b7280;
        }

        /* Removed .dark .student-info-display .loading-text */

        .student-info-display .error-text {
            font-style: italic;
            color: #c53030;
        }

        /* Removed .dark .student-info-display .error-text */

        .message-feedback {
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid transparent;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .message-feedback.hidden-visually {
            opacity: 0;
            max-height: 0;
            padding: 0;
            margin-bottom: 0;
            border-width: 0;
        }

        .message-feedback.success {
            background-color: #f0fff4;
            color: #2f855a;
            border-color: #9ae6b4;
        }

        /* Removed .dark .message-feedback.success */

        .message-feedback.error {
            background-color: #fff5f5;
            color: #c53030;
            border-color: #feb2b2;
        }

        /* Removed .dark .message-feedback.error */

        .btn-submit {
            background-color: #4299e1;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit:hover {
            background-color: #3182ce;
        }

        .btn-submit:disabled {
            background-color: #a0aec0;
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Removed .dark .btn-submit */
        /* Removed .dark .btn-submit:hover */
        /* Removed .dark .btn-submit:disabled */

        .spinner {
            display: inline-block;
            width: 1.25em;
            height: 1.25em;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5em;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 font-sans">
    <div class="form-container">
        <h1 class="form-title">Update Student Card Balance</h1>

        <div id="balance-message" class="message-feedback hidden-visually" role="alert" aria-live="assertive"></div>

        <form id="updateBalanceForm" novalidate>
            <div class="form-group">
                <label for="nfc_id">NFC Card ID:</label>
                <input type="text" id="nfc_id" name="nfc_id" class="form-input" required placeholder="Scan or Enter NFC Card ID">
                <small id="nfc_id_status" class="input-description" aria-live="polite"></small>
            </div>

            <div id="studentInfoDisplay" class="student-info-display hidden-visually"></div>

            <div class="form-group">
                <label for="amount">Amount to Add/Deduct (NPR):</label>
                <input type="number" id="amount" name="amount" class="form-input" step="100" required placeholder="e.g., 100 or -100">
                <small class="input-description">
                    Positive value to add funds, negative to deduct. Use arrows for NPR 100 increments.
                </small>
            </div>
            <div class="form-group text-center">
                <button type="submit" id="submitBtn" class="btn-submit">
                    <span class="btn-text">Update Balance</span>
                    <span class="btn-spinner" style="display: none;"><div class="spinner"></div>Processing...</span>
                </button>
            </div>
        </form>
    </div>

    <?php // Removed include '../includes/footer.php'; ?>

    <script>
        // DOM Elements
        const balanceForm = document.getElementById('updateBalanceForm');
        const messageDiv = document.getElementById('balance-message');
        const nfcIdInput = document.getElementById('nfc_id');
        const nfcIdStatus = document.getElementById('nfc_id_status');
        const studentInfoDiv = document.getElementById('studentInfoDisplay');
        const amountInput = document.getElementById('amount');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnSpinner = submitBtn.querySelector('.btn-spinner');

        let fetchTimeout;
        const DEBOUNCE_DURATION = 700;

        // UI Functions
        function setButtonLoading(isLoading) {
            submitBtn.disabled = isLoading;
            btnText.style.display = isLoading ? 'none' : 'inline';
            btnSpinner.style.display = isLoading ? 'inline-flex' : 'none';
        }

        function showHideElement(element, show) {
            element.classList.toggle('hidden-visually', !show);
        }
        
        function displayMessage(message, type = 'error') {
            messageDiv.textContent = message;
            messageDiv.className = `message-feedback ${type}`;
            showHideElement(messageDiv, true);
        }

        function updateStudentInfoUI(data) {
            let content = '';
            if (data.isLoading) {
                content = `<p class="loading-text">Loading student details...</p>`;
            } else if (data.error) {
                content = `<p class="error-text">${data.message || 'NFC ID not found or error.'}</p>`;
                nfcIdStatus.textContent = data.message || 'NFC ID not found.';
            } else if (data.success) {
                content = `
                    <p><strong>Student:</strong> <span id="studentNameDisplay">${data.full_name}</span></p>
                    <p><strong>Current Balance:</strong> NPR <span id="studentBalanceDisplay">${data.current_balance}</span></p>
                `;
                nfcIdStatus.textContent = `Student found: ${data.full_name}.`;
                amountInput.focus();
            }
            
            studentInfoDiv.innerHTML = content;
            showHideElement(studentInfoDiv, content !== '');
        }

        // Event Listeners
        nfcIdInput.addEventListener('input', function() {
            clearTimeout(fetchTimeout);
            const nfcId = this.value.trim();
            showHideElement(messageDiv, false);

            if (!nfcId) {
                showHideElement(studentInfoDiv, false);
                nfcIdStatus.textContent = "Enter an NFC Card ID.";
                return;
            }

            if (nfcId.length > 2) {
                nfcIdStatus.textContent = "Checking NFC ID...";
                updateStudentInfoUI({ isLoading: true });

                fetchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch(`./api/fetch_nfc_card_info.php?nfc_id=${encodeURIComponent(nfcId)}`);
                        const data = await response.json();
                        updateStudentInfoUI(response.ok ? data : { error: true, message: data.message || `Error: ${response.statusText}` });
                    } catch (error) {
                        console.error('Error:', error);
                        updateStudentInfoUI({ error: true, message: 'Network error or invalid response.' });
                    }
                }, DEBOUNCE_DURATION);
            } else {
                showHideElement(studentInfoDiv, false);
                nfcIdStatus.textContent = "NFC ID is too short.";
            }
        });

        balanceForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            showHideElement(messageDiv, false);
            setButtonLoading(true);

            try {
                const response = await fetch('./api/update_student_balance.php', {
                    method: 'POST',
                    body: new FormData(balanceForm)
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error("JSON parse error:", e);
                    displayMessage(`Invalid server response. Raw: ${responseText.substring(0,100)}...`, 'error');
                    return;
                }

                if (response.ok && data.success) {
                    displayMessage(data.message || 'Success! Balance updated.', 'success');
                    balanceForm.reset();
                    showHideElement(studentInfoDiv, false);
                    nfcIdStatus.textContent = "";
                    nfcIdInput.focus();
                } else {
                    displayMessage(data.message || 'Failed to update balance.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                displayMessage('An unexpected error occurred.', 'error');
            } finally {
                setButtonLoading(false);
            }
        });
    </script>
</body>
</html>
