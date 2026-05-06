<?php
// Extended Gemini proxy for all food/nutrition computation
// Actions:
//   - summary: POST meal entry, get nutrition summary
//   - search: GET/POST to search foods
//   - getFood: GET to fetch full food details
//   - estimateNutrients: POST ingredients array to estimate calories/macros
//   - generateEffects: POST meal data to generate RPG effects
// Set environment variables:
//   GEMINI_API_KEY - API key
//   GEMINI_MODEL - optional model (default: text-bison-001)

header('Content-Type: application/json');

// Simple .env loader — if you prefer an env file, create a `.env` in project root
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = preg_replace('/^(["\'])(.*)\\1$/', '$2', $v);
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

// Initialize cache directory
$cacheDir = __DIR__ . '/../cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$apiKey = 'AIzaSyB0remFe0g6ZNihb7g40JE41CopRcsqwrE';
// Accept API key from request for quick cURL testing:
// - Header: X-GEMINI-API-KEY: <key>
// - Authorization: Bearer <key>
// - Query/post param: key=<key>
if (!empty($_SERVER['HTTP_X_GEMINI_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_GEMINI_API_KEY'];
}
// Authorization: Bearer <key>
if (!$apiKey && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $apiKey = $m[1];
    }
}
// Some servers place the header into REDIRECT_HTTP_AUTHORIZATION
if (!$apiKey && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $m)) {
        $apiKey = $m[1];
    }
}
// Allow quick ?key= or POST {"key":"..."}
if (!$apiKey && isset($_REQUEST['key'])) {
    $apiKey = $_REQUEST['key'];
}
// Fallback to environment variable (keeps existing behavior)
if (!$apiKey) {
    $apiKey = getenv('GEMINI_API_KEY');
}

