<?php
require_once "../db/conexao.php";
require_once "../funcoes.php";

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) exit(json_encode(['success' => false]));

try {
    $pdo->beginTransaction();

    // 1. Atualiza dados do álbum (tabela colecao)
    $stmt = $pdo->prepare("UPDATE colecao SET titulo = ? WHERE id = ?");
    $stmt->execute([$data['titulo'], $data['id']]);

    // 2. Limpa faixas antigas (O segredo para edição limpa)
    $stmt = $pdo->prepare("DELETE FROM colecao_faixas WHERE colecao_id = ?");
    $stmt->execute([$data['id']]);

    // 3. Insere a nova lista refinada pelo usuário
    $stmt = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
    foreach ($data['faixas'] as $f) {
        $stmt->execute([
            $data['id'],
            $f['numero'],
            $f['titulo'],
            $f['duracao'] // Aqui o banco aceita a string ou você pode converter usando sua time_to_seconds()
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}