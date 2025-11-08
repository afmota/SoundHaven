<?php
// Arquivo: excluir_colecao.php
// Implementa a EXCLUSÃO LÓGICA (Soft Delete) de um item da Coleção.

require_once 'conexao.php';

// ----------------------------------------------------
// 1. VALIDAÇÃO INICIAL
// ----------------------------------------------------
$id_colecao = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_colecao) {
    // Redireciona de volta com mensagem de erro
    header("Location: colecao.php?status=erro&msg=" . urlencode("ID da Coleção não fornecido."));
    exit;
}

// ----------------------------------------------------
// 2. EXCLUSÃO LÓGICA (UPDATE)
// ----------------------------------------------------
$titulo = '';

try {
    // 2.1. Busca o título do álbum (para a mensagem de sucesso)
    $sql_titulo = "SELECT titulo FROM colecao WHERE id = :id";
    $stmt_titulo = $pdo->prepare($sql_titulo);
    $stmt_titulo->execute([':id' => $id_colecao]);
    $resultado = $stmt_titulo->fetch(PDO::FETCH_ASSOC);

    if ($resultado) {
        $titulo = htmlspecialchars($resultado['titulo']);
    } else {
        throw new Exception("Álbum não encontrado.");
    }

    // 2.2. Executa o UPDATE (Exclusão Lógica)
    $sql_update = "UPDATE colecao SET ativo = 0 WHERE id = :id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([':id' => $id_colecao]);

    $mensagem_status = "Álbum '{$titulo}' removido (Exclusão Lógica) com sucesso da Coleção.";
    $tipo_mensagem = 'sucesso';

} catch (Exception $e) {
    $mensagem_status = "Erro ao remover o álbum da Coleção: " . $e->getMessage();
    $tipo_mensagem = 'erro';
} catch (\PDOException $e) {
    $mensagem_status = "Falha no banco de dados ao tentar remover: " . $e->getMessage();
    $tipo_mensagem = 'erro';
}

// ----------------------------------------------------
// 3. REDIRECIONAMENTO
// ----------------------------------------------------
header("Location: colecao.php?status={$tipo_mensagem}&msg=" . urlencode($mensagem_status));
exit;