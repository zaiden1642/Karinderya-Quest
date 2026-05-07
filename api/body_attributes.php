<?php

function body_attribute_clamp(int $value): int
{
    return max(0, min(100, $value));
}

function body_attribute_round_delta(int $value): int
{
    return (int)round(($value - 50) / 10);
}

function body_attribute_clamp_delta(int $value): int
{
    return max(-100, min(100, $value));
}

function normalize_meal_tags($tags): array
{
    if (is_string($tags)) {
        $decoded = json_decode($tags, true);
        $tags = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($tags)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($tag) {
        return strtolower(trim((string)$tag));
    }, $tags), static function ($tag) {
        return $tag !== '';
    }));
}

function calculate_meal_attribute_deltas_from_breakdown(array $breakdown, array $tags = [], int $calories = 0): array
{
    $deltas = [
        'hp' => 0,
        'energy' => 0,
        'strength' => 0,
        'defense' => 0,
        'risk' => 0,
        'xp' => 10,
    ];

    $totalCalories = 0;

    foreach ($breakdown as $item) {
        if (!is_array($item)) {
            continue;
        }

        $category = strtolower(trim((string)($item['category'] ?? 'other')));
        $servingSize = max(0.5, (float)($item['servingSize'] ?? 1));
        $itemCalories = (float)($item['calories'] ?? 0);

        if ($itemCalories <= 0 && isset($item['baseCalories'])) {
            $itemCalories = (float)$item['baseCalories'] * $servingSize;
        }

        if ($itemCalories <= 0) {
            $itemCalories = 50 * $servingSize;
        }

        $totalCalories += max(0, $itemCalories);
        $weight = max(0.5, $itemCalories / 100);

        if ($category === 'protein') {
            $deltas['strength'] += (int)round(4 * $weight);
            $deltas['energy'] += (int)round(1.5 * $weight);
            $deltas['hp'] += (int)round(1.5 * $weight);
            continue;
        }

        if ($category === 'vegetable') {
            $deltas['defense'] += (int)round(3.5 * $weight);
            $deltas['hp'] += (int)round(3 * $weight);
            $deltas['risk'] -= (int)round(1.5 * $weight);
            continue;
        }

        if ($category === 'carb') {
            $deltas['energy'] += (int)round(4 * $weight);
            $deltas['risk'] += (int)round(0.8 * $weight);
            continue;
        }

        if ($category === 'liquid' || $category === 'drink') {
            $deltas['energy'] += (int)round(1.5 * $weight);
            continue;
        }

        if ($category === 'spice') {
            $deltas['risk'] += (int)round(0.2 * $weight);
            continue;
        }

        $deltas['hp'] += (int)round(1 * $weight);
    }

    $normalizedTags = normalize_meal_tags($tags);
    if (in_array('fried', $normalizedTags, true)) {
        $deltas['risk'] += max(2, (int)round($totalCalories / 120));
        $deltas['hp'] -= 1;
    }

    if (in_array('protein', $normalizedTags, true)) {
        $deltas['strength'] += 2;
    }

    if (in_array('vegetable', $normalizedTags, true) || in_array('balanced', $normalizedTags, true)) {
        $deltas['hp'] += 2;
        $deltas['defense'] += 2;
    }

    if ($totalCalories > 700) {
        $deltas['risk'] += (int)round(($totalCalories - 700) / 250);
    }

    $deltas['hp'] = body_attribute_clamp_delta($deltas['hp']);
    $deltas['energy'] = body_attribute_clamp_delta($deltas['energy']);
    $deltas['strength'] = body_attribute_clamp_delta($deltas['strength']);
    $deltas['defense'] = body_attribute_clamp_delta($deltas['defense']);
    $deltas['risk'] = body_attribute_clamp_delta($deltas['risk']);
    $deltas['xp'] = max(8, min(30, (int)round(max(100, $totalCalories) / 45)));

    return $deltas;
}

function calculate_user_day_streak(PDO $pdo, int $userId): int
{
    $dateStmt = $pdo->prepare('SELECT DISTINCT DATE(created_at) AS log_date FROM meal_logs WHERE user_id = :user_id ORDER BY log_date DESC');
    $dateStmt->execute(['user_id' => $userId]);
    $dates = $dateStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (!$dates) {
        return 0;
    }

    $dateSet = array_fill_keys($dates, true);
    $cursor = gmdate('Y-m-d');

    if (!isset($dateSet[$cursor])) {
        return 0;
    }

    $streak = 0;
    while (isset($dateSet[$cursor])) {
        $streak++;
        $cursor = gmdate('Y-m-d', strtotime($cursor . ' -1 day'));
    }

    return $streak;
}

