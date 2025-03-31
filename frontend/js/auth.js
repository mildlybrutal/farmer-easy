// Add this file to handle authentication
const API_URL = 'http://localhost:8000';

async function handleLogin(email, password, role) {
    try {
        const response = await fetch(`${API_URL}/api/login`, {
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
        
        // Redirect based on role
        switch(data.user.role) {
            case 'farmer':
                window.location.href = '/farmer/dashboard.html';
                break;
            case 'retailer':
                window.location.href = '/retailer/dashboard.html';
                break;
            case 'public':
                window.location.href = '/products.html';
                break;
        }
    } catch (error) {
        showError(error.message);
    }
}

async function handleRegister(userData) {
    try {
        const response = await fetch(`${API_URL}/api/users`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);
        
        showSuccess('Registration successful! Please login.');
        showLoginForm();
    } catch (error) {
        showError(error.message);
    }
} 