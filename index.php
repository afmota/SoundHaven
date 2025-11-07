<?php
// Arquivo: index.php (VERSÃO ATUALIZADA)

// 1. Inclui o arquivo de conexão
require_once 'conexao.php';

// --- CONSULTA 1: BUSCA TODOS OS ARTISTAS (para popular o dropdown) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$artistas = [];

try {
    $stmt_artistas = $pdo->prepare($sql_artistas); 
    $stmt_artistas->execute();
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC); // Obtém a lista de artistas

} catch (\PDOException $e) {
    // Se der erro aqui, a página principal ainda deve carregar
    $erro_artistas = "Erro ao buscar artistas: " . $e->getMessage();
}


// --- CONSULTA 2: BUSCA A LISTAGEM PRINCIPAL (Sua Query Original) ---

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
        WHERE s.deletado = 0
        ORDER BY s.data_lancamento
        LIMIT 100;"; 

try {
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <h1>Acervo Digital</h1>
    <hr>

    <?php 
    // NOVO: Mensagem de sucesso após edição
    if (isset($_GET['status']) && $_GET['status'] == 'editado'): 
    ?>
        <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album']); ?>" atualizado com sucesso!</p>
    <?php endif; ?>
    
    <div class="filters-container">
        <div class="search-container">
            <label for="search_titulo">Buscar Título do Álbum:</label>
            <input type="text" id="search_titulo" name="search_titulo" placeholder="Digite o título do álbum..." autocomplete="off">
        </div>
        
        <div class="search-container">
            <label for="filter_artista">Filtrar por Artista:</label>
            <select id="filter_artista" name="filter_artista">
                <option value="">-- Selecione um Artista --</option>
                <?php if (!empty($artistas)): ?>
                    <?php foreach ($artistas as $artista): ?>
                        <option value="<?php echo htmlspecialchars($artista['id']); ?>">
                            <?php echo htmlspecialchars($artista['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        
    </div>
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
                    <th>Artista/Banda</th>
                    <th>Lançamento</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Formato</th>
                    <th>Ações</th> </tr>
            </thead>
            <tbody>
                <?php 
                // Loop para exibir os dados
                foreach ($albuns as $album): 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($album['id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['titulo'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($album['nome_artista'] ?? 'Artista Desconhecido'); ?></td>
                    <td><?php echo htmlspecialchars($album['data_lancamento'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($album['tipo'] ?? 'Não Classificado'); ?></td>
                    <td><?php echo htmlspecialchars($album['status'] ?? 'Desconhecida'); ?></td>
                    <td><?php echo htmlspecialchars($album['formato'] ?? 'Sem Formato'); ?></td>
                    <td>
                        <td><?php echo htmlspecialchars($album['formato'] ?? 'Sem Formato'); ?></td>
                        <td>
                            <a href="editar.php?id=<?php echo $album['id']; ?>" title="Editar Álbum">
                            <i class="fa-solid fa-pencil" style="color: #007bff; cursor: pointer;"></i>
                            <i class="fa-solid fa-trash-can" style="color: #dc3545; cursor: pointer; margin-left: 8px;" title="Excluir"></i>
                    </td> 
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="filtro.js"></script>
</body>
</html>