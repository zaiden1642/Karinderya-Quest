<?php

function reward_is_eligible(array $reward, array $user, array $topUser = null): bool
{
    $triggerType = (string)($reward['trigger_type'] ?? '');
    $triggerValue = (int)($reward['trigger_value'] ?? 0);

    if ($triggerType === 'level') {
        return reward_effective_level($user) >= $triggerValue;
    }

    if ($triggerType === 'streak') {
        return (int)($user['day_count'] ?? 0) >= $triggerValue;
    }

    if ($triggerType === 'wellness') {
        return $topUser && (int)($topUser['id'] ?? 0) === (int)($user['id'] ?? 0);
    }

    return false;
}

function reward_effective_level(array $user): int
{
    $xp = max(0, (int)($user['xp'] ?? 0));
    $persistedLevel = max(1, (int)($user['level'] ?? 1));
    $thresholds = [0, 100, 250, 450, 700, 1000, 1350, 1750, 2200, 2700, 3300, 4000, 4750, 5550, 6400, 7300, 8250, 9250, 10350, 11500, 12750, 14100];

    $derivedLevel = 1;
    foreach ($thresholds as $index => $threshold) {
        if ($xp >= $threshold) {
            $derivedLevel = $index + 1;
        }
    }

    return max($persistedLevel, $derivedLevel);
}

function reward_eligibility_context(array $reward, array $user, array $topUser = null): array
{
    $triggerType = (string)($reward['trigger_type'] ?? '');
    $triggerValue = (int)($reward['trigger_value'] ?? 0);
    $eligible = reward_is_eligible($reward, $user, $topUser);

    return [
        'eligible' => $eligible,
        'trigger_type' => $triggerType,
        'trigger_value' => $triggerValue,
        'current_value' => $triggerType === 'level'
            ? reward_effective_level($user)
            : ($triggerType === 'streak'
                ? (int)($user['day_count'] ?? 0)
                : ($triggerType === 'wellness' ? (int)($user['wellness_score'] ?? 0) : 0)),
        'top_wellness_name' => $topUser['display_name'] ?? $topUser['username'] ?? null,
        'top_wellness_score' => $topUser['wellness_score'] ?? null,
    ];
}

function reward_gemini_payload(array $reward, array $user, array $context): array
{
    $fallback = [
        'title' => $reward['title'],
        'summary' => $reward['description'],
        'voucher_code' => strtoupper(substr($reward['code'], 0, 4)) . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
        'voucher_details' => 'Redeem this reward in the Healthy Foods section.',
        'claim_note' => 'Keep building healthier streaks to unlock more rewards.',
    ];

    if (!function_exists('curl_init')) {
        return $fallback;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $fallback;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = $scheme . '://' . $host . '/api/gemini.php';
    $prompt = sprintf(
        'Create a short JSON reward voucher for a Filipino food health tracker. Reward code: %s. Reward title: %s. User: %s. Trigger: %s at %d. Current value: %d. Highest wellness user today: %s (%s). Return ONLY JSON with keys title, summary, voucher_code, voucher_details, claim_note. Make it friendly, concise, and useful for the user. Keep the voucher useful for healthy foods.',
        $reward['code'],
        $reward['title'],
        $user['display_name'] ?? $user['username'] ?? 'player',
        $context['trigger_type'],
        (int)$context['trigger_value'],
        (int)$context['current_value'],
        $context['top_wellness_name'] ?? 'none',
        (string)($context['top_wellness_score'] ?? '0')
    );

    $payload = ['message' => $prompt];

    $ch = curl_init($url);
    if (!$ch) {
        return $fallback;
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode >= 400) {
        return $fallback;
    }

    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        return $fallback;
    }

    $json = json_decode($text, true);
    if (!is_array($json)) {
        return $fallback;
    }

    return [
        'title' => (string)($json['title'] ?? $fallback['title']),
        'summary' => (string)($json['summary'] ?? $fallback['summary']),
        'voucher_code' => (string)($json['voucher_code'] ?? $fallback['voucher_code']),
        'voucher_details' => (string)($json['voucher_details'] ?? $fallback['voucher_details']),
        'claim_note' => (string)($json['claim_note'] ?? $fallback['claim_note']),
    ];
}

function ensure_reward_catalog(PDO $pdo): void
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM reward_catalog');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int)($row['cnt'] ?? 0) === 0) {
            $inserts = [
                [
                    'code' => 'reach_level_10',
                    'title' => 'Level 10 Reward',
                    'description' => 'Reach level 10 to unlock a healthy food voucher.',
                    'trigger_type' => 'level',
                    'trigger_value' => 10,
                    'reward_kind' => 'voucher',
                ],
                [
                    'code' => 'one_month_streak',
                    'title' => '30-Day Streak Reward',
                    'description' => 'Keep your streak alive for 30 days to claim a bigger food reward.',
                    'trigger_type' => 'streak',
                    'trigger_value' => 30,
                    'reward_kind' => 'meal_box',
                ],
                [
                    'code' => 'wellness_leader',
                    'title' => 'Daily Wellness Leader',
                    'description' => 'Hold the highest wellness score for the day to receive a reward.',
                    'trigger_type' => 'wellness',
                    'trigger_value' => 1,
                    'reward_kind' => 'discount',
                ],
            ];

            $stmt = $pdo->prepare(
                'INSERT INTO reward_catalog (code, title, description, trigger_type, trigger_value, reward_kind)
                 VALUES (:code, :title, :description, :trigger_type, :trigger_value, :reward_kind)'
            );

            foreach ($inserts as $reward) {
                $stmt->execute([
                    ':code' => $reward['code'],
                    ':title' => $reward['title'],
                    ':description' => $reward['description'],
                    ':trigger_type' => $reward['trigger_type'],
                    ':trigger_value' => $reward['trigger_value'],
                    ':reward_kind' => $reward['reward_kind'],
                ]);
            }
        }
    } catch (Exception $e) {
        // Silent fail - rewards not critical for core gameplay
    }
}
