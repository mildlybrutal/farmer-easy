<?php
function testEndpoint($method, $endpoint, $data = null, $token = null) {
    $ch = curl_init("http://localhost/farmer-portal/backend" . $endpoint);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ];
    
    if ($data && ($method === 'POST' || $method === 'PUT')) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Test cases
echo "Running API tests...\n\n";

// 1. User Registration Test
$userData = [
    'name' => 'Test Farmer',
    'email' => 'test.farmer@example.com',
    'password' => 'password123',
    'role' => 'farmer'
];

echo "1. Testing User Registration:\n";
$result = testEndpoint('POST', '/api/users', $userData);
print_r($result);

// 2. User Login Test
$loginData = [
    'email' => 'test.farmer@example.com',
    'password' => 'password123'
];

echo "\n2. Testing User Login:\n";
$result = testEndpoint('POST', '/api/login', $loginData);
$token = $result['response']['session_id'] ?? null;
print_r($result);

if (!$token) {
    die("\nLogin failed, cannot continue testing\n");
}

// 3. Create Project Test (as retailer)
$projectData = [
    'title' => 'Test Project',
    'description' => 'This is a test project',
    'deadline' => '2024-12-31'
];

echo "\n3. Testing Project Creation:\n";
$result = testEndpoint('POST', '/api/projects', $projectData, $token);
$projectId = $result['response']['id'] ?? null;
print_r($result);

// 4. Get Open Projects Test
echo "\n4. Testing Get Open Projects:\n";
$result = testEndpoint('GET', '/api/projects?status=open', null, $token);
print_r($result);

// 5. Submit Bid Test
if ($projectId) {
    $bidData = [
        'project_id' => $projectId,
        'amount' => 1000,
        'proposal' => 'This is a test bid'
    ];

    echo "\n5. Testing Bid Submission:\n";
    $result = testEndpoint('POST', '/api/bids', $bidData, $token);
    $bidId = $result['response']['id'] ?? null;
    print_r($result);
}

// 6. Get Project Bids Test
if ($projectId) {
    echo "\n6. Testing Get Project Bids:\n";
    $result = testEndpoint('GET', "/api/projects/$projectId/bids", null, $token);
    print_r($result);
}

// 7. Send Message Test
if ($projectId) {
    $messageData = [
        'project_id' => $projectId,
        'receiver_id' => 1, // Assuming receiver ID 1 exists
        'content' => 'This is a test message'
    ];

    echo "\n7. Testing Message Sending:\n";
    $result = testEndpoint('POST', '/api/messages', $messageData, $token);
    print_r($result);
}

// 8. Get Messages Test
if ($projectId) {
    echo "\n8. Testing Get Messages:\n";
    $result = testEndpoint('GET', "/api/messages?project_id=$projectId", null, $token);
    print_r($result);
} 