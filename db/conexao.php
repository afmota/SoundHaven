<?php
// Arquivo: conexao.php

// Caminho do arquivo de configuração externa (.ini)
$configPath = __DIR__ . '/config.ini';

if (!file_exists($configPath)) {
    die("Arquivo de configuração do banco não encontrado.");
}

$config = parse_ini_file($configPath);

define('DB_HOST', $config['host']);
define('DB_NAME', $config['dbname']);
define('DB_USER', $config['user']);
define('DB_PASS', $config['pass']);
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
} catch (PDOException $e) {

    // Log real (nunca mostrar erros de banco em produção)
    error_log("[Erro PDO] " . $e->getMessage());

    // Mensagem genérica para o usuário
    die("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
}