function calculate_user_body_attributes(PDO $pdo, int $userId): array
{
    $supportsIngredientBreakdown = db_column_exists($pdo, 'meal_logs', 'ingredient_breakdown');
    $mealQuery = $supportsIngredientBreakdown
        ? 'SELECT calories, hp_delta, energy_delta, strength_delta, defense_delta, risk_delta, tags, ingredient_breakdown FROM meal_logs WHERE user_id = :user_id ORDER BY created_at ASC'
        : 'SELECT calories, hp_delta, energy_delta, strength_delta, defense_delta, risk_delta, tags FROM meal_logs WHERE user_id = :user_id ORDER BY created_at ASC';

    $mealStmt = $pdo->prepare($mealQuery);
    $mealStmt->execute(['user_id' => $userId]);
    $mealStats = $mealStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $mealCount = 0;
    $totalCalories = 0;
    $hpTotal = 0;
    $energyTotal = 0;
    $strengthTotal = 0;
    $defenseTotal = 0;
    $riskTotal = 0;

    foreach ($mealStats as $mealRow) {
        $mealCount++;
        $totalCalories += (int)($mealRow['calories'] ?? 0);

        $breakdown = [];
        if (!empty($mealRow['ingredient_breakdown'])) {
            $decodedBreakdown = json_decode((string)$mealRow['ingredient_breakdown'], true);
            if (is_array($decodedBreakdown)) {
                $breakdown = $decodedBreakdown;
            }
        }

        $tags = [];
        if (!empty($mealRow['tags'])) {
            $decodedTags = json_decode((string)$mealRow['tags'], true);
            if (is_array($decodedTags)) {
                $tags = $decodedTags;
            }
        }

        if (!empty($breakdown)) {
            $computed = calculate_meal_attribute_deltas_from_breakdown($breakdown, $tags, (int)($mealRow['calories'] ?? 0));
            $hpTotal += (int)($computed['hp'] ?? 0);
            $energyTotal += (int)($computed['energy'] ?? 0);
            $strengthTotal += (int)($computed['strength'] ?? 0);
            $defenseTotal += (int)($computed['defense'] ?? 0);
            $riskTotal += (int)($computed['risk'] ?? 0);
            continue;
        }

        $hpTotal += (int)($mealRow['hp_delta'] ?? 0);
        $energyTotal += (int)($mealRow['energy_delta'] ?? 0);
        $strengthTotal += (int)($mealRow['strength_delta'] ?? 0);
        $defenseTotal += (int)($mealRow['defense_delta'] ?? 0);
        $riskTotal += (int)($mealRow['risk_delta'] ?? 0);
    }

    $hp = 100;
    $energy = body_attribute_clamp(50 + $energyTotal);
    $strength = body_attribute_clamp(50 + $strengthTotal);
    $defense = body_attribute_clamp(50 + $defenseTotal);

    $hpDelta = body_attribute_round_delta($energy) + body_attribute_round_delta($strength) + body_attribute_round_delta($defense);
    $hp = body_attribute_clamp($hp + max(-15, min(15, $hpDelta)));

    $risk = 10 + $riskTotal;
    $riskBalance = ($energy + $strength + $defense) / 3;
    $caloriePressure = max(0, (int)round(($totalCalories - ($mealCount * 450)) / 220));
    $risk += $caloriePressure;

    if ($riskBalance >= 75) {
        $risk -= 6;
    } elseif ($riskBalance >= 60) {
        $risk -= 4;
    } elseif ($riskBalance >= 45) {
        $risk -= 2;
    } else {
        $risk += 2;
    }

    if ($mealCount >= 3 && $riskTotal <= 0 && $totalCalories <= 1800) {
        $risk -= 2;
    }

    if ($mealCount === 0) {
        $risk += 3;
    }

    $risk = body_attribute_clamp($risk);
    $wellnessScore = body_attribute_clamp((int)round((($hp + $energy + $strength + $defense) / 4) - ($risk * 0.3)));
    $dayStreak = calculate_user_day_streak($pdo, $userId);

    $updateStmt = $pdo->prepare('UPDATE users SET hp = :hp, energy = :energy, strength = :strength, defense = :defense, risk = :risk, wellness_score = :wellness_score, day_count = :day_count WHERE id = :id');
    $updateStmt->execute([
        'hp' => $hp,
        'energy' => $energy,
        'strength' => $strength,
        'defense' => $defense,
        'risk' => $risk,
        'wellness_score' => $wellnessScore,
        'day_count' => $dayStreak,
        'id' => $userId,
    ]);

    return [
        'hp' => $hp,
        'energy' => $energy,
        'strength' => $strength,
        'defense' => $defense,
        'risk' => $risk,
        'wellness_score' => $wellnessScore,
        'day_count' => $dayStreak,
    ];
}

function sync_user_body_attributes(PDO $pdo, int $userId): array
{
    return calculate_user_body_attributes($pdo, $userId);
}

function sync_user_wellness_score(PDO $pdo, int $userId): int
{
    $stats = sync_user_body_attributes($pdo, $userId);
    return (int)($stats['wellness_score'] ?? 0);
}
