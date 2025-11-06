<?php
// Arquivo: deletar.php

// 1. Inclui a conexão segura com o banco de dados
require_once 'db/conexao.php';

// 2. Verifica se o ID foi passado na URL (método GET)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID do álbum não fornecido.");
}

// 3. Captura o ID e garante que é um número inteiro
$album_id = (int)$_GET['id'];

// --- Operação Segura: UPDATE com Prepared Statement ---

// Consulta para fazer a Exclusão Lógica (altera 'deletado' para 1)
$sql = "UPDATE store SET deletado = 1, atualizado_em = NOW() WHERE id = :id";

try {
    // Prepara a consulta para segurança
    $stmt = $pdo->prepare($sql);
    
    // Liga o ID (PDO::PARAM_INT é mais seguro para números)
    $stmt->bindParam(':id', $album_id, PDO::PARAM_INT);
    
    // Executa o UPDATE
    $stmt->execute();

    // 4. Redireciona o usuário de volta para a tela principal
    header('Location: index.php?status=deletado');
    exit();

} catch (\PDOException $e) {
    // Em caso de erro, exibe uma mensagem
    die("Erro ao deletar o álbum: " . $e->getMessage());
}