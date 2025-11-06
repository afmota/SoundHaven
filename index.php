<?php
// Arquivo: index.php (ATUALIZADO)

// 1. Inclui o arquivo de conexão
// Garanta que 'conexao.php' está na mesma pasta.
require_once 'db/conexao.php';

// --- Lógica do Negócio: Consulta ao Banco de Dados ---

// Seleciona todos os campos solicitados e aplica o filtro 'deletado = 0'.
$sql = "SELECT 
            T.id, 
            T.titulo, 
            A.nome AS nome_artista,       
            TPL.descricao AS descricao_tipo,
            SIT.descricao AS descricao_situacao,  -- NOVO: Pega a descrição da situação
            T.data_lancamento, 
            T.formato_id
        FROM 
            store AS T
        LEFT JOIN 
            artistas AS A ON T.artista_id = A.id 
        LEFT JOIN                                   
            tipo_album AS TPL ON T.tipo_id = TPL.id
        LEFT JOIN                                     -- NOVO JOIN
            situacao AS SIT ON T.situacao = SIT.id 
        WHERE 
            T.deletado = 0
        LIMIT 100";

try {
    // Uso de Prepared Statements (Segurança contra SQL Injection)
    $stmt = $pdo->prepare($sql); 
    $stmt->execute();
    $albuns = $stmt->fetchAll();

} catch (\PDOException $e) {
    $erro = "Erro ao buscar álbuns: " . $e->getMessage();
    $albuns = []; 
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    
    <title>Acervo Digital</title>
    
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <h1>Acervo Digital</h1>
    <hr>

    <?php if (isset($erro)): ?>
        <p class="erro"><?php echo $erro; ?></p>
    <?php elseif (empty($albuns)): ?>
        <p>Nenhum álbum encontrado no Acervo Digital.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Artista ID</th>
                    <th>Lançamento</th>
                    <th>Tipo ID</th>
                    <th>Situação ID</th>
                    <th>Formato ID</th>
                    <th>Ações</th> </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($albuns as $album): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($album['id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['titulo'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['nome_artista'] ?? 'Artista Desconhecido'); ?></td>
                    <td><?php echo htmlspecialchars($album['data_lancamento'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($album['descricao_tipo'] ?? 'Não Classificado'); ?></td>
                    <td><?php echo htmlspecialchars($album['descricao_situacao'] ?? 'Desconhecida'); ?></td>
                    <td><?php echo htmlspecialchars($album['formato_id'] ?? 'N/A'); ?></td>
                    <td></td> 
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>