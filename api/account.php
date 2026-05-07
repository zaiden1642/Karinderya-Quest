<?php
session_start();
// CORS: allow origin that initiated the request so cookies can be included
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Content-Type: application/json');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$action = isset($body['action']) ? trim((string)$body['action']) : '';
$dataFile = __DIR__ . '/../data/users.json';

// Try to connect to DB (optional)
$pdo = null;
try {
    require_once __DIR__ . '/db.php';
    $pdo = db_try_connect();
} catch (Throwable $e) {
    $pdo = null;
}

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

function respond_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function load_users(string $dataFile): array
{
    $raw = file_get_contents($dataFile);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function save_users(string $dataFile, array $users): void
{
    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        respond_json(['error' => 'Failed to encode users'], 500);
    }

    $ok = file_put_contents($dataFile, $json, LOCK_EX);
    if ($ok === false) {
        respond_json(['error' => 'Failed to save users'], 500);
    }
}

function sanitize_user(array $user): array
{
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'birthdate' => $user['birthdate'],
        'height_cm' => $user['height_cm'],
        'weight_kg' => $user['weight_kg'],
        'bmi' => $user['bmi'],
        'created_at' => $user['created_at'],
    ];
}

function compute_bmi(float $heightCm, float $weightKg): float
{
    $heightM = $heightCm / 100;
    if ($heightM <= 0) {
        return 0;
    }
    return round($weightKg / ($heightM * $heightM), 2);
}

function validate_profile_fields(array $input): array
{
    $birthdate = trim((string)($input['birthdate'] ?? ''));
    $height = (float)($input['height_cm'] ?? 0);
    $weight = (float)($input['weight_kg'] ?? 0);

    if (!$birthdate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        respond_json(['error' => 'Birthdate is required (YYYY-MM-DD).'], 422);
    }

    if ($height < 80 || $height > 260) {
        respond_json(['error' => 'Height must be between 80 and 260 cm.'], 422);
    }

    if ($weight < 20 || $weight > 300) {
        respond_json(['error' => 'Weight must be between 20 and 300 kg.'], 422);
    }

    return [$birthdate, $height, $weight, compute_bmi($height, $weight)];
}

$users = load_users($dataFile);

if ($action === 'me') {
    if (!isset($_SESSION['acm_user_id'])) {
        respond_json(['user' => null]);
    }

    $sessId = (int)$_SESSION['acm_user_id'];

    // If we have a DB connection, prefer returning DB-backed user info
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id, username, display_name, xp, level, wellness_score, day_count, email, birthdate, height_cm, weight_kg, bmi FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $sessId]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbUser) {
                // try to find matching JSON profile for fallback fields
                $jsonProfile = null;
                foreach ($users as $u) {
                    if (isset($u['username']) && $u['username'] === $dbUser['username']) {
                        $jsonProfile = $u;
                        break;
                    }
                }

                $out = [
                    'id' => (int)$dbUser['id'],
                    'username' => $dbUser['username'],
                    'display_name' => $dbUser['display_name'] ?? $dbUser['username'],
                    'xp' => (int)($dbUser['xp'] ?? 0),
                    'level' => (int)($dbUser['level'] ?? 1),
                    'wellness_score' => (int)($dbUser['wellness_score'] ?? 0),
                    'day_count' => (int)($dbUser['day_count'] ?? 1),
                ];

                // prefer DB-backed profile fields when available
                $out['email'] = $dbUser['email'] ?? ($jsonProfile['email'] ?? null);
                $out['birthdate'] = $dbUser['birthdate'] ?? ($jsonProfile['birthdate'] ?? null);
                $out['height_cm'] = $dbUser['height_cm'] ?? ($jsonProfile['height_cm'] ?? null);
                $out['weight_kg'] = $dbUser['weight_kg'] ?? ($jsonProfile['weight_kg'] ?? null);
                $out['bmi'] = isset($dbUser['bmi']) ? (float)$dbUser['bmi'] : ($jsonProfile['bmi'] ?? null);

                respond_json(['user' => $out]);
            }
        } catch (Throwable $e) {
            // fall through to JSON fallback
        }
    }

    // JSON fallback: find by JSON id
    foreach ($users as $user) {
        if ((int)$user['id'] === $sessId) {
            respond_json(['user' => sanitize_user($user)]);
        }
    }

    // session id not matched
    unset($_SESSION['acm_user_id']);
    respond_json(['user' => null]);
}

