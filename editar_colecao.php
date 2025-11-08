<?php
// Arquivo: editar_colecao.php
// Lógica e formulário para editar um item existente na Coleção Pessoal (tabela 'colecao').

require_once 'conexao.php';
require_once 'funcoes.php'; 

// ----------------------------------------------------
// 0. VALIDAÇÃO INICIAL E PREPARAÇÃO
// ----------------------------------------------------
$id_colecao = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$mensagem_status = '';
$tipo_mensagem = '';

if (!$id_colecao) {
    die("Erro: ID da Coleção não fornecido.");
}

// Inicializa arrays para carregar/manter dados
$dados_colecao = [];
$selecionados_mn = [
    'artistas' => [],
    'produtores' => [],
    'generos' => [],
    'estilos' => []
];

// ----------------------------------------------------
// 1. CARREGAR DADOS PRINCIPAIS E RELACIONAMENTOS (GET/Carga Inicial)
// ----------------------------------------------------
try {
    // 1.1. Carregar Dados da Coleção
    $sql_colecao = "SELECT * FROM colecao WHERE id = :id";
    $stmt_colecao = $pdo->prepare($sql_colecao);
    $stmt_colecao->execute([':id' => $id_colecao]);
    $dados_colecao = $stmt_colecao->fetch(PDO::FETCH_ASSOC);

    if (!$dados_colecao) {
        die("Erro: Item da Coleção (ID {$id_colecao}) não encontrado.");
    }
    
    // 1.2. Carregar Relacionamentos M:N (IDs)
    $relacionamentos_tabelas = [
        'artistas' => 'colecao_artista',
        'produtores' => 'colecao_produtor',
        'generos' => 'colecao_genero',
        'estilos' => 'colecao_estilo',
    ];

    foreach ($relacionamentos_tabelas as $key => $tabela_relacao) {
        // Ex: 'artistas' -> 'artista_id' ; 'generos' -> 'genero_id'
        $coluna_id = rtrim($key, 's') . '_id';
        // Ajuste para 'produtor_id'
        if ($key === 'produtores') $coluna_id = 'produtor_id'; 
        
        $sql_mn = "SELECT {$coluna_id} FROM {$tabela_relacao} WHERE colecao_id = :id";
        $stmt_mn = $pdo->prepare($sql_mn);
        $stmt_mn->execute([':id' => $id_colecao]);
        
        // Armazena apenas os IDs para uso nos 'selected' do HTML
        $selecionados_mn[$key] = $stmt_mn->fetchAll(PDO::FETCH_COLUMN, 0);
    }

} catch (\PDOException $e) {
    die("Erro ao carregar dados do álbum: " . $e->getMessage());
}

// ----------------------------------------------------
// 2. CARREGAR DADOS DAS TABELAS DE APOIO (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
    'artistas' => "SELECT id, nome FROM artistas ORDER BY nome ASC",
    'produtores' => "SELECT id, nome FROM produtores ORDER BY nome ASC",
    'gravadoras' => "SELECT id, nome FROM gravadoras ORDER BY nome ASC",
    'generos' => "SELECT id, descricao FROM generos ORDER BY descricao ASC",
    'formatos' => "SELECT id, descricao FROM formatos ORDER BY descricao ASC",
    'estilos' => "SELECT id, descricao FROM estilos ORDER BY descricao ASC", 
];

