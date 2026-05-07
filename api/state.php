<?php
session_start();
// CORS: mirror origin to allow cookies when frontend uses credentials: 'include'
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

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/body_attributes.php';
require_once __DIR__ . '/rewards.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    db_json_response(['error' => 'Method not allowed'], 405);
}

if (!isset($_SESSION['acm_user_id'])) {
    db_json_response(['error' => 'Unauthorized'], 401);
}

$body = json_decode(file_get_contents('php://input'), true);
$action = isset($body['action']) ? trim((string)$body['action']) : '';
$userId = (int)$_SESSION['acm_user_id'];
$pdo = db_connect();

if ($action === 'load_state') {
    $stmt = $pdo->prepare('SELECT state_json FROM user_game_states WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    $state = [];
    if ($row && isset($row['state_json'])) {
        $decoded = json_decode((string)$row['state_json'], true);
        if (is_array($decoded)) {
            $state = $decoded;
        }
    }

    db_json_response(['state' => $state]);
}

if ($action === 'statistics') {
    sync_user_body_attributes($pdo, $userId);
    $userStmt = $pdo->prepare('SELECT id, username, display_name, xp, level, hp, energy, strength, defense, risk, wellness_score, day_count FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute(['user_id' => $userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        db_json_response(['error' => 'User not found'], 404);
    }

    $mealStmt = $pdo->prepare('SELECT COUNT(*) AS meal_count, COALESCE(SUM(calories), 0) AS total_calories, COALESCE(SUM(hp_delta), 0) AS hp_delta, COALESCE(SUM(energy_delta), 0) AS energy_delta, COALESCE(SUM(strength_delta), 0) AS strength_delta, COALESCE(SUM(defense_delta), 0) AS defense_delta, COALESCE(SUM(risk_delta), 0) AS risk_delta FROM meal_logs WHERE user_id = :user_id');
    $mealStmt->execute(['user_id' => $userId]);
    $mealStats = $mealStmt->fetch();

    $questStmt = $pdo->prepare('SELECT COUNT(*) AS completed_quests FROM quest_progress WHERE user_id = :user_id AND completed_at IS NOT NULL');
    $questStmt->execute(['user_id' => $userId]);
    $questStats = $questStmt->fetch();

    $stats = [
        'hp' => (int)($user['hp'] ?? 100),
        'energy' => (int)($user['energy'] ?? 50),
        'strength' => (int)($user['strength'] ?? 50),
        'defense' => (int)($user['defense'] ?? 50),
        'risk' => (int)($user['risk'] ?? 10),
    ];

    $wellnessScore = (int)($user['wellness_score'] ?? 0);

    db_json_response([
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?? $user['username'],
            'xp' => (int)($user['xp'] ?? 0),
            'level' => (int)($user['level'] ?? 1),
            'hp' => $stats['hp'],
            'energy' => $stats['energy'],
            'strength' => $stats['strength'],
            'defense' => $stats['defense'],
            'risk' => $stats['risk'],
            'wellness_score' => $wellnessScore,
            'day_count' => (int)($user['day_count'] ?? 1),
        ],
        'stats' => $stats,
        'summary' => [
            'meal_count' => (int)($mealStats['meal_count'] ?? 0),
            'total_calories' => (int)($mealStats['total_calories'] ?? 0),
            'completed_quests' => (int)($questStats['completed_quests'] ?? 0),
            'wellness_score' => $wellnessScore,
        ],
    ]);
}

if ($action === 'save_state') {
    $state = $body['state'] ?? null;
    if (!is_array($state)) {
        db_json_response(['error' => 'state must be an object'], 422);
    }

    $stateJson = json_encode($state, JSON_UNESCAPED_SLASHES);
    if ($stateJson === false) {
        db_json_response(['error' => 'Failed to encode state'], 500);
    }

    $stmt = $pdo->prepare('INSERT INTO user_game_states (user_id, state_json) VALUES (:user_id, :state_json) ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([
        'user_id' => $userId,
        'state_json' => $stateJson,
    ]);

    $xp = max(0, (int)($body['xp'] ?? 0));
    $level = max(1, (int)($body['level'] ?? 1));
    $title = trim((string)($body['title'] ?? 'Street Rookie'));
    $wellnessScore = max(0, (int)($body['wellness_score'] ?? 0));

    $userUpdate = $pdo->prepare('UPDATE users SET xp = :xp, level = :level, title = :title, wellness_score = :wellness_score WHERE id = :id');
    $userUpdate->execute([
        'xp' => $xp,
        'level' => $level,
        'title' => $title,
        'wellness_score' => $wellnessScore,
        'id' => $userId,
    ]);

    $bodyStats = sync_user_body_attributes($pdo, $userId);

    db_log_activity($pdo, $userId, 'save_state', 'Game state saved', [
        'xp' => $xp,
        'level' => $level,
        'title' => $title,
        'wellness_score' => $wellnessScore,
        'day_count' => (int)($bodyStats['day_count'] ?? 0),
    ]);

    db_json_response(['ok' => true]);
}