if ($action === 'logout') {
    $sessId = isset($_SESSION['acm_user_id']) ? (int)$_SESSION['acm_user_id'] : null;
    unset($_SESSION['acm_user_id']);
    db_log_activity($pdo, $sessId, 'logout', 'User logged out');
    respond_json(['ok' => true]);
}

if ($action === 'register') {
    $username = trim((string)($body['username'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');

    if (strlen($username) < 3) {
        respond_json(['error' => 'Username must be at least 3 characters.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_json(['error' => 'A valid email is required.'], 422);
    }

    if (strlen($password) < 6) {
        respond_json(['error' => 'Password must be at least 6 characters.'], 422);
    }

    foreach ($users as $existing) {
        if (strtolower((string)$existing['email']) === $email) {
            respond_json(['error' => 'Email is already registered.'], 409);
        }
    }

    [$birthdate, $height, $weight, $bmi] = validate_profile_fields($body);

    $maxId = 0;
    foreach ($users as $u) {
        $maxId = max($maxId, (int)$u['id']);
    }

    $newUser = [
        'id' => $maxId + 1,
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'birthdate' => $birthdate,
        'height_cm' => $height,
        'weight_kg' => $weight,
        'bmi' => $bmi,
        'created_at' => gmdate('c'),
    ];

    $users[] = $newUser;
    save_users($dataFile, $users);

    // Try to create a corresponding DB user (non-fatal) and store richer profile
    $dbId = null;
    if ($pdo) {
        try {
            // Attempt to insert with full profile columns if they exist
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, display_name, email, password_hash, birthdate, height_cm, weight_kg, bmi, created_at) VALUES (:username, :display_name, :email, :password_hash, :birthdate, :height_cm, :weight_kg, :bmi, NOW())');
            $stmt->execute([
                'username' => $username,
                'display_name' => $username,
                'email' => $email,
                'password_hash' => $hashed,
                'birthdate' => $birthdate,
                'height_cm' => $height,
                'weight_kg' => $weight,
                'bmi' => $bmi,
            ]);
            $dbId = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            // If full-profile insert fails (maybe schema lacks columns), fall back to minimal insert or lookup
            try {
                $ins = $pdo->prepare('INSERT INTO users (username, display_name, created_at) VALUES (:username, :display_name, NOW())');
                $ins->execute(['username' => $username, 'display_name' => $username]);
                $dbId = (int)$pdo->lastInsertId();
            } catch (Throwable $ee) {
                try {
                    $sel = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                    $sel->execute(['username' => $username]);
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                    if ($row) $dbId = (int)$row['id'];
                } catch (Throwable $eee) {
                    $dbId = null;
                }
            }
        }
    }

    // Prefer DB id for session if available, otherwise JSON id
    $_SESSION['acm_user_id'] = $dbId ?: $newUser['id'];
    db_log_activity($pdo, (int)$_SESSION['acm_user_id'], 'register', 'User registered', ['username' => $username]);
    respond_json(['user' => sanitize_user($newUser)], 201);
}

if ($action === 'login') {
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        respond_json(['error' => 'Email and password are required.'], 422);
    }

    // If DB available, try DB-backed authentication first
    if ($pdo) {
        try {
            $sel = $pdo->prepare('SELECT id, username, display_name, password_hash, email, birthdate, height_cm, weight_kg, bmi, xp, level, wellness_score, day_count FROM users WHERE email = :email LIMIT 1');
            $sel->execute(['email' => $email]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $dbPass = $row['password_hash'] ?? null;
                if ($dbPass && password_verify($password, (string)$dbPass)) {
                    // successful DB auth
                    $_SESSION['acm_user_id'] = (int)$row['id'];
                    db_log_activity($pdo, (int)$_SESSION['acm_user_id'], 'login', 'User logged in', ['email' => $email]);
                    $out = [
                        'id' => (int)$row['id'],
                        'username' => $row['username'],
                        'display_name' => $row['display_name'] ?? $row['username'],
                        'email' => $row['email'] ?? null,
                        'birthdate' => $row['birthdate'] ?? null,
                        'height_cm' => $row['height_cm'] ?? null,
                        'weight_kg' => $row['weight_kg'] ?? null,
                        'bmi' => isset($row['bmi']) ? (float)$row['bmi'] : null,
                        'xp' => (int)($row['xp'] ?? 0),
                        'level' => (int)($row['level'] ?? 1),
                        'wellness_score' => (int)($row['wellness_score'] ?? 0),
                        'day_count' => (int)($row['day_count'] ?? 1),
                    ];
                    respond_json(['user' => $out]);
                }
                // If DB row exists but no password_hash or verify failed, fall through to JSON check
            }
        } catch (Throwable $e) {
            // ignore and fall back to JSON file
        }
    }

    // Fallback to JSON-based authentication
    foreach ($users as $user) {
        if (strtolower((string)$user['email']) !== $email) {
            continue;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            respond_json(['error' => 'Invalid email or password.'], 401);
        }

        // Upon successful auth, ensure a DB user exists and prefer DB id in session
        $dbId = null;
        if ($pdo) {
            try {
                $sel = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                $sel->execute(['username' => $user['username']]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $dbId = (int)$row['id'];
                } else {
                    $ins = $pdo->prepare('INSERT INTO users (username, display_name, created_at) VALUES (:username, :display_name, NOW())');
                    $ins->execute(['username' => $user['username'], 'display_name' => $user['username']]);
                    $dbId = (int)$pdo->lastInsertId();
                }
            } catch (Throwable $e) {
                $dbId = null;
            }
        }

        $_SESSION['acm_user_id'] = $dbId ?: $user['id'];
        db_log_activity($pdo, (int)$_SESSION['acm_user_id'], 'login', 'User logged in', ['email' => $email]);
        respond_json(['user' => sanitize_user($user)]);
    }

    respond_json(['error' => 'Invalid email or password.'], 401);
}

if ($action === 'update_profile') {
    if (!isset($_SESSION['acm_user_id'])) {
        respond_json(['error' => 'Unauthorized'], 401);
    }

    [$birthdate, $height, $weight, $bmi] = validate_profile_fields($body);

    $found = false;
    foreach ($users as &$user) {
        if ((int)$user['id'] !== (int)$_SESSION['acm_user_id']) {
            continue;
        }

        $user['birthdate'] = $birthdate;
        $user['height_cm'] = $height;
        $user['weight_kg'] = $weight;
        $user['bmi'] = $bmi;
        $found = true;
        $updated = $user;
        break;
    }
    unset($user);

    if (!$found) {
        respond_json(['error' => 'User not found.'], 404);
    }

    save_users($dataFile, $users);
    db_log_activity($pdo, (int)$_SESSION['acm_user_id'], 'profile_update', 'User profile updated');
    respond_json(['user' => sanitize_user($updated)]);
}

if ($action === 'delete_all_data') {
    if (!isset($_SESSION['acm_user_id'])) {
        respond_json(['error' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['acm_user_id'];

    if ($pdo) {
        try {
            // Delete all meal logs for this user
            $stmt = $pdo->prepare('DELETE FROM meal_logs WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            // Delete all quest progress for this user
            $stmt = $pdo->prepare('DELETE FROM quest_progress WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            // Delete user game state
            $stmt = $pdo->prepare('DELETE FROM user_game_states WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            // Reset user stats in users table
            $stmt = $pdo->prepare('UPDATE users SET xp = 0, level = 1, hp = 100, energy = 50, strength = 50, defense = 50, risk = 10, wellness_score = 0, day_count = 0, title = :title WHERE id = :user_id');
            $stmt->execute(['title' => 'Street Rookie', 'user_id' => $userId]);

            db_log_activity($pdo, $userId, 'delete_all_data', 'User deleted all account data and reset stats');
            respond_json(['ok' => true, 'message' => 'All data has been deleted and account reset.']);
        } catch (Throwable $e) {
            respond_json(['error' => 'Failed to delete data: ' . $e->getMessage()], 500);
        }
    } else {
        respond_json(['error' => 'Database connection not available.'], 500);
    }
}

respond_json(['error' => 'Invalid action.'], 400);