try {
    foreach ($sqls as $nome => $sql) {
        $stmt = $pdo->query($sql);
        $listas[$nome] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    die("Erro ao carregar listas de apoio: " . $e->getMessage());
}


// ----------------------------------------------------
// 3. PROCESSAMENTO DO FORMULÁRIO (UPDATE com Sincronização M:N)
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // 3.0. Sanitização e Validação
        // Dados principais (Coleção)
        $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
        $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
        $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null;
        $data_aquisicao = filter_input(INPUT_POST, 'data_aquisicao', FILTER_SANITIZE_SPECIAL_CHARS);
        $numero_catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT) ?: null;
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

        // IDs (Relacionamentos 1:N)
        $gravadora_id = filter_input(INPUT_POST, 'gravadora_id', FILTER_VALIDATE_INT) ?: null;
        $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);

        // IDs M:N (Recebidos como arrays)
        $artistas_ids = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $produtores_ids = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $generos_ids = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $estilos_ids = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []; 
        
        // Validação Mínima
        if (!$titulo || !$data_aquisicao || !$formato_id || empty($artistas_ids)) {
            throw new Exception("Campos obrigatórios (Título, Data de Aquisição, Formato, Artista) não preenchidos.");
        }
        
        // INÍCIO DA TRANSAÇÃO: Garantir a integridade do UPDATE e dos M:N
        $pdo->beginTransaction();

        // 3.1. UPDATE na tabela principal: 'colecao'
        $sql_update = "UPDATE colecao SET 
                        titulo = :titulo, 
                        data_lancamento = :data_lancamento, 
                        capa_url = :capa_url, 
                        data_aquisicao = :data_aquisicao, 
                        numero_catalogo = :numero_catalogo, 
                        preco = :preco, 
                        condicao = :condicao, 
                        observacoes = :observacoes, 
                        gravadora_id = :gravadora_id, 
                        formato_id = :formato_id
                       WHERE id = :id";
                         
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            ':titulo' => $titulo,
            ':data_lancamento' => $data_lancamento ?: null,
            ':capa_url' => $capa_url,
            ':data_aquisicao' => $data_aquisicao,
            ':numero_catalogo' => $numero_catalogo,
            ':preco' => $preco,
            ':condicao' => $condicao,
            ':observacoes' => $observacoes,
            ':gravadora_id' => $gravadora_id,
            ':formato_id' => $formato_id,
            ':id' => $id_colecao,
        ]);
        
        // 3.2. SINCRONIZAÇÃO DOS RELACIONAMENTOS M:N (Deletar tudo e Reinserir)
        $relacionamentos_sincronizar = [
            'artistas' => ['tabela' => 'colecao_artista', 'coluna_id' => 'artista_id', 'ids' => $artistas_ids],
            'produtores' => ['tabela' => 'colecao_produtor', 'coluna_id' => 'produtor_id', 'ids' => $produtores_ids],
            'generos' => ['tabela' => 'colecao_genero', 'coluna_id' => 'genero_id', 'ids' => $generos_ids],
            'estilos' => ['tabela' => 'colecao_estilo', 'coluna_id' => 'estilo_id', 'ids' => $estilos_ids],
        ];

        foreach ($relacionamentos_sincronizar as $item) {
            $tabela = $item['tabela'];
            $coluna = $item['coluna_id'];
            $ids_novos = $item['ids'];

            // A. Deleta todos os registros antigos para este colecao_id
            $sql_delete = "DELETE FROM {$tabela} WHERE colecao_id = :id";
            $pdo->prepare($sql_delete)->execute([':id' => $id_colecao]);

            // B. Insere todos os novos IDs (se houver)
            if (!empty($ids_novos)) {
                $sql_insert = "INSERT INTO {$tabela} (colecao_id, {$coluna}) VALUES (:cid, :colid)";
                $stmt_insert = $pdo->prepare($sql_insert);
                foreach ($ids_novos as $novo_id) {
                    if (filter_var($novo_id, FILTER_VALIDATE_INT)) {
                        $stmt_insert->execute([':cid' => $id_colecao, ':colid' => $novo_id]);
                    }
                }
            }
        }


        // FIM DA TRANSAÇÃO: Confirma todas as operações.
        $pdo->commit();
        
        $mensagem_status = "Álbum '{$titulo}' (ID: {$id_colecao}) atualizado na Coleção com sucesso!";
        $tipo_mensagem = 'sucesso';
        
        // CORREÇÃO: Redireciona para a listagem da coleção com a mensagem de sucesso.
        header("Location: colecao.php?status=sucesso&msg=" . urlencode($mensagem_status));
        exit;

    } catch (\PDOException $e) {
        $pdo->rollBack();
        $mensagem_status = "Erro ao salvar na coleção: Falha no banco de dados. " . $e->getMessage();
        $tipo_mensagem = 'erro';
        // Recarrega dados do POST para pré-preencher em caso de erro
        $dados_colecao = $_POST;
    } catch (Exception $e) {
        $mensagem_status = "Erro de validação: " . $e->getMessage();
        $tipo_mensagem = 'erro';
        $dados_colecao = $_POST;
    }
}

// O bloco abaixo é necessário para recarregar os dados M:N corretos se o formulário for submetido
// e falhar (para que os selects múltiplos mantenham o que o usuário tentou salvar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($dados_colecao['titulo'])) {
    // Se houve um erro, usamos os dados POST para pré-selecionar
    $selecionados_mn['artistas'] = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    $selecionados_mn['produtores'] = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    $selecionados_mn['generos'] = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    $selecionados_mn['estilos'] = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
}


// ----------------------------------------------------
// 4. HTML DO FORMULÁRIO (Com pré-preenchimento aplicado)
// ----------------------------------------------------
require_once 'header.php'; 
?>

