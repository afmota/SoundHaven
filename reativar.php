<?php
// Arquivo: reativar.php
// Reativa (define deletado = 0) um álbum excluído logicamente.

require_once 'conexao.php';

// 1. Verificar se o ID foi fornecido e é um inteiro válido
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    // Redireciona de volta para a listagem com erro
    header('Location: index.php?status=erro_id');
    exit;
}

// 2. Tentar reativar o álbum no banco de dados
try {
    $sql = "UPDATE store SET deletado = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // 3. Verificar se a atualização foi bem-sucedida
    if ($stmt->rowCount() > 0) {
        // Redireciona de volta para a listagem principal com mensagem de sucesso
        header('Location: index.php?status=reativado');
        exit;
    } else {
        // ID válido, mas nenhuma linha afetada (álbum não existe ou já estava ativo)
        header('Location: index.php?status=erro_reativacao');
        exit;
    }

} catch (\PDOException $e) {
    // Erro de banco de dados
    error_log("Erro de reativação: " . $e->getMessage());
    header('Location: index.php?status=erro_db');
    exit;
}