<?php
require_once '../src/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 1; // Pega o id logado, ou 1 como fallback

/** @var PDO $pdo */
header('Content-Type: application/json');

// Recebe o JSON enviado pelo colecao_form.js
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['titulo'])) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos (Título é obrigatório).']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. TRATAMENTO DE GRAVADORA (Lógica de Tag/Criação em tempo real)
    $gravadora_id = $data['gravadora_id'] ?? null;
    
    if ($gravadora_id && !is_numeric($gravadora_id)) {
        // Se não for um número, o usuário digitou um nome novo no Select2
        // Primeiro, verificamos se já não existe uma gravadora com esse nome (evitar duplicados)
        $checkG = $pdo->prepare("SELECT id FROM gravadoras WHERE nome = ?");
        $checkG->execute([$gravadora_id]);
        $idExistente = $checkG->fetchColumn();

        if ($idExistente) {
            $gravadora_id = $idExistente;
        } else {
            // Se realmente não existe, insere a nova
            $stmt_g = $pdo->prepare("INSERT INTO gravadoras (nome) VALUES (?)");
            $stmt_g->execute([$gravadora_id]);
            $gravadora_id = $pdo->lastInsertId();
        }
    }

    // 2. Inserir na tabela colecao
    $stmt = $pdo->prepare("INSERT INTO colecao (
        titulo, gravadora_id, formato_id, numero_catalogo, 
        data_lancamento, data_aquisicao, preco, observacoes, 
        store_id, capa_url, criado_em, user_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    
    // Tratamento do preço: remove pontos de milhar e troca vírgula por ponto
    $precoRaw = $data['preco'] ?: '0';
    $preco_limpo = str_replace(['R$', ' ', '.'], '', $precoRaw);
    $preco = str_replace(',', '.', $preco_limpo);

    $stmt->execute([
        $data['titulo'],
        $gravadora_id ?: null,
        $data['formato_id'] ?: null,
        $data['numero_catalogo'] ?: null,
        $data['data_lancamento'] ?: null,
        $data['data_aquisicao'] ?: null,
        (float)$preco,
        $data['observacoes'] ?: null,
        $data['store_id'] ?: null, 
        $data['capa_url'] ?: null,
        $user_id 
    ]);

    $colecao_id = $pdo->lastInsertId();

    // Função de Sincronização de Tags (M:N)
    function syncTags($pdo, $pivotTable, $pivotColumn, $mainTable, $nameColumn, $values, $colecao_id) {
        if (!empty($values) && is_array($values)) {
            $insPivot = $pdo->prepare("INSERT INTO $pivotTable (colecao_id, $pivotColumn) VALUES (?, ?)");
            foreach ($values as $val) {
                if (is_numeric($val)) {
                    $idFinal = $val;
                } else {
                    $check = $pdo->prepare("SELECT id FROM $mainTable WHERE $nameColumn = ?");
                    $check->execute([$val]);
                    $idFinal = $check->fetchColumn();
                    if (!$idFinal) {
                        $create = $pdo->prepare("INSERT INTO $mainTable ($nameColumn) VALUES (?)");
                        $create->execute([$val]);
                        $idFinal = $pdo->lastInsertId();
                    }
                }
                $insPivot->execute([$colecao_id, $idFinal]);
            }
        }
    }

    // 3. Sincronizar Relacionamentos M:N
    syncTags($pdo, 'colecao_artista', 'artista_id', 'artistas', 'nome', $data['artistas'] ?? [], $colecao_id);
    syncTags($pdo, 'colecao_genero', 'genero_id', 'generos', 'descricao', $data['generos'] ?? [], $colecao_id);
    syncTags($pdo, 'colecao_estilo', 'estilo_id', 'estilos', 'descricao', $data['estilos'] ?? [], $colecao_id);
    syncTags($pdo, 'colecao_produtor', 'produtor_id', 'produtores', 'nome', $data['produtores'] ?? [], $colecao_id);

    // 4. Inserir Faixas (Tracklist)
    if (!empty($data['tracklist'])) {
        $stmt_f = $pdo->prepare("INSERT INTO colecao_faixas (colecao_id, numero_faixa, titulo, duracao) VALUES (?, ?, ?, ?)");
        foreach ($data['tracklist'] as $i => $t) {
            if (!empty($t['titulo'])) {
                $stmt_f->execute([$colecao_id, $i + 1, $t['titulo'], $t['duracao']]);
            }
        }
    }

    // 5. ATUALIZAR LOJA (Se vindo de uma compra/desejo)
    if (!empty($data['store_id']) && $data['store_id'] > 0) {
        $stmt_store = $pdo->prepare("UPDATE store SET situacao = 4, atualizado_em = NOW() WHERE id = ?");
        $stmt_store->execute([$data['store_id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}