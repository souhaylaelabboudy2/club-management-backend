<?php

return [
    // Which routes should use CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    // Allow all HTTP methods (GET, POST, etc.)
    'allowed_methods' => ['*'],
    
    // Which frontend URLs can access your API
    'allowed_origins' => array_filter([
        'http://localhost:3000',           // Your local React dev
        'http://localhost:5173',           // Vite default port
        env('FRONTEND_URL'),               // Railway frontend (we'll set this)
    ]),
    
    // Also allow any Railway subdomain (*.up.railway.app)
    'allowed_origins_patterns' => [
        '/^https?:\/\/.*\.up\.railway\.app$/',
    ],
    
    // Allow all headers
    'allowed_headers' => ['*'],
    
    // Don't expose extra headers
    'exposed_headers' => [],
    
    // Don't cache CORS preflight requests
    'max_age' => 0,
    
    // 🔴 THIS IS THE MOST IMPORTANT LINE!
    // It tells the browser: "Yes, send cookies with cross-origin requests"
    'supports_credentials' => true,
];