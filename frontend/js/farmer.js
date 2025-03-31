async function loadFarmerDashboard() {
    try {
        // Load products
        const products = await API.request('/api/products?farmer_id=' + getCurrentUserId());
        displayProducts(products);
        
        // Load bids
        const bids = await API.request('/api/bidding/bids?farmer_id=' + getCurrentUserId());
        displayBids(bids);
    } catch (error) {
        showError('Failed to load dashboard data');
    }
}

function displayProducts(products) {
    const productsList = document.getElementById('productsList');
    productsList.innerHTML = products.map(product => `
        <div class="border rounded p-4 mb-4">
            <h3 class="font-bold">${product.name}</h3>
            <p class="text-gray-600">Price: $${product.price}</p>
            <p class="text-gray-600">Stock: ${product.stock}</p>
            <div class="mt-2">
                <button onclick="editProduct(${product.id})" class="text-blue-600">Edit</button>
                <button onclick="deleteProduct(${product.id})" class="text-red-600 ml-2">Delete</button>
            </div>
        </div>
    `).join('');
}

function displayBids(bids) {
    const bidsList = document.getElementById('bidsList');
    bidsList.innerHTML = bids.map(bid => `
        <div class="border rounded p-4 mb-4">
            <h3 class="font-bold">${bid.project_title}</h3>
            <p class="text-gray-600">Amount: $${bid.amount}</p>
            <p class="text-gray-600">Status: ${bid.status}</p>
            <button onclick="viewBidDetails(${bid.id})" class="text-blue-600 mt-2">View Details</button>
        </div>
    `).join('');
} 