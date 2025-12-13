<?php
// Arquivo: src/Model/UserModel.php

require_once dirname(__DIR__) . '/Database.php'; // Inclui a classe Database

class UserModel {
    
    private PDO $pdo;

    public function __construct() {
        // Obtém a instância única da conexão
        $this->pdo = Database::getInstance();
    }

    /**
     * Busca um usuário pelo email no banco de dados.
     * @param string $email O email do usuário
     * @return array|false Retorna um array associativo com os dados do usuário ou false.
     */
    public function getUserByEmail(string $email): array|false {
        // A busca é simples e segura, utilizando Prepared Statements
        $sql = "SELECT id, nome, senha, tipo FROM usuarios WHERE email = :email AND ativo = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}