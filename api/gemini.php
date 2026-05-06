<?php
header('Content-Type: application/json');
// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');

// Gemini API Configuration
// API key can be provided via JSON body as `api_key`, or use the default below
$default_api_key = 'API_KEY_PLACEHOLDER';

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$api_key = $input['api_key'] ?? $default_api_key;

if (!isset($input['message']) || empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$message = $input['message'];

// Gemini v1beta generateContent endpoint (model: gemini-3.1-flash-lite-preview)
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key=' . $api_key;

$data = [
    'contents' => [
        [
            'parts' => [
                [ 'text' => $message ]
            ]
        ]
    ]
];

try {
    if (!function_exists('curl_init')) {
        throw new Exception('cURL extension is not installed. Enable cURL in PHP.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // testing - update for production
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('cURL Error: ' . $curl_error);
    }

    if (!$response) {
        throw new Exception('No response from Gemini API');
    }

    $result = json_decode($response, true);

    if ($http_code >= 400) {
        http_response_code($http_code);
        if (isset($result['error'])) {
            echo json_encode(['error' => $result['error']['message'] ?? 'API Error']);
        } else {
            echo json_encode(['error' => 'API returned error code: ' . $http_code]);
        }
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

