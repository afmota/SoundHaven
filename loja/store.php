<?php
// Arquivo: store.php
// Listagem de álbuns na tabela 'store' com Paginação e 6 Filtros.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php'); // Redireciona para o login
    exit();
}

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// --- CONFIGURAÇÃO DE PAGINAÇÃO E FILTROS ---
$limite_por_pagina = 20; 
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1; 

// Pega e sanitiza os filtros da URL
$termo_busca = filter_input(INPUT_GET, 'search_titulo', FILTER_DEFAULT) ?? '';

// Filtros de IDs (inteiros) - CORREÇÃO CRÍTICA: Se false ou 0, define como null para ignorar
$artista_filtro = filter_input(INPUT_GET, 'filter_artista', FILTER_VALIDATE_INT);
if ($artista_filtro === false || $artista_filtro === 0) {
    $artista_filtro = null;
}

$tipo_filtro = filter_input(INPUT_GET, 'filter_tipo', FILTER_VALIDATE_INT);
if ($tipo_filtro === false || $tipo_filtro === 0) {
    $tipo_filtro = null;
}

$situacao_filtro = filter_input(INPUT_GET, 'filter_situacao', FILTER_VALIDATE_INT);
if ($situacao_filtro === false || $situacao_filtro === 0) {
    $situacao_filtro = null;
}

// Formato: Permite o valor -1 (Sem Formato/NULL), mas trata 0 ou false como null.
$formato_filtro = filter_input(INPUT_GET, 'filter_formato', FILTER_VALIDATE_INT);
if ($formato_filtro === false || $formato_filtro === 0) {
    $formato_filtro = null;
}

// Deletado: O filtro padrão é 0 (Ativo). -1 = Todos.
$deletado_filtro = filter_input(INPUT_GET, 'filter_deletado', FILTER_VALIDATE_INT);
// Se não veio, ou veio inválido, usamos o padrão 0 (apenas ativos)
if ($deletado_filtro === false || $deletado_filtro === null) {
    $deletado_filtro = 0; 
}


// --- CONSTRUÇÃO DAS CONDIÇÕES WHERE ---
$where_condicoes = [];
$bind_params = [];

// 1. Título (Busca segura com LIKE e binding)
if (!empty($termo_busca)) {
    // 1. DECODIFICAÇÃO (OPCIONAL, mas resolve problemas persistentes de entidade HTML)
    // Isso garante que se o filtro pegou a entidade &#39; em algum momento, 
    // ela seja convertida de volta para o apóstrofo '
    $busca_tratada = html_entity_decode($termo_busca, ENT_QUOTES, 'UTF-8');
    
    $where_condicoes[] = "s.titulo LIKE :titulo";
    
    // 2. CORREÇÃO: Usamos o valor DECODIFICADO para o bind
    $bind_params[':titulo'] = '%' . $busca_tratada . '%';
}

// 2. Artista
if ($artista_filtro !== null) {
    $where_condicoes[] = "s.artista_id = :artista_id";
    $bind_params[':artista_id'] = $artista_filtro;
}

// 3. Tipo
if ($tipo_filtro !== null) {
    $where_condicoes[] = "s.tipo_id = :tipo_id";
    $bind_params[':tipo_id'] = $tipo_filtro;
}

// 4. Situação
if ($situacao_filtro !== null) {
    $where_condicoes[] = "s.situacao = :situacao";
    $bind_params[':situacao'] = $situacao_filtro;
}

// 5. Formato (trata -1 para NULL no DB)
if ($formato_filtro !== null) {
    if ($formato_filtro == -1) {
        $where_condicoes[] = "s.formato_id IS NULL";
    } else {
        $where_condicoes[] = "s.formato_id = :formato_id";
        $bind_params[':formato_id'] = $formato_filtro;
    }
}

// 6. Deleção (trata -1 para TODOS)
if ($deletado_filtro != -1) {
    $where_condicoes[] = "s.deletado = :deletado_filtro";
    $bind_params[':deletado_filtro'] = $deletado_filtro;
}

// NOVO FILTRO: NÃO EXIBIR SITUAÇÃO 4 (Não Lançado/Privado)
// A SITUAÇÃO 5 (Em Rascunho/Oculto) JÁ É O PADRÃO PARA NÃO EXIBIR
$where_condicoes[] = "s.situacao NOT IN (4, 5)";


