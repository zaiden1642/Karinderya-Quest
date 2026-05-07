<?php
function db_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function db_connect(): PDO
{
    $rootConfig = __DIR__ . '/../config.php';
    if (file_exists($rootConfig)) {
        require $rootConfig;
    }

    $host = $DB_HOST ?? '127.0.0.1';
    $port = $DB_PORT ?? '3306';
    $name = $DB_NAME ?? '';
    $user = $DB_USER ?? '';
    $pass = $DB_PASS ?? '';

    if ($name === '' || $user === '') {
        db_json_response(['error' => 'Database is not configured. Set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS in config.php.'], 500);
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        db_json_response(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
        throw new RuntimeException('Unreachable database connection failure path.');
    }
}

function db_try_connect(): ?PDO
{
    $rootConfig = __DIR__ . '/../config.php';
    if (file_exists($rootConfig)) {
        require $rootConfig;
    }

    $host = $DB_HOST ?? '127.0.0.1';
    $port = $DB_PORT ?? '3306';
    $name = $DB_NAME ?? '';
    $user = $DB_USER ?? '';
    $pass = $DB_PASS ?? '';

    if ($name === '' || $user === '') {
        return null;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

function compute_bmi_value(float $heightCm, float $weightKg): float
{
    $heightM = $heightCm / 100;
    if ($heightM <= 0) {
        return 0;
    }
    return round($weightKg / ($heightM * $heightM), 2);
}

function db_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function db_log_activity(?PDO $pdo, ?int $userId, string $eventType, string $eventMessage, array $eventContext = []): void
{
    if (!$pdo) {
        return;
    }

    try {
        $contextJson = $eventContext ? json_encode($eventContext, JSON_UNESCAPED_SLASHES) : null;
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, event_type, event_message, event_context) VALUES (:user_id, :event_type, :event_message, :event_context)');
        $stmt->execute([
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_message' => $eventMessage,
            'event_context' => $contextJson,
        ]);
    } catch (Throwable $e) {
        // Logging must never break the request flow.
    }
}
