<?php
// Arquivo: src/Database.php (Atualizado para diagnóstico de conexão)

// 1. Carregar Configurações (o .env já foi carregado em index.php/login.php)
// Se não carregamos o .env antes, isso garantirá que as variáveis estejam aqui.
if (!isset($_ENV['DB_HOST'])) {
    require_once dirname(__DIR__) . '/src/config/config.php';
}

class Database {
    private static ?PDO $instance = null;

    /**
     * Retorna a única instância da conexão PDO (Singleton).
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'];
            $db = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];
            $charset = 'utf8mb4';

            // Inclui a porta, se especificada no DB_HOST
            $port = '';
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host);
                $port = ";port=$port";
            }

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset$port";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                // *** MENSAGEM DE ERRO DETALHADA PARA AJUDAR NO DIAGNÓSTICO ***
                
                // Extrai o código do erro para fins de logging/segurança
                $error_code = $e->getCode();

                // Registra o erro completo no log do servidor
                error_log("Erro Fatal de Conexão PDO (Code: $error_code): " . $e->getMessage());
                
                // MENSAGEM VISÍVEL AO USUÁRIO: Mantém a segurança, mas adiciona dica de diagnóstico
                $user_message = "Desculpe, nosso sistema de banco de dados está indisponível no momento.";
                
                if ($error_code == 2002) {
                    $user_message .= " Erro: Servidor MySQL/MariaDB (Host: $host) não encontrado ou inativo. Verifique se o serviço está rodando.";
                } elseif (in_array($error_code, [1045, 1044])) {
                    $user_message .= " Erro: Falha na autenticação. Verifique DB_USER e DB_PASS no arquivo .env.";
                } elseif ($error_code == 1049) {
                    $user_message .= " Erro: Banco de dados '$db' não encontrado. Verifique DB_NAME no arquivo .env.";
                }

                die("<div style='background-color:#ffe0e0; color:#cc0000; border: 1px solid #cc0000; padding: 20px; margin: 20px; font-family: monospace;'>
                    <strong>ERRO CRÍTICO DE CONEXÃO:</strong> $user_message
                </div>");
            }
        }
        return self::$instance;
    }
}