<?php
require_once '../src/config/config.php';
/** @var PDO $pdo */
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['titulo'])) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Inserir na tabela colecao
    $stmt = $pdo->prepare("INSERT INTO colecao (
        titulo, gravadora_id, formato_id, numero_catalogo, 
        data_lancamento, data_aquisicao, preco, observacoes, criado_em
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $preco = str_replace(['.', ','], ['', '.'], $data['preco'] ?: '0');

    $stmt->execute([
        $data['titulo'],
        $data['gravadora_id'] ?: null,
        $data['formato_id'] ?: null,
        $data['numero_catalogo'],
        $data['data_lancamento'] ?: null,
        $data['data_aquisicao'] ?: null,
        $preco,
        $data['observacoes']
    ]);

    $colecao_id = $pdo->lastInsertId();

    // 2. Sincronizar Tabelas M:N (Artistas, Gêneros, Estilos, Produtores)
    function syncMN($pdo, $table, $column, $ids, $colecao_id) {
        if (!empty($ids)) {
            $ins = $pdo->prepare("INSERT INTO $table (colecao_id, $column) VALUES (?, ?)");
            foreach ($ids as $id) $ins->execute([$colecao_id, $id]);
        }
    }

    syncMN($pdo, 'colecao_artista', 'artista_id', $data['artistas'], $colecao_id);
    syncMN($pdo, 'colecao_genero', 'genero_id', $data['generos'], $colecao_id);
    syncMN($pdo, 'colecao_estilo', 'estilo_id', $data['estilos'], $colecao_id);
    syncMN($pdo, 'colecao_produtor', 'produtor_id', $data['produtores'], $colecao_id);

    // 3. Inserir Faixas
    if (!empty($data['tracks'])) {
        $stmt_f = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
        foreach ($data['tracks'] as $i => $t) {
            $stmt_f->execute([$colecao_id, $i + 1, $t['titulo'], $t['duracao']]);
        }
    }

    // 4. ATUALIZAR LOJA (Se veio de lá)
    if (!empty($data['store_id'])) {
        $stmt_store = $pdo->prepare("UPDATE store SET situacao = 3, atualizado_em = NOW() WHERE id = ?");
        $stmt_store->execute([$data['store_id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}