// Lógica de reset de página ao aplicar novo filtro
$has_filter = !empty($termo_busca) || $artista_filtro !== null || $tipo_filtro !== null || $situacao_filtro !== null || $formato_filtro !== null || $deletado_filtro != 0;
if ($has_filter && !isset($_GET['p'])) {
    $pagina_atual = 1;
}
if ($pagina_atual < 1) {
    $pagina_atual = 1;
}
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// --- CONSULTA 1: NÚMERO TOTAL DE REGISTROS (COM FILTROS) ---
$where_clause = empty($where_condicoes) ? '1=1' : implode(' AND ', $where_condicoes);

// USANDO A TABELA 'store'
$sql_total = "SELECT COUNT(s.id) AS total FROM store AS s WHERE " . $where_clause;
$total_registros = 0;

try {
    $stmt_total = $pdo->prepare($sql_total);
    
    // CORREÇÃO: Passa o array de filtros diretamente para o execute()
    $stmt_total->execute($bind_params); 
    
    $resultado_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resultado_total['total'];
    
} catch (\PDOException $e) {
    $erro_total = "Erro ao contar registros: " . $e->getMessage();
}

$total_paginas = ceil($total_registros / $limite_por_pagina);


// --- CONSULTA 2: BUSCA DADOS RELACIONADOS (Dropdowns) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$sql_tipos = "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC";
$sql_situacao = "SELECT id, descricao FROM situacao ORDER BY descricao ASC";
$sql_formatos = "SELECT id, descricao FROM formatos ORDER BY descricao ASC";

$artistas = $tipos = $situacoes = $formatos = [];

try {
    $artistas = $pdo->query($sql_artistas)->fetchAll(PDO::FETCH_ASSOC);
    $tipos = $pdo->query($sql_tipos)->fetchAll(PDO::FETCH_ASSOC);
    $situacoes = $pdo->query($sql_situacao)->fetchAll(PDO::FETCH_ASSOC);
    $formatos = $pdo->query($sql_formatos)->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $erro_dropdowns = "Erro ao carregar listas de filtros: " . $e->getMessage();
}


// --- CONSULTA 3: BUSCA A LISTAGEM PRINCIPAL (Corrigida para usar execute) ---
$sql = "SELECT 
            s.id, 
            s.titulo, 
            s.capa_url, /* Adicionado para a capa do álbum */
            s.situacao, /* Adicionado para diferenciar cores/estilos */
            s.preco_sugerido, /* Adicionado para simular preço */
            DATE_FORMAT(s.data_lancamento, '%Y') AS ano_lancamento, /* Ano para card */
            s.deletado,
            s.formato_id,
            a.nome AS nome_artista,
            t.descricao AS tipo,
            st.descricao AS status,
            f.descricao AS formato
        FROM store AS s
        LEFT JOIN artistas AS a ON s.artista_id = a.id
        LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
        LEFT JOIN situacao AS st ON s.situacao = st.id
        LEFT JOIN formatos AS f ON s.formato_id = f.id
        WHERE " . $where_clause . "
        ORDER BY s.data_lancamento DESC
        LIMIT :limite OFFSET :offset"; 

try {
    $stmt = $pdo->prepare($sql); 
    
    // Cria um array completo para o execute, incluindo os binds de filtro e paginação
    $execute_params = array_merge(
        $bind_params,
        [':limite' => $limite_por_pagina, ':offset' => $offset]
    );

    // CORREÇÃO: Passa o array completo de parâmetros para o execute()
    // O PDO fará o bind correto, garantindo a segurança e o tratamento do apóstrofo.
    $stmt->execute($execute_params); 
    $albuns = $stmt->fetchAll();

} catch (\PDOException $e) {
    $erro = "Erro ao buscar álbuns: " . $e->getMessage();
    $albuns = []; 
}