if ($action === 'rankings') {
    sync_user_body_attributes($pdo, $userId);
    // Get current user info
    $userStmt = $pdo->prepare('SELECT id, username, display_name, level, xp, wellness_score, day_count FROM users WHERE id = :user_id');
    $userStmt->execute(['user_id' => $userId]);
    $currentUser = $userStmt->fetch();
    
    if (!$currentUser) {
        db_json_response(['error' => 'User not found'], 404);
    }
    
    // Count total completed quests for each user
    $questCountStmt = $pdo->query('
        SELECT user_id, COUNT(DISTINCT DATE(completed_at)) as quest_days_completed, COUNT(*) as total_quests_completed
        FROM quest_progress 
        WHERE completed_at IS NOT NULL
        GROUP BY user_id
    ');
    $questCounts = [];
    foreach ($questCountStmt->fetchAll() as $row) {
        $questCounts[$row['user_id']] = (int)$row['total_quests_completed'];
    }
    
    $currentUserQuestCount = $questCounts[$userId] ?? 0;
    
    // Get all users (limit to top performers)
    $allUsersStmt = $pdo->query('SELECT id, username, display_name, level, xp, wellness_score, day_count FROM users ORDER BY xp DESC LIMIT 10');
    $allUsers = $allUsersStmt->fetchAll();
    
    // Pseudo-users data with varied stats
    $pseudoUsers = [
        ['username' => 'PalabtanKing', 'display_name' => 'Palaban King', 'level' => 1, 'xp' => 1950, 'wellness_score' => 67, 'day_count' => 45, 'quest_completed' => 2],
        ['username' => 'VeggieVibe', 'display_name' => 'Veggie Vibe', 'level' => 3, 'xp' => 1650, 'wellness_score' => 94, 'day_count' => 38, 'quest_completed' => 3],
        ['username' => 'RiceWarrior', 'display_name' => 'Rice Warrior', 'level' => 2, 'xp' => 2250, 'wellness_score' => 40, 'day_count' => 52, 'quest_completed' => 6],
        ['username' => 'CarboChamp', 'display_name' => 'Carbo Champ', 'level' => 1, 'xp' => 1450, 'wellness_score' => 83, 'day_count' => 32, 'quest_completed' => 1],
        ['username' => 'BalanceGod', 'display_name' => 'Balance God', 'level' => 3, 'xp' => 2750, 'wellness_score' => 80, 'day_count' => 60, 'quest_completed' => 8],
    ];
    
    // Rankings by different criteria
    $levelRanking = [];
    $wellnessRanking = [];
    $dayStreakRanking = [];
    $questRanking = [];
    
    // Add pseudo-users to rankings
    foreach ($pseudoUsers as $pu) {
        $levelRanking[] = ['type' => 'pseudo', 'name' => $pu['display_name'], 'value' => $pu['level']];
        $wellnessRanking[] = ['type' => 'pseudo', 'name' => $pu['display_name'], 'value' => $pu['wellness_score']];
        $dayStreakRanking[] = ['type' => 'pseudo', 'name' => $pu['display_name'], 'value' => $pu['day_count']];
        $questRanking[] = ['type' => 'pseudo', 'name' => $pu['display_name'], 'value' => $pu['quest_completed']];
    }
    
    // Add real users to rankings
    foreach ($allUsers as $user) {
        $questCount = $questCounts[$user['id']] ?? 0;
        $levelRanking[] = ['type' => 'real', 'name' => $user['display_name'] ?? $user['username'], 'value' => (int)$user['level']];
        $wellnessRanking[] = ['type' => 'real', 'name' => $user['display_name'] ?? $user['username'], 'value' => (int)$user['wellness_score']];
        $dayStreakRanking[] = ['type' => 'real', 'name' => $user['display_name'] ?? $user['username'], 'value' => (int)$user['day_count']];
        $questRanking[] = ['type' => 'real', 'name' => $user['display_name'] ?? $user['username'], 'value' => $questCount];
    }
    
    // Sort all rankings by value descending
    usort($levelRanking, fn($a, $b) => $b['value'] <=> $a['value']);
    usort($wellnessRanking, fn($a, $b) => $b['value'] <=> $a['value']);
    usort($dayStreakRanking, fn($a, $b) => $b['value'] <=> $a['value']);
    usort($questRanking, fn($a, $b) => $b['value'] <=> $a['value']);
    
    // Limit each ranking to top 10
    $levelRanking = array_slice($levelRanking, 0, 10);
    $wellnessRanking = array_slice($wellnessRanking, 0, 10);
    $dayStreakRanking = array_slice($dayStreakRanking, 0, 10);
    $questRanking = array_slice($questRanking, 0, 10);
    
    // Find current user's rank in each category
    $currentUserLevelRank = 0;
    $currentUserWellnessRank = 0;
    $currentUserDayStreakRank = 0;
    $currentUserQuestRank = 0;
    
    // Find rank for each leaderboard
    foreach ($levelRanking as $idx => $entry) {
        if ($entry['type'] === 'real' && $entry['name'] === ($currentUser['display_name'] ?? $currentUser['username'])) {
            $currentUserLevelRank = $idx + 1;
        }
    }
    foreach ($wellnessRanking as $idx => $entry) {
        if ($entry['type'] === 'real' && $entry['name'] === ($currentUser['display_name'] ?? $currentUser['username'])) {
            $currentUserWellnessRank = $idx + 1;
        }
    }
    foreach ($dayStreakRanking as $idx => $entry) {
        if ($entry['type'] === 'real' && $entry['name'] === ($currentUser['display_name'] ?? $currentUser['username'])) {
            $currentUserDayStreakRank = $idx + 1;
        }
    }
    foreach ($questRanking as $idx => $entry) {
        if ($entry['type'] === 'real' && $entry['name'] === ($currentUser['display_name'] ?? $currentUser['username'])) {
            $currentUserQuestRank = $idx + 1;
        }
    }
    
    db_json_response([
        'rankings' => [
            'levels' => [
                'title' => 'Levels',
                'unit' => 'LVL',
                'board' => $levelRanking,
                'current' => ['rank' => $currentUserLevelRank ?: null, 'value' => (int)$currentUser['level'], 'name' => $currentUser['display_name'] ?? $currentUser['username']],
            ],
            'wellness' => [
                'title' => 'Wellness Score',
                'unit' => 'PTS',
                'board' => $wellnessRanking,
                'current' => ['rank' => $currentUserWellnessRank ?: null, 'value' => (int)$currentUser['wellness_score'], 'name' => $currentUser['display_name'] ?? $currentUser['username']],
            ],
            'dayStreak' => [
                'title' => 'Day Streak',
                'unit' => 'DAYS',
                'board' => $dayStreakRanking,
                'current' => ['rank' => $currentUserDayStreakRank ?: null, 'value' => (int)$currentUser['day_count'], 'name' => $currentUser['display_name'] ?? $currentUser['username']],
            ],
            'quests' => [
                'title' => 'Quests Completed',
                'unit' => 'TOTAL',
                'board' => $questRanking,
                'current' => ['rank' => $currentUserQuestRank ?: null, 'value' => $currentUserQuestCount, 'name' => $currentUser['display_name'] ?? $currentUser['username']],
            ],
        ],
    ]);
}

if ($action === 'rewards') {
    sync_user_body_attributes($pdo, $userId);
    ensure_reward_catalog($pdo);

    $userStmt = $pdo->prepare('SELECT id, username, display_name, level, xp, hp, energy, strength, defense, risk, wellness_score, day_count FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute(['user_id' => $userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        db_json_response(['error' => 'User not found'], 404);
    }

    $topStmt = $pdo->query('SELECT id, username, display_name, wellness_score FROM users ORDER BY wellness_score DESC, xp DESC LIMIT 1');
    $topUser = $topStmt ? $topStmt->fetch() : null;

    $catalogStmt = $pdo->query('SELECT id, code, title, description, trigger_type, trigger_value, reward_kind FROM reward_catalog ORDER BY trigger_value ASC, id ASC');
    $catalog = $catalogStmt ? $catalogStmt->fetchAll() : [];

    $rewardRows = [];
    foreach ($catalog as $reward) {
        $eligibility = reward_eligibility_context($reward, $user, $topUser ?: null);

        $rowStmt = $pdo->prepare('SELECT id, status, reward_json, eligibility_json, unlocked_at, claimed_at FROM user_rewards WHERE user_id = :user_id AND reward_id = :reward_id LIMIT 1');
        $rowStmt->execute(['user_id' => $userId, 'reward_id' => $reward['id']]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row && $eligibility['eligible']) {
            $rewardJson = reward_gemini_payload($reward, $user, $eligibility);
            $insertStmt = $pdo->prepare('INSERT INTO user_rewards (user_id, reward_id, status, reward_json, eligibility_json, unlocked_at) VALUES (:user_id, :reward_id, :status, :reward_json, :eligibility_json, NOW())');
            $insertStmt->execute([
                'user_id' => $userId,
                'reward_id' => $reward['id'],
                'status' => 'unlocked',
                'reward_json' => json_encode($rewardJson, JSON_UNESCAPED_SLASHES),
                'eligibility_json' => json_encode($eligibility, JSON_UNESCAPED_SLASHES),
            ]);
            $rowStmt->execute(['user_id' => $userId, 'reward_id' => $reward['id']]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($row && $eligibility['eligible'] && empty($row['reward_json'])) {
            $rewardJson = reward_gemini_payload($reward, $user, $eligibility);
            $updateStmt = $pdo->prepare('UPDATE user_rewards SET reward_json = :reward_json, eligibility_json = :eligibility_json, status = :status WHERE id = :id');
            $updateStmt->execute([
                'reward_json' => json_encode($rewardJson, JSON_UNESCAPED_SLASHES),
                'eligibility_json' => json_encode($eligibility, JSON_UNESCAPED_SLASHES),
                'status' => 'unlocked',
                'id' => $row['id'],
            ]);
            $row['reward_json'] = json_encode($rewardJson, JSON_UNESCAPED_SLASHES);
            $row['eligibility_json'] = json_encode($eligibility, JSON_UNESCAPED_SLASHES);
            $row['status'] = 'unlocked';
        }

        $rewardRows[] = [
            'id' => (int)$reward['id'],
            'code' => $reward['code'],
            'title' => $reward['title'],
            'description' => $reward['description'],
            'trigger_type' => $reward['trigger_type'],
            'trigger_value' => (int)$reward['trigger_value'],
            'reward_kind' => $reward['reward_kind'],
            'eligible' => (bool)$eligibility['eligible'],
            'status' => $row['status'] ?? ($eligibility['eligible'] ? 'unlocked' : 'locked'),
            'reward_json' => isset($row['reward_json']) ? json_decode((string)$row['reward_json'], true) : null,
            'eligibility' => $eligibility,
            'unlocked_at' => $row['unlocked_at'] ?? null,
            'claimed_at' => $row['claimed_at'] ?? null,
        ];
    }

    db_json_response([
        'rewards' => $rewardRows,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?? $user['username'],
            'level' => (int)($user['level'] ?? 1),
            'day_count' => (int)($user['day_count'] ?? 0),
            'wellness_score' => (int)($user['wellness_score'] ?? 0),
        ],
        'top_wellness' => $topUser ? [
            'id' => (int)$topUser['id'],
            'name' => $topUser['display_name'] ?? $topUser['username'],
            'wellness_score' => (int)($topUser['wellness_score'] ?? 0),
        ] : null,
    ]);
}

if ($action === 'claim_reward') {
    $rewardId = isset($body['reward_id']) ? (int)$body['reward_id'] : 0;
    if ($rewardId <= 0) {
        db_json_response(['error' => 'reward_id is required'], 422);
    }

    sync_user_body_attributes($pdo, $userId);

    $userStmt = $pdo->prepare('SELECT id, username, display_name, level, xp, hp, energy, strength, defense, risk, wellness_score, day_count FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute(['user_id' => $userId]);
    $user = $userStmt->fetch();
    if (!$user) {
        db_json_response(['error' => 'User not found'], 404);
    }

    $rewardStmt = $pdo->prepare('SELECT id, code, title, description, trigger_type, trigger_value, reward_kind FROM reward_catalog WHERE id = :reward_id LIMIT 1');
    $rewardStmt->execute(['reward_id' => $rewardId]);
    $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) {
        db_json_response(['error' => 'Reward not found'], 404);
    }

    $topStmt = $pdo->query('SELECT id, username, display_name, wellness_score FROM users ORDER BY wellness_score DESC, xp DESC LIMIT 1');
    $topUser = $topStmt ? $topStmt->fetch() : null;
    $eligibility = reward_eligibility_context($reward, $user, $topUser ?: null);

    if (!$eligibility['eligible']) {
        db_json_response(['error' => 'Reward is not unlocked yet'], 409);
    }

    $rowStmt = $pdo->prepare('SELECT id, status, reward_json, eligibility_json, unlocked_at, claimed_at FROM user_rewards WHERE user_id = :user_id AND reward_id = :reward_id LIMIT 1');
    $rowStmt->execute(['user_id' => $userId, 'reward_id' => $rewardId]);
    $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$row) {
        $rewardJson = reward_gemini_payload($reward, $user, $eligibility);
        $insertStmt = $pdo->prepare('INSERT INTO user_rewards (user_id, reward_id, status, reward_json, eligibility_json, unlocked_at) VALUES (:user_id, :reward_id, :status, :reward_json, :eligibility_json, NOW())');
        $insertStmt->execute([
            'user_id' => $userId,
            'reward_id' => $rewardId,
            'status' => 'unlocked',
            'reward_json' => json_encode($rewardJson, JSON_UNESCAPED_SLASHES),
            'eligibility_json' => json_encode($eligibility, JSON_UNESCAPED_SLASHES),
        ]);
        $rowStmt->execute(['user_id' => $userId, 'reward_id' => $rewardId]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$row) {
        db_json_response(['error' => 'Unable to create reward entry'], 500);
    }

    if (($row['status'] ?? '') === 'claimed') {
        db_json_response(['error' => 'Reward already claimed'], 409);
    }

    $updateStmt = $pdo->prepare('UPDATE user_rewards SET status = "claimed", claimed_at = NOW() WHERE id = :id');
    $updateStmt->execute(['id' => $row['id']]);

    db_log_activity($pdo, $userId, 'claim_reward', 'Reward claimed', [
        'reward_id' => $rewardId,
        'reward_code' => $reward['code'],
    ]);

    db_json_response([
        'ok' => true,
        'reward' => [
            'id' => (int)$reward['id'],
            'code' => $reward['code'],
            'title' => $reward['title'],
            'description' => $reward['description'],
            'reward_kind' => $reward['reward_kind'],
            'reward_json' => $row['reward_json'] ? json_decode((string)$row['reward_json'], true) : reward_gemini_payload($reward, $user, $eligibility),
        ],
    ]);
}

if ($action === 'get_daily_quests') {
    $today = date('Y-m-d');
    
    // Get all daily quests
    $questsStmt = $pdo->prepare('SELECT id, code, title, description, reward_xp, target_value FROM quests WHERE quest_type = "daily"');
    $questsStmt->execute();
    $quests = $questsStmt->fetchAll();

    $claimedStmt = $pdo->prepare('SELECT quest_id FROM quest_progress WHERE user_id = :user_id AND log_date = :today AND completed_at IS NOT NULL');
    $claimedStmt->execute(['user_id' => $userId, 'today' => $today]);
    $claimedQuestIds = [];
    foreach ($claimedStmt->fetchAll() as $row) {
        $claimedQuestIds[(int)$row['quest_id']] = true;
    }
    
    // Get today's meals for quest calculation (include category for food group detection)
    $mealsStmt = $pdo->prepare('SELECT calories, tags, category FROM meal_logs WHERE user_id = :user_id AND DATE(created_at) = :today');
    $mealsStmt->execute(['user_id' => $userId, 'today' => $today]);
    $meals = $mealsStmt->fetchAll();
    
    // Calculate quest progress
    $questsWithProgress = [];
    foreach ($quests as $quest) {
        $progress = calculateQuestProgress($quest['code'], $meals, $today, $userId, $pdo);
        $questsWithProgress[] = [
            'id' => $quest['id'],
            'code' => $quest['code'],
            'title' => $quest['title'],
            'description' => $quest['description'],
            'reward' => $quest['reward_xp'],
            'goal' => $quest['target_value'],
            'progress' => $progress['value'],
            'completed' => $progress['completed'],
            'claimed' => isset($claimedQuestIds[(int)$quest['id']]),
        ];
    }
    
    db_json_response(['quests' => $questsWithProgress]);
}

if ($action === 'meal_history') {
    $supportsIngredientBreakdown = db_column_exists($pdo, 'meal_logs', 'ingredient_breakdown');
    $historySql = $supportsIngredientBreakdown
        ? 'SELECT id, meal_type, custom_name, category, calories, price, hp_delta, energy_delta, strength_delta, defense_delta, risk_delta, xp_earned, tags, ingredient_breakdown, created_at FROM meal_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 60'
        : 'SELECT id, meal_type, custom_name, category, calories, price, hp_delta, energy_delta, strength_delta, defense_delta, risk_delta, xp_earned, tags, created_at FROM meal_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 60';

    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute(['user_id' => $userId]);
    $rows = $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $meals = [];
    foreach ($rows as $row) {
        $tags = [];
        if (!empty($row['tags'])) {
            $decodedTags = json_decode((string)$row['tags'], true);
            if (is_array($decodedTags)) {
                $tags = $decodedTags;
            }
        }

        $ingredientBreakdown = [];
        if ($supportsIngredientBreakdown && !empty($row['ingredient_breakdown'])) {
            $decodedBreakdown = json_decode((string)$row['ingredient_breakdown'], true);
            if (is_array($decodedBreakdown)) {
                $ingredientBreakdown = $decodedBreakdown;
            }
        }

        $meals[] = [
            'id' => (int)$row['id'],
            'mealType' => $row['meal_type'],
            'name' => $row['custom_name'] ?? 'Custom Meal',
            'category' => $row['category'] ?? 'Custom',
            'calories' => (int)($row['calories'] ?? 0),
            'price' => (int)($row['price'] ?? 0),
            'effects' => [
                'hp' => (int)($row['hp_delta'] ?? 0),
                'energy' => (int)($row['energy_delta'] ?? 0),
                'strength' => (int)($row['strength_delta'] ?? 0),
                'defense' => (int)($row['defense_delta'] ?? 0),
                'risk' => (int)($row['risk_delta'] ?? 0),
                'xp' => (int)($row['xp_earned'] ?? 0),
            ],
            'tags' => $tags,
            'ingredientBreakdown' => $ingredientBreakdown,
            'createdAt' => $row['created_at'],
            'source' => 'server',
        ];
    }

    db_json_response(['meals' => $meals]);
}

if ($action === 'log_meal') {
    $mealData = $body['meal'] ?? null;
    if (!is_array($mealData)) {
        db_json_response(['error' => 'meal must be an object'], 422);
    }
    
    $mealType = trim((string)($mealData['mealType'] ?? 'Snack'));
    $name = trim((string)($mealData['name'] ?? 'Custom Meal'));
    $category = trim((string)($mealData['category'] ?? 'Custom'));
    $calories = max(0, (int)($mealData['calories'] ?? 0));
    $price = max(0, (int)($mealData['price'] ?? 0));
    $tags = $mealData['tags'] ?? [];
    $ingredientBreakdown = $mealData['ingredientBreakdown'] ?? [];
    $supportsIngredientBreakdown = db_column_exists($pdo, 'meal_logs', 'ingredient_breakdown');

    if (is_string($tags)) {
        $decodedTags = json_decode($tags, true);
        $tags = is_array($decodedTags) ? $decodedTags : [];
    }

    if (!is_array($tags)) {
        $tags = [];
    }

    if (is_string($ingredientBreakdown)) {
        $decodedBreakdown = json_decode($ingredientBreakdown, true);
        $ingredientBreakdown = is_array($decodedBreakdown) ? $decodedBreakdown : [];
    }

    if (!is_array($ingredientBreakdown)) {
        $ingredientBreakdown = [];
    }
    
    $validMealTypes = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
    if (!in_array($mealType, $validMealTypes)) {
        $mealType = 'Snack';
    }
    
    $effects = $mealData['effects'] ?? ['hp' => 0, 'energy' => 0, 'strength' => 0, 'defense' => 0, 'risk' => 0, 'xp' => 10];
    $computedEffects = !empty($ingredientBreakdown)
        ? calculate_meal_attribute_deltas_from_breakdown($ingredientBreakdown, $tags, $calories)
        : null;

    if (is_array($computedEffects)) {
        $hpDelta = (int)($computedEffects['hp'] ?? 0);
        $energyDelta = (int)($computedEffects['energy'] ?? 0);
        $strengthDelta = (int)($computedEffects['strength'] ?? 0);
        $defenseDelta = (int)($computedEffects['defense'] ?? 0);
        $riskDelta = (int)($computedEffects['risk'] ?? 0);
        $xpEarned = max(0, (int)($computedEffects['xp'] ?? 10));
    } else {
        $hpDelta = max(-100, min(100, (int)($effects['hp'] ?? 0)));
        $energyDelta = max(-100, min(100, (int)($effects['energy'] ?? 0)));
        $strengthDelta = max(-100, min(100, (int)($effects['strength'] ?? 0)));
        $defenseDelta = max(-100, min(100, (int)($effects['defense'] ?? 0)));
        $riskDelta = max(-100, min(100, (int)($effects['risk'] ?? 0)));
        $xpEarned = max(0, (int)($effects['xp'] ?? 10));
    }
    
    // Insert meal log
    $tagsJson = json_encode($tags, JSON_UNESCAPED_SLASHES);
    $ingredientBreakdownJson = json_encode($ingredientBreakdown, JSON_UNESCAPED_SLASHES);
    $insertSql = '
        INSERT INTO meal_logs (user_id, meal_type, custom_name, category, calories, price, hp_delta, energy_delta, strength_delta, defense_delta, risk_delta, xp_earned, tags' . ($supportsIngredientBreakdown ? ', ingredient_breakdown' : '') . ')
        VALUES (:user_id, :meal_type, :name, :category, :calories, :price, :hp_delta, :energy_delta, :strength_delta, :defense_delta, :risk_delta, :xp_earned, :tags' . ($supportsIngredientBreakdown ? ', :ingredient_breakdown' : '') . ')
    ';
    $insertStmt = $pdo->prepare($insertSql);

    $insertParams = [
        'user_id' => $userId,
        'meal_type' => $mealType,
        'name' => $name,
        'category' => $category,
        'calories' => $calories,
        'price' => $price,
        'hp_delta' => $hpDelta,
        'energy_delta' => $energyDelta,
        'strength_delta' => $strengthDelta,
        'defense_delta' => $defenseDelta,
        'risk_delta' => $riskDelta,
        'xp_earned' => $xpEarned,
        'tags' => $tagsJson,
    ];

    if ($supportsIngredientBreakdown) {
        $insertParams['ingredient_breakdown'] = $ingredientBreakdownJson;
    }

    $insertStmt->execute($insertParams);
    
    $mealId = $pdo->lastInsertId();
    
    // Update user XP
    $userStmt = $pdo->prepare('UPDATE users SET xp = xp + :xp_earned WHERE id = :user_id');
    $userStmt->execute(['xp_earned' => $xpEarned, 'user_id' => $userId]);
    
    // Update quest progress based on meal
    updateQuestProgressForMeal($userId, $mealData, $pdo);
    sync_user_body_attributes($pdo, $userId);

    db_log_activity($pdo, $userId, 'log_meal', 'Meal logged', [
        'meal_type' => $mealType,
        'name' => $name,
        'calories' => $calories,
        'xp_earned' => $xpEarned,
    ]);
    
    db_json_response([
        'ok' => true,
        'mealId' => (int)$mealId,
        'meal' => [
            'id' => (int)$mealId,
            'mealType' => $mealType,
            'name' => $name,
            'category' => $category,
            'calories' => $calories,
            'price' => $price,
            'effects' => [
                'hp' => $hpDelta,
                'energy' => $energyDelta,
                'strength' => $strengthDelta,
                'defense' => $defenseDelta,
                'risk' => $riskDelta,
                'xp' => $xpEarned,
            ],
            'tags' => $tags,
            'ingredientBreakdown' => $ingredientBreakdown,
            'createdAt' => gmdate('c'),
            'source' => 'server',
        ],
    ]);
}

if ($action === 'claim_quest') {
    $questId = isset($body['quest_id']) ? (int)$body['quest_id'] : 0;
    $today = date('Y-m-d');
    
    // Get quest info
    $questStmt = $pdo->prepare('SELECT id, reward_xp FROM quests WHERE id = :id');
    $questStmt->execute(['id' => $questId]);
    $quest = $questStmt->fetch();
    
    if (!$quest) {
        db_json_response(['error' => 'Quest not found'], 404);
    }
    
    // Check if quest was already claimed today
    $claimStmt = $pdo->prepare('SELECT id FROM quest_progress WHERE user_id = :user_id AND quest_id = :quest_id AND log_date = :today AND completed_at IS NOT NULL');
    $claimStmt->execute(['user_id' => $userId, 'quest_id' => $questId, 'today' => $today]);
    
    if ($claimStmt->fetch()) {
        db_json_response(['error' => 'Quest reward already claimed today'], 409);
    }
    
    // Mark quest as claimed and award XP
    $updateStmt = $pdo->prepare('UPDATE quest_progress SET completed_at = NOW() WHERE user_id = :user_id AND quest_id = :quest_id AND log_date = :today');
    $updateStmt->execute(['user_id' => $userId, 'quest_id' => $questId, 'today' => $today]);
    
    $xpStmt = $pdo->prepare('UPDATE users SET xp = xp + :reward_xp WHERE id = :user_id');
    $xpStmt->execute(['reward_xp' => $quest['reward_xp'], 'user_id' => $userId]);

    db_log_activity($pdo, $userId, 'claim_quest', 'Quest reward claimed', [
        'quest_id' => $questId,
        'reward_xp' => (int)$quest['reward_xp'],
    ]);
    
    db_json_response(['ok' => true, 'xpRewarded' => $quest['reward_xp']]);
}

db_json_response(['error' => 'Invalid action'], 400);

// Helper functions for quest logic
function calculateQuestProgress($questCode, $meals, $today, $userId, $pdo) {
    $totalCalories = 0;
    $hasVegetable = false;
    $hasFried = false;
    $foodGroups = [];
    
    foreach ($meals as $meal) {
        $totalCalories += $meal['calories'];
        $tagsJson = $meal['tags'];
        $tags = is_array($tagsJson) ? $tagsJson : (json_decode($tagsJson, true) ?? []);
        
        if (in_array('vegetable', $tags)) {
            $hasVegetable = true;
        }
        if (in_array('fried', $tags)) {
            $hasFried = true;
        }
        
        // Infer food group from tags or category
        $group = detectFoodGroup($tags, $meal['category'] ?? '');
        if ($group) {
            $foodGroups[] = $group;
        }
    }
    
    $foodGroupCount = count(array_unique($foodGroups));
    
    $progress = ['value' => 0, 'completed' => false];
    
    switch ($questCode) {
        case 'daily_vegetable':
            $progress['value'] = $hasVegetable ? 1 : 0;
            $progress['completed'] = $hasVegetable;
            break;
        case 'daily_fried_free':
            $progress['value'] = $hasFried ? 0 : 1;
            $progress['completed'] = !$hasFried;
            break;
        case 'daily_calorie_cap':
            $progress['value'] = min($totalCalories, 1500);
            $progress['completed'] = $totalCalories <= 1500;
            break;
        case 'daily_food_groups':
            $progress['value'] = min($foodGroupCount, 3);
            $progress['completed'] = $foodGroupCount >= 3;
            break;
    }
    
    return $progress;
}

function detectFoodGroup($tags, $category) {
    // Check tags first for food group classification
    $tagsStr = is_array($tags) ? implode(' ', $tags) : $tags;
    $tagsLower = strtolower($tagsStr);
    $categoryLower = strtolower($category);
    
    if (preg_match('/(protein|meat|chicken|pork|beef|fish|egg)/', $tagsLower . ' ' . $categoryLower)) {
        return 'Protein';
    }
    if (preg_match('/(vegetable|broth|vegetable|carb|rice|noodle|bread)/', $tagsLower . ' ' . $categoryLower)) {
        if (preg_match('/(vegetable|soup|broth|gulay)/', $categoryLower)) {
            return 'Vegetable';
        }
        return 'Carb';
    }
    if (preg_match('/(drink|beverage|juice|water)/', $tagsLower . ' ' . $categoryLower)) {
        return 'Drink';
    }
    if (preg_match('/(fried|street food)/', $categoryLower)) {
        return 'Street Food';
    }
    
    // Default based on category alone
    if (preg_match('/(vegetable|soup|broth)/', $categoryLower)) {
        return 'Vegetable';
    }
    if (preg_match('/(protein|heavy|meat)/', $categoryLower)) {
        return 'Protein';
    }
    
    return 'Other';
}

function updateQuestProgressForMeal($userId, $mealData, $pdo) {
    $today = date('Y-m-d');
    
    // Get today's meals to recalculate all quest progress - include category to infer food group
    $mealsStmt = $pdo->prepare('SELECT calories, tags, category FROM meal_logs WHERE user_id = :user_id AND DATE(created_at) = :today');
    $mealsStmt->execute(['user_id' => $userId, 'today' => $today]);
    $meals = $mealsStmt->fetchAll();
    
    // Get all daily quests
    $questsStmt = $pdo->prepare('SELECT id, code FROM quests WHERE quest_type = "daily"');
    $questsStmt->execute();
    $quests = $questsStmt->fetchAll();
    
    // Update progress for each quest
    foreach ($quests as $quest) {
        $progress = calculateQuestProgress($quest['code'], $meals, $today, $userId, $pdo);
        
        $upsertStmt = $pdo->prepare('
            INSERT INTO quest_progress (user_id, quest_id, progress_value, log_date)
            VALUES (:user_id, :quest_id, :progress_value, :log_date)
            ON DUPLICATE KEY UPDATE progress_value = :progress_value
        ');
        
        $upsertStmt->execute([
            'user_id' => $userId,
            'quest_id' => $quest['id'],
            'progress_value' => $progress['value'],
            'log_date' => $today,
        ]);
    }
}

