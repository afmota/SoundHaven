<?php
// Arquivo: excluir.php (Exclusão Lógica)

require_once 'conexao.php';

// 1. Verifica se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redireciona de volta com erro se o ID for inválido
    header('Location: index.php?status=erro_id');
    exit();
}
$album_id = (int)$_GET['id'];

// 2. Query de Exclusão Lógica (Marca 'deletado' como 1)
$sql = "UPDATE store SET 
            deletado = 1, 
            atualizado_em = NOW() 
        WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $album_id, PDO::PARAM_INT);
    $stmt->execute();

    // 3. Verifica se alguma linha foi afetada
    if ($stmt->rowCount() > 0) {
        // Sucesso: Redireciona de volta à lista com mensagem
        header('Location: index.php?status=excluido');
        exit();
    } else {
        // Falha: Álbum não encontrado ou já excluído
        header('Location: index.php?status=erro_nao_encontrado');
        exit();
    }

} catch (\PDOException $e) {
    // Erro no banco de dados
    header('Location: index.php?status=erro_db');
    exit();
}