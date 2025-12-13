<?php
// Arquivo: src/config/config.php (AGORA COMPLETO: Carregamento do .env + Conexão PDO)

// 1. CARREGAMENTO DAS VARIÁVEIS DE AMBIENTE (.env)
// ----------------------------------------------------

// Define o caminho para o arquivo .env
// O __DIR__ garante que estamos pegando a raiz do projeto (um nível acima de src/config/)
$envFile = dirname(__DIR__, 2) . '/.env'; 

if (file_exists($envFile)) {
    
    if ($handle = fopen($envFile, 'r')) {
        
        while (($line = fgets($handle)) !== false) {
            
            $line = trim($line);

            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                 $_ENV[$key] = $value;
                 $_SERVER[$key] = $value; 
            }
        }
        fclose($handle); 
    }
}


// 2. CONEXÃO COM O BANCO DE DADOS (CRIAÇÃO DA VARIÁVEL $pdo)
// -----------------------------------------------------------------

// Defina $pdo como NULL por segurança.
$pdo = null;

try {
    // Busca as variáveis do .env (assumindo DB_HOST, DB_NAME, DB_USER, DB_PASS)
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $db = $_ENV['DB_NAME'] ?? 'soundhaven';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? ''; // Senha

    // Cria o objeto PDO
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8", $user, $pass, [
        // Modo de erro para exceções (essencial para debug e Model)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Evita que o MySQL emule comandos (melhora performance e segurança)
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // O $pdo AGORA ESTÁ DEFINIDO e será passado corretamente para o AlbumModel.

} catch (PDOException $e) {
    // Se a conexão falhar, exibe o erro e encerra o script.
    // Isso garante que não continuemos sem o banco de dados.
    die("Erro na conexão com o banco de dados. Verifique suas credenciais no .env. Detalhe: " . $e->getMessage());
}