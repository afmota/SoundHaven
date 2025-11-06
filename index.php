<?php
// Arquivo: index.php (ATUALIZADO)

// 1. Inclui o arquivo de conexão
// Garanta que 'conexao.php' está na mesma pasta.
require_once 'db/conexao.php';

// --- Lógica do Negócio: Consulta ao Banco de Dados ---

// Seleciona todos os campos solicitados e aplica o filtro 'deletado = 0'.
$sql = "SELECT
            s.id,
            s.titulo, 
            a.nome AS nome_artista,
            DATE_FORMAT(s.data_lancamento, '%d-%m-%Y') AS data_lancamento_formatada, 
            t.descricao AS tipo,
            sit.descricao AS status,
            f.descricao AS formato
        FROM store AS s
            LEFT JOIN artistas AS a ON s.artista_id = a.id 
            LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
            LEFT JOIN situacao AS sit ON s.situacao = sit.id
            LEFT JOIN formatos AS f ON s.formato_id = f.id
        WHERE s.deletado = 0
        ORDER BY s.data_lancamento
        LIMIT 100;";

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
    <?php 
    // Verifica se a exclusão foi bem-sucedida
    if (isset($_GET['status']) && $_GET['status'] == 'deletado'): 
    ?>
        <p class="sucesso">Álbum deletado (ocultado) com sucesso!</p>
    <?php endif; ?>

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
                    <td><?php echo htmlspecialchars($album['descricao_formato'] ?? 'Sem Formato'); ?></td>
                    <td>
                        <a href="deletar.php?id=<?php echo $album['id']; ?>" 
                            onclick="return confirm('Tem certeza que deseja DELETAR (Ocultar) o álbum: <?php echo htmlspecialchars($album['titulo'] ?? ''); ?>?');"
                            class="link-deletar">
                            Excluir
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>