$model = null;
// Allow model override via header `X-GEMINI-MODEL` or query `?model=` for quick testing
if (!empty($_SERVER['HTTP_X_GEMINI_MODEL'])) {
    $model = $_SERVER['HTTP_X_GEMINI_MODEL'];
}
if (!$model && isset($_REQUEST['model'])) {
    $model = $_REQUEST['model'];
}
// Fallback to environment or default to Gemini 3.5 Flash
if (!$model) {
    $model = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';
}

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(["error" => "Server not configured: set GEMINI_API_KEY environment variable"]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'summary';
$cacheDir = __DIR__ . '/../cache';

// Route to appropriate handler
switch ($action) {
    case 'diag':
        handleDiag();
        break;
    case 'search':
        handleFoodSearch();
        break;
    case 'getFood':
        handleGetFood();
        break;
    case 'estimateNutrients':
        handleEstimateNutrients();
        break;
    case 'generateEffects':
        handleGenerateEffects();
        break;
    case 'summary':
    default:
        handleMealSummary();
        break;
}

function getCacheKey($prefix, $data) {
    return md5($prefix . json_encode($data));
}

function readCache($key, $maxAge = 86400) {
    global $cacheDir;
    $file = $cacheDir . '/' . $key . '.json';
    if (file_exists($file) && (time() - filemtime($file) < $maxAge)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function writeCache($key, $data) {
    global $cacheDir;
    @file_put_contents($cacheDir . '/' . $key . '.json', json_encode($data));
}

function callGemini($prompt) {
    global $apiKey, $model;
    
    $requestBody = [
        'prompt' => [ 'text' => $prompt ],
        'temperature' => 0.3,
        'maxOutputTokens' => 512,
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta2/models/" . urlencode($model) . ":generateText";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        return [
            'error' => 'API error',
            'curl_error' => $err,
            'http_code' => $code,
            'response' => $resp,
        ];
    }

    $decoded = json_decode($resp, true);
    $generated = null;
    
    if (isset($decoded['candidates']) && is_array($decoded['candidates'])) {
        $candidate = $decoded['candidates'][0];
        if (isset($candidate['display'])) {
            $generated = $candidate['display'];
        } elseif (isset($candidate['content'])) {
            $generated = is_string($candidate['content']) ? $candidate['content'] : json_encode($candidate['content']);
        }
    }

    return ['ok' => true, 'text' => $generated ?: $resp, 'raw' => $decoded];
}

function handleFoodSearch() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? [];
    $query = $payload['query'] ?? $_GET['q'] ?? '';

    if (!$query) {
        http_response_code(400);
        echo json_encode(["error" => "Missing query parameter"]);
        exit;
    }

    $cacheKey = getCacheKey('search', $query);
    $cached = readCache($cacheKey, 3600); // 1 hour cache
    if ($cached) {
        echo json_encode(["ok" => true, "cached" => true, "results" => $cached]);
        exit;
    }

    $prompt = "You are a Filipino food expert. User is searching for food matching '$query'. Return a JSON array of 8-10 Filipino dishes or meals that match the search. Include ONLY these fields per item: name (string), category (string), estimatedCalories (number), tags (array of strings). Be consistent with Filipino cuisine. Output ONLY valid JSON array, no other text.";

    $result = callGemini($prompt);
    if (isset($result['error'])) {
        http_response_code(502);
        echo json_encode($result);
        exit;
    }

    $text = $result['text'];
    // Try to extract JSON from the response
    if (preg_match('/\[.*\]/s', $text, $matches)) {
        $json = $matches[0];
        $foods = json_decode($json, true);
        if (is_array($foods)) {
            writeCache($cacheKey, $foods);
            echo json_encode(["ok" => true, "results" => $foods]);
            exit;
        }
    }

    http_response_code(502);
    echo json_encode(["error" => "Failed to parse Gemini response", "raw" => $text]);
}

function handleGetFood() {
    $foodName = $_GET['name'] ?? '';
    if (!$foodName) {
        http_response_code(400);
        echo json_encode(["error" => "Missing name parameter"]);
        exit;
    }

    $cacheKey = getCacheKey('food', $foodName);
    $cached = readCache($cacheKey, 86400); // 24 hour cache
    if ($cached) {
        echo json_encode(["ok" => true, "cached" => true, "food" => $cached]);
        exit;
    }

    $prompt = "You are a Filipino food expert and nutritionist. For the dish '$foodName', return a JSON object with ONLY these fields: name (string), category (string), calories (number), price (number, in Philippine pesos), tags (array), ingredients (array of strings), effects (object with hp, energy, strength, defense, risk, xp as numbers 0-20). Consider typical preparation. Output ONLY valid JSON object, no other text.";

    $result = callGemini($prompt);
    if (isset($result['error'])) {
        http_response_code(502);
        echo json_encode($result);
        exit;
    }

    $text = $result['text'];
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $json = $matches[0];
        $food = json_decode($json, true);
        if (is_array($food)) {
            writeCache($cacheKey, $food);
            echo json_encode(["ok" => true, "food" => $food]);
            exit;
        }
    }

    http_response_code(502);
    echo json_encode(["error" => "Failed to parse Gemini response", "raw" => $text]);
}

function handleEstimateNutrients() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!$payload || !isset($payload['ingredients'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ingredients array"]);
        exit;
    }

    $ingredients = $payload['ingredients']; // array of {name, servingSize}
    $cacheKey = getCacheKey('nutrients', $ingredients);
    $cached = readCache($cacheKey, 3600);
    if ($cached) {
        echo json_encode(["ok" => true, "cached" => true, "nutrients" => $cached]);
        exit;
    }

    $ingredientList = array_map(fn($ing) => "{$ing['name']} (x{$ing['servingSize']})", $ingredients);
    $ingredientStr = implode(', ', $ingredientList);

    $prompt = "You are a nutritionist. Given these Filipino meal ingredients with serving sizes: $ingredientStr. Estimate total calories and macro breakdown (protein/carbs/fat in grams). Return ONLY a JSON object: {calories: number, protein: number, carbs: number, fat: number}. No other text.";

    $result = callGemini($prompt);
    if (isset($result['error'])) {
        http_response_code(502);
        echo json_encode($result);
        exit;
    }

    $text = $result['text'];
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $json = $matches[0];
        $nutrients = json_decode($json, true);
        if (is_array($nutrients)) {
            writeCache($cacheKey, $nutrients);
            echo json_encode(["ok" => true, "nutrients" => $nutrients]);
            exit;
        }
    }

    http_response_code(502);
    echo json_encode(["error" => "Failed to parse nutrients response", "raw" => $text]);
}

