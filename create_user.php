<?php
require __DIR__ . '/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(400);
    echo "Usa este script por CLI.\n";
    exit(1);
}

if ($argc < 3) {
    echo "Uso: php create_user.php <usuario> <password>\n";
    exit(1);
}

$username = trim($argv[1]);
$password = trim($argv[2]);

if ($username === '' || strlen($password) < 8) {
    echo "Usuario inválido o password demasiado corto (mínimo 8).\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1');
$stmt->execute([
    'username' => $username,
    'password_hash' => $hash,
]);

echo "Usuario creado/actualizado: {$username}\n";
