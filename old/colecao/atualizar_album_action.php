<?php
require_once '../src/config/config.php';
/** @var PDO $pdo */
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // 1. Update na Tabela Coleção (1:N)
    $stmt = $pdo->prepare("UPDATE colecao SET 
        titulo = ?, gravadora_id = ?, formato_id = ?, numero_catalogo = ?, 
        data_lancamento = ?, data_aquisicao = ?, preco = ?, observacoes = ?, atualizado_em = NOW() 
        WHERE id = ?");
    
    $stmt->execute([
        $data['titulo'], $data['gravadora_id'] ?: null, $data['formato_id'] ?: null,
        $data['numero_catalogo'], $data['data_lancamento'] ?: null, 
        $data['data_aquisicao'], str_replace(',', '.', $data['preco']), 
        $data['observacoes'], $data['colecao_id']
    ]);

    // 2. Função para sincronizar tabelas M:N
    function sync($pdo, $table, $column, $ids, $colecao_id) {
        $pdo->prepare("DELETE FROM $table WHERE colecao_id = ?")->execute([$colecao_id]);
        if (!empty($ids)) {
            $ins = $pdo->prepare("INSERT INTO $table (colecao_id, $column) VALUES (?, ?)");
            foreach ($ids as $id) $ins->execute([$colecao_id, $id]);
        }
    }

    sync($pdo, 'colecao_artista', 'artista_id', $data['artistas'], $data['colecao_id']);
    sync($pdo, 'colecao_genero', 'genero_id', $data['generos'], $data['colecao_id']);
    sync($pdo, 'colecao_estilo', 'estilo_id', $data['estilos'], $data['colecao_id']);
    sync($pdo, 'colecao_produtor', 'produtor_id', $data['produtores'], $data['colecao_id']);

    // 3. Tracklist (Delete e Insert)
    $pdo->prepare("DELETE FROM colecao_faixas WHERE colecao_id = ?")->execute([$data['colecao_id']]);
    $stmt_f = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
    foreach ($data['tracks'] as $i => $t) {
        $stmt_f->execute([$data['colecao_id'], $i+1, $t['titulo'], $t['duracao']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}