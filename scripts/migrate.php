<?php
$host = getenv('LMS_DB_HOST') ?: 'localhost';
$user = getenv('LMS_DB_USER') ?: 'root';
$pass = getenv('LMS_DB_PASS') ?: '';
$charset = 'utf8mb4';

$schemaFile = dirname(__DIR__) . '/database/schema.sql';

if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Schema file not found: $schemaFile\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaFile);
if ($schemaSql === false) {
    fwrite(STDERR, "Unable to read schema file.\n");
    exit(1);
}

$schemaSql = preg_replace('/\/\*.*?\*\//s', '', $schemaSql);
$lines = preg_split('/\R/', $schemaSql);
$cleanLines = [];
foreach ($lines as $line) {
    $trimmed = ltrim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $cleanLines[] = $line;
}

$statements = array_filter(array_map('trim', explode(';', implode("\n", $cleanLines))));

try {
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $exception) {
            $errorCode = $exception->errorInfo[1] ?? null;
            if (in_array($errorCode, [1050, 1060, 1061], true)) {
                continue;
            }
            throw $exception;
        }
    }

    $pdo->exec("INSERT IGNORE INTO roles (role_name) VALUES ('admin')");

    $roleStatement = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
    $roleStatement->execute(['admin']);
    $adminRole = $roleStatement->fetch();

    if ($adminRole) {
        $adminEmail = 'admin@admin.com';
        $adminPasswordHash = password_hash('admin1234', PASSWORD_DEFAULT);

        $existingTarget = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $existingTarget->execute([$adminEmail]);
        $targetUser = $existingTarget->fetch();

        if ($targetUser) {
            $updateTarget = $pdo->prepare('UPDATE users SET role_id = ?, password_hash = ?, is_email_confirmed = 1, status = ? WHERE user_id = ?');
            $updateTarget->execute([(int)$adminRole['role_id'], $adminPasswordHash, 'active', (int)$targetUser['user_id']]);
        } else {
            $legacyAdmin = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $legacyAdmin->execute(['admin@university.edu']);
            $legacyUser = $legacyAdmin->fetch();

            if ($legacyUser) {
                $updateLegacy = $pdo->prepare('UPDATE users SET role_id = ?, email = ?, password_hash = ?, is_email_confirmed = 1, status = ? WHERE user_id = ?');
                $updateLegacy->execute([(int)$adminRole['role_id'], $adminEmail, $adminPasswordHash, 'active', (int)$legacyUser['user_id']]);
            } else {
                $insertAdmin = $pdo->prepare('INSERT INTO users (role_id, full_name, email, password_hash, is_email_confirmed, status) VALUES (?, ?, ?, ?, 1, ?)');
                $insertAdmin->execute([(int)$adminRole['role_id'], 'Admin 1', $adminEmail, $adminPasswordHash, 'active']);
            }
        }

        echo "Default admin set: admin@admin.com / admin1234\n";
    }

    echo "Migration completed successfully.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
