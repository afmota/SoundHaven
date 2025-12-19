// Arquivo: importar_store.php (Com a correção da limpeza do nome do artista)
<?php
// Arquivo: importar_store.php
// Rotina de importação de dados ESSENCIAIS para a tabela 'store' via CSV.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php'); // Redireciona para o login
    exit();
}

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// ----------------------------------------------------------------------
// Variáveis de Estado e Resultados
// ----------------------------------------------------------------------
$mensagem_status = '';
$tipo_mensagem = '';
$sucesso_count = 0;
$erro_count = 0;
$artistas_ausentes = [];
$linhas_nao_processadas = 0;

// ----------------------------------------------------------------------
// ESTRUTURA CSV ESPERADA (Separador: ;) - APENAS OS CAMPOS ESSENCIAIS
// ----------------------------------------------------------------------
// O script espera 6 colunas, sendo as 4 primeiras obrigatórias.
// [0] Título (string, NOT NULL)
// [1] Nome do Artista (string, REQUIRED para lookup)
// [2] Tipo ID (int, NOT NULL)
// [3] Situação ID (int, NOT NULL)
// [4] Data Lançamento (date YYYY-MM-DD, optional)
// [5] Deletado (tinyint 0 ou 1, optional)
$colunas = [
    'titulo', 'artista_nome', 'tipo_id', 'situacao', 'data_lancamento', 'deletado'
];


