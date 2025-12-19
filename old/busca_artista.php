<?php
// Arquivo: busca_artista.php (Filtro APENAS por Artista ID)

header('Content-Type: application/json');
require_once 'db/conexao.php';

// 1. Obtém o ID do artista selecionado
$artista_id = isset($_GET['artista_id']) ? (int)$_GET['artista_id'] : 0;

$where_clause = "s.deletado = 0";
$params = [];

// 2. Se o ID for válido (maior que zero), adiciona o filtro
if ($artista_id > 0) {
    $where_clause .= " AND s.artista_id = :artista_id";
    $params[':artista_id'] = $artista_id;
} else {
    // Se for 0 ou inválido, retorna a listagem completa (o que está no index)
}

// 3. Monta a Query (Sua Query Original + Filtro)
$sql = "SELECT
            s.id, 
            s.titulo, 
            a.nome AS nome_artista,
            DATE_FORMAT(s.data_lancamento, '%d/%m/%Y') AS data_lancamento, 
            t.descricao AS tipo,
            sit.descricao AS status,
            f.descricao AS formato
        FROM store AS s
            LEFT JOIN artistas AS a ON s.artista_id = a.id 
            LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
            LEFT JOIN situacao AS sit ON s.situacao = sit.id
            LEFT JOIN formatos AS f ON s.formato_id = f.id
        WHERE " . $where_clause . "
        ORDER BY s.data_lancamento DESC
        LIMIT 100";
        
try {
    $stmt = $pdo->prepare($sql);
    
    // 4. Liga os parâmetros de forma segura (essencial contra SQL Injection)
    if ($artista_id > 0) {
        $stmt->bindParam(':artista_id', $params[':artista_id'], PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    
    // 5. Retorna o resultado como JSON
    echo json_encode($resultados);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno na busca por artista: ' . $e->getMessage()]);
}