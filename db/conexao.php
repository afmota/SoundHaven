<?php
// Arquivo: conexao.php

// Constantes de Conexão
// Você pode mudar o 'localhost' para o IP do seu servidor se necessário
define('DB_HOST', 'localhost');
define('DB_NAME', 'SoundHaven');
define('DB_USER', 'sh_user');
define('DB_PASS', 'W3azxc*9');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$opcoes = [
    // Opções recomendadas para segurança e estabilidade do PDO
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Essencial para usar Prepared Statements de forma segura
];

try {
    // Tenta criar uma nova instância da conexão PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
    // echo "Conexão estabelecida com sucesso!"; // (Descomente para testar a conexão)
} catch (\PDOException $e) {
    // Em caso de erro, exibe uma mensagem amigável e registra o erro (melhor para segurança)
    // Em produção, você não deve exibir $e->getMessage() para o usuário.
    // Apenas para fins de desenvolvimento, vamos mostrar.
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}