// ----------------------------------------------------------------------
// 1. PROCESSAMENTO DO ARQUIVO
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $mensagem_status = 'Erro no upload do arquivo.';
        $tipo_mensagem = 'erro';
    } elseif ($file['size'] == 0) {
        $mensagem_status = 'O arquivo está vazio.';
        $tipo_mensagem = 'alerta';
    } elseif (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {

        // Array para cache de IDs de artistas já encontrados
        $cache_artistas = [];
        $pdo->beginTransaction();

        try {
            // Pula o cabeçalho se houver (opcional, mas recomendado)
            // fgetcsv($handle, 1000, ";"); 
            
            // CORREÇÃO (Deprecação): Adicionado o caractere de enclosure e escape.
            while (($data = fgetcsv($handle, 1000, ";", '"', '\\')) !== FALSE) {
                // Remove espaços em branco nas extremidades de cada campo
                $data = array_map('trim', $data);

                // Deve ter pelo menos 4 colunas para processamento mínimo
                if (count($data) < 4) {
                    $erro_count++;
                    $linhas_nao_processadas++;
                    continue; 
                }
                
                $titulo = $data[0];
                $artista_nome_sujo = $data[1]; // Nome original do CSV
                $tipo_id = filter_var($data[2], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                $situacao_id = filter_var($data[3], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

                // =========================================================================
                // CORREÇÃO: Limpeza super-robusta do nome do artista.
                // Remove todos os caracteres não-imprimíveis (incluindo espaços não-padrão)
                // e garante que a string seja tratada como UTF-8 pura.
                // =========================================================================
                $artista_nome = preg_replace('/[[:^print:]]/', '', $artista_nome_sujo);
                $artista_nome = trim($artista_nome); 
                // Se a limpeza o deixou vazio, ignora.
                if (empty($artista_nome)) {
                    $erro_count++;
                    $linhas_nao_processadas++;
                    continue;
                }
                
                // Validação mínima dos campos essenciais
                if (empty($titulo) || $tipo_id === null || $situacao_id === null) {
                    $erro_count++;
                    $linhas_nao_processadas++;
                    continue;
                }

                // 1. BUSCA OU CACHE DO ID DO ARTISTA
                $artista_id = null;
                
                // Usa o nome limpo para o cache.
                if (isset($cache_artistas[$artista_nome])) {
                    $artista_id = $cache_artistas[$artista_nome];
                } else {
                    // Consulta: Usa o TRIM(LOWER()) no DB, e passa o nome limpo.
                    $sql_artista = "SELECT id FROM artistas WHERE TRIM(LOWER(nome)) = TRIM(LOWER(:nome))";
                    $stmt_artista = $pdo->prepare($sql_artista);
                    
                    // Passa o nome limpo e trimado para o bind.
                    $stmt_artista->execute([':nome' => $artista_nome]);
                    $resultado_artista = $stmt_artista->fetch(PDO::FETCH_ASSOC);

                    if ($resultado_artista) {
                        $artista_id = $resultado_artista['id'];
                        $cache_artistas[$artista_nome] = $artista_id; // Adiciona ao cache
                    }
                }
                
                // 2. TRATAMENTO DO ARTISTA AUSENTE
                if ($artista_id === null) {
                    // Adiciona o nome original (sujo) do CSV à lista de ausentes para o relatório.
                    $artistas_ausentes[] = $artista_nome_sujo; 
                    $linhas_nao_processadas++;
                    continue; 
                }

                // 3. PREPARAÇÃO DOS DADOS PARA INSERÇÃO (Store)
                $campos = [
                    'titulo' => $titulo,
                    'artista_id' => $artista_id,
                    'tipo_id' => $tipo_id,
                    'situacao' => $situacao_id,
                    // Campos Opcionais
                    'data_lancamento' => $data[4] ?? null, // Index 4: Data Lançamento
                    'deletado' => filter_var($data[5] ?? 0, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? 0, // Index 5: Deletado (Default 0)
                ];
                
                // 4. INSERÇÃO NA TABELA 'store'
                $sql_insert = "INSERT INTO store (
                                titulo, artista_id, data_lancamento, tipo_id, situacao, deletado, 
                                criado_em
                              ) VALUES (
                                :titulo, :artista_id, :data_lancamento, :tipo_id, :situacao, :deletado, 
                                NOW()
                              )";
                
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute($campos);

                $sucesso_count++;
            }

            fclose($handle);
            $pdo->commit();
            
            // Finalização do processamento
            if (empty($artistas_ausentes)) {
                $mensagem_status = "Importação concluída com sucesso! {$sucesso_count} registros inseridos.";
                $tipo_mensagem = 'sucesso';
            } else {
                $artistas_ausentes = array_unique($artistas_ausentes); // Remove duplicatas
                $mensagem_status = "Importação parcial concluída. {$sucesso_count} registros inseridos. Artistas ausentes: " . implode(', ', $artistas_ausentes) . ".";
                $tipo_mensagem = 'alerta';
            }

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $mensagem_status = "Erro grave no banco de dados. Transação desfeita. Detalhe: " . $e->getMessage();
            $tipo_mensagem = 'erro';
            $sucesso_count = 0;
            $erro_count++;
        }
    }
}

// ----------------------------------------------------------------------
// 2. HTML DO FORMULÁRIO E RESULTADOS
// ----------------------------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1>Importação em Lote para o Catálogo (Store)</h1>
                <a href="store.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar para o Catálogo
                </a>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <div class="card" style="margin-top: 20px;">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-file-csv"></i> Carregar Arquivo CSV</h3>

                <p style="margin-bottom: 20px; font-size: 0.9em; color: var(--cor-texto-secundario);">
                    O arquivo deve ser formatado em CSV, utilizando o **ponto e vírgula (`;`) como separador**. 
                    A ordem das **6 colunas** esperadas é: 
                    <br><br>
                    <code style="background-color: #333; padding: 5px 10px; border-radius: 4px; display: inline-block; white-space: normal; line-height: 1.5;">
                        **Título**; **Nome do Artista**; **Tipo ID**; **Situação ID**; Data Lançamento (YYYY-MM-DD); Deletado (0 ou 1)
                    </code>
                    <br><br>
                    **Artistas Ausentes:** Se um artista não for encontrado pelo nome, o registro será ignorado e listado no relatório abaixo.
                </p>

                <form method="POST" enctype="multipart/form-data" action="importar_store.php" class="edit-form">
                    <label for="csv_file">Selecione o arquivo CSV:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    
                    <div class="form-actions" style="margin-top: 20px;">
                        <button type="submit" class="save-button">
                            <i class="fas fa-upload"></i> Processar Importação
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($artistas_ausentes)): ?>
                <div class="card" style="margin-top: 30px;">
                    <h3 style="color: var(--cor-alerta);"><i class="fas fa-exclamation-triangle"></i> Artistas Ausentes</h3>
                    <p>Os seguintes artistas não foram encontrados no banco de dados. Crie-os e tente re-importar os registros relacionados a eles:</p>
                    
                    <ul style="list-style-type: disc; margin-left: 20px; margin-top: 10px; padding: 0;">
                        <?php foreach (array_unique($artistas_ausentes) as $artista): ?>
                            <li style="color: var(--cor-texto-principal); margin-bottom: 5px;"><?php echo htmlspecialchars($artista); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($sucesso_count > 0 || $erro_count > 0 || $linhas_nao_processadas > 0): ?>
                <div class="card" style="margin-top: 30px;">
                    <h3><i class="fas fa-chart-bar"></i> Resumo da Execução</h3>
                    <p>Processamento concluído:</p>
                    <ul>
                        <li style="color: var(--cor-sucesso);">Registros Inseridos com Sucesso: **<?php echo $sucesso_count; ?>**</li>
                        <li style="color: var(--cor-erro);">Registros com Erro/Linhas Inválidas: **<?php echo $erro_count; ?>**</li>
                        <?php if ($linhas_nao_processadas > 0): ?>
                        <li style="color: var(--cor-alerta);">Total de Linhas Não Processadas: **<?php echo $linhas_nao_processadas; ?>**</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </main>
    </div> 
</div> 

<?php require_once '../include/footer.php'; ?>