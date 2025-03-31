<?php
/**
 * API Testing Script for Farmer Portal
 * 
 * This script helps test the API endpoints for the bidding system
 */

// Configuration
$baseUrl = 'http://localhost:8000';
$cookieFile = __DIR__ . '/cookies.txt';

// Test user credentials
$retailerCredentials = [
    'email' => 'retailer@example.com',
    'password' => 'password123'
];

$farmerCredentials = [
    'email' => 'farmer@example.com',
    'password' => 'password123'
];

// Function to make API requests
function makeRequest($url, $method = 'GET', $data = null, $useCookie = true, $saveCookie = false) {
    global $cookieFile;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    // Set headers
    $headers = ['Content-Type: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Set cookie options
    if ($useCookie) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($saveCookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }
    
    // Set data for POST/PUT requests
    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response ? json_decode($response, true) : null,
        'raw' => $response
    ];
}

// Function to print test results
function printResult($testName, $result) {
    echo "\n=== $testName ===\n";
    echo "Status Code: " . $result['code'] . "\n";
    
    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo "SUCCESS: ";
    } else {
        echo "ERROR: ";
    }
    
    echo json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
    echo "=====================\n";
}

// Clear previous cookie file
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

// Test 1: Login as Retailer
$loginResult = makeRequest(
    $baseUrl . '/api/auth/login',
    'POST',
    $retailerCredentials,
    false,
    true
);
printResult('Login as Retailer', $loginResult);

// Test 2: Create a Project
$projectData = [
    'title' => 'Test Project ' . date('Y-m-d H:i:s'),
    'description' => 'This is a test project created by the API testing script',
    'deadline' => date('Y-m-d', strtotime('+30 days'))
];

$createProjectResult = makeRequest(
    $baseUrl . '/api/bidding/projects',
    'POST',
    $projectData,
    true,
    true
);
printResult('Create Project', $createProjectResult);

// Get the project ID from the response
$projectId = isset($createProjectResult['response']['project_id']) 
    ? $createProjectResult['response']['project_id'] 
    : null;

// Test 3: Get All Projects
$getProjectsResult = makeRequest(
    $baseUrl . '/api/bidding/projects',
    'GET',
    null,
    true,
    false
);
printResult('Get All Projects', $getProjectsResult);

// Test 4: Get Specific Project (if project was created)
if ($projectId) {
    $getProjectResult = makeRequest(
        $baseUrl . '/api/bidding/projects?id=' . $projectId,
        'GET',
        null,
        true,
        false
    );
    printResult('Get Specific Project', $getProjectResult);
}

// Test 5: Logout as Retailer
$logoutResult = makeRequest(
    $baseUrl . '/api/auth/logout',
    'POST',
    null,
    true,
    true
);
printResult('Logout as Retailer', $logoutResult);

// Test 6: Login as Farmer
$loginFarmerResult = makeRequest(
    $baseUrl . '/api/auth/login',
    'POST',
    $farmerCredentials,
    false,
    true
);
printResult('Login as Farmer', $loginFarmerResult);

// Test 7: Create a Bid (if project was created)
if ($projectId) {
    $bidData = [
        'project_id' => $projectId,
        'price' => 12500,
        'terms' => 'Delivery within 2 weeks',
        'message' => 'I can provide high-quality products at a competitive price.'
    ];
    
    $createBidResult = makeRequest(
        $baseUrl . '/api/bidding/bids',
        'POST',
        $bidData,
        true,
        true
    );
    printResult('Create Bid', $createBidResult);
    
    // Get the bid ID from the response
    $bidId = isset($createBidResult['response']['bid_id']) 
        ? $createBidResult['response']['bid_id'] 
        : null;
    
    // Test 8: Get Bids for Project
    if ($projectId) {
        $getBidsResult = makeRequest(
            $baseUrl . '/api/bidding/bids?project_id=' . $projectId,
            'GET',
            null,
            true,
            false
        );
        printResult('Get Bids for Project', $getBidsResult);
    }
}

// Test 9: Logout as Farmer
$logoutFarmerResult = makeRequest(
    $baseUrl . '/api/auth/logout',
    'POST',
    null,
    true,
    true
);
printResult('Logout as Farmer', $logoutFarmerResult);

echo "\nAPI Testing Completed!\n";