<div class="container">
    <div class="form-container">
        <h1>Editar Item na Coleção (ID: <?php echo $id_colecao; ?>)</h1>
        <p>Ajuste os detalhes específicos da sua cópia física do álbum "<?php echo htmlspecialchars($dados_colecao['titulo'] ?? 'N/A'); ?>".</p>

        <?php if (!empty($mensagem_status)): ?>
            <p class="<?php echo $tipo_mensagem; ?>" 
               style="padding: 10px; border: 1px solid <?php echo ($tipo_mensagem === 'sucesso' ? 'green' : 'red'); ?>; 
                      background-color: <?php echo ($tipo_mensagem === 'sucesso' ? '#e6ffe6' : '#ffe6e6'); ?>; 
                      color: <?php echo ($tipo_mensagem === 'sucesso' ? 'green' : 'red'); ?>; 
                      margin-bottom: 20px;">
                <?php echo htmlspecialchars($mensagem_status); ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="editar_colecao.php?id=<?php echo $id_colecao; ?>">
            
            <fieldset>
                <legend>Metadados do Álbum (Cópia)</legend>

                <label for="titulo">Título do Álbum:*</label>
                <input type="text" id="titulo" name="titulo" required 
                       value="<?php echo htmlspecialchars($dados_colecao['titulo'] ?? ''); ?>">
                       
                <label for="data_lancamento">Data de Lançamento:</label>
                <input type="date" id="data_lancamento" name="data_lancamento"
                       value="<?php echo htmlspecialchars($dados_colecao['data_lancamento'] ?? ''); ?>">

                <label for="artistas">Artista(s):*</label>
                <select id="artistas" name="artistas[]" multiple required style="min-height: 120px;">
                    <?php 
                    $selecionados = $selecionados_mn['artistas'];
                    foreach ($listas['artistas'] as $artista): 
                        // Note o uso de array_map para garantir que ambos sejam strings ou int
                        $is_selected = in_array($artista['id'], array_map('intval', $selecionados));
                    ?>
                        <option value="<?php echo $artista['id']; ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($artista['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos artistas. **Obrigatório**</small>

                <label for="produtores">Produtor(es):</label>
                <select id="produtores" name="produtores[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php 
                    $selecionados = $selecionados_mn['produtores'];
                    foreach ($listas['produtores'] as $produtor): 
                        $is_selected = in_array($produtor['id'], array_map('intval', $selecionados));
                    ?>
                        <option value="<?php echo $produtor['id']; ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($produtor['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos produtores.</small>

                <label for="generos">Gênero(s) Principal(is):</label>
                <select id="generos" name="generos[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php 
                    $selecionados = $selecionados_mn['generos'];
                    foreach ($listas['generos'] as $genero): 
                        $is_selected = in_array($genero['id'], array_map('intval', $selecionados));
                    ?>
                        <option value="<?php echo $genero['id']; ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genero['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos gêneros **principais** (Ex: Rock, Jazz).</small>

                <label for="estilos">Estilos/Subgêneros:</label>
                <select id="estilos" name="estilos[]" multiple style="min-height: 120px;">
                    <option value="">-- Nenhum --</option>
                    <?php 
                    $selecionados = $selecionados_mn['estilos'];
                    foreach ($listas['estilos'] as $estilo): 
                        $is_selected = in_array($estilo['id'], array_map('intval', $selecionados));
                    ?>
                        <option value="<?php echo $estilo['id']; ?>"
                                <?php echo $is_selected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estilo['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Use Ctrl (ou Cmd) para selecionar múltiplos estilos **detalhados** (Ex: Post-Punk, Bossa Nova).</small>


                <label for="gravadora_id">Gravadora:</label>
                <select id="gravadora_id" name="gravadora_id">
                    <option value="">-- Selecione (Opcional) --</option>
                    <?php 
                    $gravadora_pre = $dados_colecao['gravadora_id'] ?? null;
                    foreach ($listas['gravadoras'] as $gravadora): 
                    ?>
                        <option value="<?php echo $gravadora['id']; ?>"
                                <?php echo ($gravadora_pre == $gravadora['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($gravadora['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="capa_url">URL da Capa (Imagem):</label>
                <input type="url" id="capa_url" name="capa_url" placeholder="http://ouhttps://..."
                       value="<?php echo htmlspecialchars($dados_colecao['capa_url'] ?? ''); ?>">

            </fieldset>

            <fieldset>
                <legend>Detalhes da Sua Cópia (Coleção)</legend>
                
                <label for="data_aquisicao">Data de Aquisição:*</label>
                <input type="date" id="data_aquisicao" name="data_aquisicao" required 
                       value="<?php echo htmlspecialchars($dados_colecao['data_aquisicao'] ?? ''); ?>">
                
                <label for="numero_catalogo">Número de Catálogo:</label>
                <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Ex: R-1234567"
                       value="<?php echo htmlspecialchars($dados_colecao['numero_catalogo'] ?? ''); ?>">
                
                <label for="formato_id">Formato:*</label>
                <select id="formato_id" name="formato_id" required>
                    <option value="">-- Selecione o Formato --</option>
                    <?php 
                    $formato_pre = $dados_colecao['formato_id'] ?? null;
                    foreach ($listas['formatos'] as $formato): 
                    ?>
                        <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                <?php echo ($formato_pre == $formato['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formato['descricao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="preco">Preço Pago:</label>
                <input type="number" step="0.01" min="0" id="preco" name="preco" placeholder="99.99"
                       value="<?php echo htmlspecialchars($dados_colecao['preco'] ?? ''); ?>">
                
                <label for="condicao">Condição:</label>
                <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint"
                       value="<?php echo htmlspecialchars($dados_colecao['condicao'] ?? ''); ?>">
                
                <label for="observacoes">Observações:</label>
                <textarea id="observacoes" name="observacoes" rows="3"><?php 
                    echo htmlspecialchars($dados_colecao['observacoes'] ?? ''); 
                ?></textarea>
                
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="save-button">Salvar Alterações</button>
                <a href="colecao.php" class="back-link">Voltar para a Coleção</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>