// INÍCIO DO HTML
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area">
            
            <div class="page-header-actions">
                <h1>Catálogo (Total: <?php echo $total_registros; ?>)</h1>
                
                <div style="display: flex; gap: 10px;"> 
                    <a href="importar_store.php" class="btn-adicionar-catalogo">
                        <i class="fas fa-file-upload"></i> Importar em Lote
                    </a>
                    <a href="adicionar_album.php" class="btn-adicionar-catalogo">
                        <i class="fas fa-plus-circle"></i> Adicionar Álbum
                    </a>
                </div>
            </div>

            <?php 
            if (isset($_GET['status']) && $_GET['status'] == 'editado'): 
            ?>
                <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album'] ?? 'N/D'); ?>" atualizado com sucesso!</p>
            <?php 
            elseif (isset($_GET['status']) && $_GET['status'] == 'criado'): 
            ?>
                <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album'] ?? 'N/D'); ?>" adicionado ao catálogo com sucesso!</p>
            <?php 
            elseif (isset($_GET['status']) && $_GET['status'] == 'excluido'): 
            ?>
                <p class="sucesso">Álbum excluído com sucesso (movido para a lixeira).</p>
            <?php 
            elseif (isset($_GET['status']) && $_GET['status'] == 'reativado'): 
            ?>
                <p class="sucesso">Álbum reativado com sucesso.</p>
            <?php 
            elseif (isset($_GET['status']) && strpos($_GET['status'], 'erro') !== false): 
            ?>
                <p class="erro">Erro ao processar a operação. Tente novamente.</p>
            <?php 
            endif; // FIM DO BLOCO IF/ELSEIF
            ?>

            <?php if (!empty($erro)): ?>
                <p class="erro"><?php echo $erro; ?></p>
            <?php endif; ?>

            <?php if (empty($albuns)): ?>
                <p class="alerta">Nenhum álbum encontrado com os filtros selecionados.</p>
            <?php else: ?>
                
                <div class="store-grid-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; margin-top: 20px;">
                    
                    <?php foreach ($albuns as $album): ?>
                        <div class="album-card" style="
                            background-color: var(--cor-fundo-card); /* CORRIGIDO: Usando a variável do CSS */
                            border: 1px solid var(--cor-borda); 
                            border-radius: 8px; 
                            overflow: hidden; 
                            box-shadow: 0 4px 8px var(--sombra-card); /* CORRIGIDO: Usando a variável do CSS */
                            transition: transform 0.2s;
                            <?php echo ($album['deletado'] == 1) ? 'opacity: 0.5; filter: grayscale(100%);' : ''; ?>
                        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            
                            <a href="detalhes_album.php?id=<?php echo $album['id']; ?>" title="<?php echo htmlspecialchars($album['titulo']); ?>">
                                <div class="album-cover-wrapper" style="width: 100%; aspect-ratio: 1 / 1; overflow: hidden; background-color: #333;">
                                    <?php 
                                    $capa_url = $album['capa_url'] ?? '';
                                    if (!empty($capa_url)): ?>
                                        <img src="<?php echo htmlspecialchars($capa_url); ?>" alt="Capa do Álbum: <?php echo htmlspecialchars($album['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: #aaa; text-align: center; font-size: 14px;">
                                            <i class="fas fa-compact-disc fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <div class="card-details" style="padding: 10px;">
                                <h4 style="margin: 0 0 5px 0; font-size: 1em; height: 2.8em; overflow: hidden;">
                                    <a href="detalhes_album.php?id=<?php echo $album['id']; ?>" style="text-decoration: none; color: var(--cor-texto-principal); font-weight: bold;">
                                        <?php echo htmlspecialchars(limitar_texto($album['titulo'], 40)); ?>
                                    </a>
                                </h4>
                                <p style="margin: 0 0 5px 0; font-size: 0.85em; color: var(--cor-texto-secundario);"><?php echo htmlspecialchars($album['nome_artista']); ?> (<?php echo htmlspecialchars($album['ano_lancamento']); ?>)</p>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                    <?php if ($album['situacao'] == 1): // Disponível (Exemplo) ?>
                                        <span class="price-tag" style="font-weight: bold; color: var(--cor-destaque); font-size: 1.1em;">
                                            R$ <?php echo number_format($album['preco_sugerido'] ?? 0.00, 2, ',', '.'); ?>
                                        </span>
                                    <?php elseif ($album['situacao'] == 2): // ESGOTADO (Exemplo) ?>
                                        <span class="status-tag" style="font-weight: bold; color: var(--cor-erro); font-size: 0.9em;">
                                            <?php echo htmlspecialchars($album['status']); ?>
                                        </span>
                                    <?php else: // Outras situações ?>
                                        <span class="status-tag" style="font-weight: bold; color: var(--cor-alerta); font-size: 0.9em;">
                                            <?php echo htmlspecialchars($album['status']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($album['deletado'] == 0): ?>
                                        <a href="editar_album.php?id=<?php echo $album['id']; ?>" class="btn-action-sm" style="font-size: 0.8em; padding: 5px 10px;">
                                            <i class="fas fa-edit"></i> Detalhes
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 0.8em; color: var(--cor-erro);"><i class="fas fa-trash"></i> Excluído</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php 
                $link_base = 'store.php?' . http_build_query([
                    'search_titulo' => $termo_busca,
                    'filter_artista' => $artista_filtro,
                    'filter_tipo' => $tipo_filtro,
                    'filter_situacao' => $situacao_filtro,
                    'filter_formato' => $formato_filtro,
                    'filter_deletado' => $deletado_filtro,
                    // Não inclui a página atual aqui, ela será adicionada pela função
                ]);

                // Nota: A função 'renderizar_paginacao' deve estar no seu funcoes.php
                renderizar_paginacao($pagina_atual, $total_paginas, $link_base);
                ?>

            <?php endif; ?>

            <div style="height: 20px;"></div> </main>
        
        <aside class="sidebar-filters">
            <div class="card" style="padding: 15px;"> 
                
                <h3><i class="fas fa-filter"></i> Filtros de Catálogo</h3>
                
                <form method="GET" action="store.php" class="filters-container">
                    
                    <div class="search-container">
                        <label for="search_titulo">Buscar Título:</label>
                        <input type="text" id="search_titulo" name="search_titulo" 
                                value="<?php echo htmlspecialchars($termo_busca); ?>">
                    </div>

                    <div class="search-container">
                        <label for="filter_artista">Artista:</label>
                        <select id="filter_artista" name="filter_artista">
                            <option value="">-- Todos --</option>
                            <?php foreach ($artistas as $artista): ?>
                                <option value="<?php echo htmlspecialchars($artista['id']); ?>"
                                        <?php echo ($artista_filtro == $artista['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($artista['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-container">
                        <label for="filter_tipo">Tipo:</label>
                        <select id="filter_tipo" name="filter_tipo">
                            <option value="">-- Todos --</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['id']); ?>"
                                        <?php echo ($tipo_filtro == $tipo['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-container">
                        <label for="filter_situacao">Situação:</label>
                        <select id="filter_situacao" name="filter_situacao">
                            <option value="">-- Todas --</option>
                            <?php foreach ($situacoes as $situacao): ?>
                                <option value="<?php echo htmlspecialchars($situacao['id']); ?>"
                                        <?php echo ($situacao_filtro == $situacao['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($situacao['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-container">
                        <label for="filter_formato">Formato:</label>
                        <select id="filter_formato" name="filter_formato">
                            <option value="">-- Todos --</option>
                            <option value="-1" <?php echo ($formato_filtro == -1) ? 'selected' : ''; ?>>-- Sem Formato --</option>
                            <?php foreach ($formatos as $formato): ?>
                                <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                        <?php echo ($formato_filtro == $formato['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formato['descricao']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-container">
                        <label for="filter_deletado">Status:</label>
                        <select id="filter_deletado" name="filter_deletado">
                            <option value="0" <?php echo ($deletado_filtro == 0) ? 'selected' : ''; ?>>Ativos</option>
                            <option value="1" <?php echo ($deletado_filtro == 1) ? 'selected' : ''; ?>>Excluídos (Lixeira)</option>
                            <option value="-1" <?php echo ($deletado_filtro == -1) ? 'selected' : ''; ?>>Todos</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="save-button" style="height: 40px; background-color: #007bff; flex-shrink: 0;">Aplicar Filtros</button>
                        <?php if ($has_filter): ?>
                            <a href="store.php" class="back-link" style="height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">Limpar Filtros</a>
                        <?php endif; ?>
                    </div>
                    
                </form>
            </div>
        </aside>

    </div> 

</div> 

<?php
require_once '../include/footer.php';
?>