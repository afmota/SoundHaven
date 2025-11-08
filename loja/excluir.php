<?php
// Arquivo: excluir.php
// Marca um álbum como logicamente excluído (deletado = 1).

// Necessário para conectar ao banco de dados
require_once 'db/conexao.php';

// 1. Coleta e valida o ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Se o ID for inválido, redireciona com erro
if (!$id) {
    header('Location: store.php?status=erro_id');
    exit;
}

try {
    // 2. Executa a exclusão lógica (UPDATE deletado = 1)
    // O álbum continua no banco, mas fica oculto por padrão no index.php
    $sql = "UPDATE store SET deletado = 1 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // 3. Verifica e redireciona
    if ($stmt->rowCount() > 0) {
        // Redireciona de volta para a listagem com mensagem de sucesso
        header('Location: store.php?status=excluido');
        exit;
    } else {
        // ID válido, mas nenhuma linha afetada (pode ser que já estava deletado)
        header('Location: store.php?status=erro_exclusao');
        exit;
    }

} catch (\PDOException $e) {
    // 4. Se houver erro no banco de dados
    error_log("Erro ao excluir álbum: " . $e->getMessage());
    header('Location: store.php?status=erro_db');
    exit;
}