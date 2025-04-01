<?php
return [
    'session_timeout' => 3600, // 1 hour
    'password_min_length' => 8,
    'allowed_roles' => ['farmer', 'retailer', 'consumer'],
    'csrf_token_expiry' => 1800, // 30 minutes
];