function handleGenerateEffects() {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!$payload || !isset($payload['mealName'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing mealName"]);
        exit;
    }

    $mealName = $payload['mealName'];
    $ingredients = json_encode($payload['ingredients'] ?? []);
    $calories = $payload['calories'] ?? 300;

    $cacheKey = getCacheKey('effects', $mealName . $ingredients);
    $cached = readCache($cacheKey, 86400);
    if ($cached) {
        echo json_encode(["ok" => true, "cached" => true, "effects" => $cached]);
        exit;
    }

    $prompt = "You are an RPG game designer for a Filipino food health tracker. For meal '$mealName' with ingredients: $ingredients (~$calories calories), generate RPG stat effects. Each stat is 0-20. Consider: vegetarian/balance -> Defense/HP, protein -> Strength, fried -> Risk, healthy -> Energy. Return ONLY JSON: {hp: number, energy: number, strength: number, defense: number, risk: number, xp: number}. No other text.";

    $result = callGemini($prompt);
    if (isset($result['error'])) {
        http_response_code(502);
        echo json_encode($result);
        exit;
    }

    $text = $result['text'];
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $json = $matches[0];
        $effects = json_decode($json, true);
        if (is_array($effects) && isset($effects['hp'])) {
            // Clamp values to 0-20 range
            $effects = array_map(fn($v) => max(0, min(20, intval($v))), $effects);
            writeCache($cacheKey, $effects);
            echo json_encode(["ok" => true, "effects" => $effects]);
            exit;
        }
    }

    http_response_code(502);
    echo json_encode(["error" => "Failed to parse effects response", "raw" => $text]);
}

function handleDiag() {
    global $apiKey, $model;

    // Require API key for diag
    if (!$apiKey) {
        http_response_code(400);
        echo json_encode(["error" => "Missing API key. Provide via X-GEMINI-API-KEY, Authorization Bearer, or ?key="]);
        exit;
    }

    // Small diagnostic prompt to check connectivity and auth
    $prompt = "Ping: please reply with the single word 'pong'";
    $result = callGemini($prompt);

    // Return raw result for debugging (do not leak server secrets)
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'note' => 'Diagnostic result from callGemini; check http_code and curl_error for issues',
        'api_key_present' => $apiKey ? true : false,
        'model' => $model,
        'result' => $result,
    ]);
    exit;
}

function handleMealSummary() {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        http_response_code(400);
        echo json_encode(["error" => "Missing request body"]);
        exit;
    }

    $payload = json_decode($raw, true);
    if ($payload === null) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON"]);
        exit;
    }

    $mealJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $prompt = "You are a helpful nutrition assistant. Given the meal log JSON below, return a concise summary (1-2 sentences), list the top 3 contributing ingredients by calories, and suggest one micro-tip to make the meal healthier. Output as JSON with keys: summary, top_ingredients (array of {name, calories}), tip.\n\nMeal JSON:\n" . $mealJson;

    $result = callGemini($prompt);
    if (isset($result['error'])) {
        http_response_code(502);
        echo json_encode($result);
        exit;
    }

    echo json_encode(["ok" => true, "raw" => $result['raw'] ?? [], "text" => $result['text']]);
}

?>
