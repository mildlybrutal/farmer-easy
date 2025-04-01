// Add this file to handle authentication
const API_URL = 'http://localhost:8000';

// Add form validation functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    return password.length >= 6;
}

async function handleLogin(email, password, role) {
    if (!validateEmail(email)) {
        throw new Error('Please enter a valid email address');
    }

    if (!validatePassword(password)) {
        throw new Error('Password must be at least 6 characters long');
    }

    try {
        const response = await fetch(`${API_URL}/api/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password, role })
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);
        
        localStorage.setItem('token', data.session_id);
        localStorage.setItem('user', JSON.stringify(data.user));
        
        showSuccess('Login successful! Redirecting...');
        
        // Redirect after a short delay to show the success message
        setTimeout(() => {
            switch(data.user.role) {
                case 'farmer':
                    window.location.href = '/farmer/dashboard.html';
                    break;
                case 'retailer':
                    window.location.href = '/retailer/dashboard.html';
                    break;
                case 'consumer':
                    window.location.href = '/products.html';
                    break;
                default:
                    throw new Error('Invalid user role');
            }
        }, 1500);
    } catch (error) {
        throw error;
    }
}

async function handleRegister(event) {
    event.preventDefault();
    
    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const role = document.querySelector('input[name="registerUserType"]:checked')?.value;
    
    try {
        // Validate inputs
        if (!name || !email || !password || !role) {
            throw new Error('All fields are required');
        }
        
        if (!validateEmail(email)) {
            throw new Error('Please enter a valid email address');
        }
        
        if (!validatePassword(password)) {
            throw new Error('Password must be at least 6 characters long');
        }
        
        const response = await fetch(`${API_URL}/api/auth/register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name, email, password, role })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Registration failed');
        }
        
        // Store user data in localStorage
        localStorage.setItem('user', JSON.stringify(data.user));
        
        showSuccess('Registration successful! Redirecting...');
        
        // Redirect based on role
        setTimeout(() => {
            switch(role) {
                case 'farmer':
                    window.location.href = '/farmer/dashboard.html';
                    break;
                case 'retailer':
                    window.location.href = '/retailer/dashboard.html';
                    break;
                case 'consumer':
                    window.location.href = '/products.html';
                    break;
                default:
                    throw new Error('Invalid user role');
            }
        }, 1500);
        
    } catch (error) {
        showError(error.message);
        // Reset form
        document.getElementById('registerForm').reset();
    }
}

function showLoginForm() {
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
    loginBtn.classList.add('border-b-2', 'border-primary', 'text-primary');
    registerBtn.classList.remove('border-b-2', 'border-primary', 'text-primary');
    registerBtn.classList.add('text-gray-500');
}

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = message;
    errorDiv.classList.remove('hidden');
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 3000);
}

function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    successDiv.textContent = message;
    successDiv.classList.remove('hidden');
    setTimeout(() => {
        successDiv.classList.add('hidden');
    }, 3000);
}

// Update the form submission handlers in the DOMContentLoaded event listener

// Replace the login form submission handler:
loginForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Signing in...';
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const roleElement = document.querySelector('input[name="loginUserType"]:checked');
        const role = roleElement ? roleElement.value : null;
        
        if (!role) {
            showError('Please select a user type');
            return;
        }
        
        await handleLogin(email, password, role);
    } catch (error) {
        showError(error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// Replace the register form submission handler:
document.getElementById('registerForm').addEventListener('submit', handleRegister);

// Add radio button styling on selection
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove highlight from all labels in the same group
        const name = this.getAttribute('name');
        document.querySelectorAll(`input[name="${name}"]`).forEach(input => {
            input.closest('label').classList.remove('border-primary', 'bg-green-50');
        });
        
        // Add highlight to selected label
        if (this.checked) {
            this.closest('label').classList.add('border-primary', 'bg-green-50');
        }
    });
}); 