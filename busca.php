<?php
// Arquivo: busca.php (Filtro Simples APENAS no TÍTULO)

header('Content-Type: application/json');
require_once 'conexao.php';

// 1. Obtém o termo de busca
$termo_busca = isset($_GET['query']) ? trim($_GET['query']) : '';

$params = [];
$where_clause = "s.deletado = 0";

// 2. Adiciona o filtro APENAS no título
if (!empty($termo_busca)) {
    $where_clause .= " AND s.titulo LIKE :termo";
    $params[':termo'] = '%' . $termo_busca . '%';
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
        WHERE s.deletado = 0 AND s.titulo LIKE :query /* <-- Adicionamos o filtro aqui */
        ORDER BY s.data_lancamento DESC";
        
try {
    $stmt = $pdo->prepare($sql);
    
    // 4. Liga os parâmetros de forma segura
    if (!empty($termo_busca)) {
        $stmt->bindParam(':termo', $params[':termo'], PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch como array associativo
    
    // 5. Retorna o resultado como JSON
    echo json_encode($resultados);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([]); // Em caso de falha, retorna array JSON vazio
}