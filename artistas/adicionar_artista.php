<?php
// Arquivo: adicionar_artista.php
// Formulário para adição de um NOVO artista (INSERT na tabela 'artistas').

// Inclusão de arquivos essenciais
require_once '../db/conexao.php';
require_once '../funcoes.php'; 
// require_once '../seguranca.php'; // Inclua sua checagem de login/admin se aplicável

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';

// ----------------------------------------------------
// 1. CARREGAR DADOS AUXILIARES (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
    // Lista de Países
    'paises' => "SELECT id, nome FROM paises ORDER BY nome ASC",
    // Lista de Gêneros
    'generos' => "SELECT id, descricao FROM generos ORDER BY descricao ASC",
];

try {
    foreach ($sqls as $nome => $sql) {
        $stmt = $pdo->query($sql);
        $listas[$nome] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    die("Erro ao carregar listas de apoio: " . $e->getMessage());
}

// Trata o status da URL (se houver erro no salvamento, redireciona para cá)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $tipo_mensagem = $_GET['status'];
    $mensagem_status = urldecode($_GET['msg']);
}

// ----------------------------------------------------
// 2. VARIÁVEIS PARA PREENCHIMENTO DO FORMULÁRIO (TUDO VAZIO/PADRÃO)
// ----------------------------------------------------
$titulo_pagina = "Adicionar Novo Artista";

// Todas as variáveis são inicializadas como nulas ou vazias
$id_artista = null; // CRUCIAL: Sinaliza para salvar_artista.php que é um INSERT
$nome = '';
$pais_origem = null; 
$genero_principal = null; 
$data_inicio = '';
$data_fim = '';
$biografia = '';
$site_oficial = '';
$imagem_url = '';
$ativo = 1; // Artistas novos começam como Ativos

// URL para onde o formulário será enviado
$action_url = 'salvar_artista.php';

require_once '../include/header.php'; 
?>

<style>
/* CSS do editar_colecao.php para 2 colunas */
.form-row-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px; /* Espaço entre as colunas */
}
.form-row-2-col > div {
    display: flex;
    flex-direction: column;
}
.form-row-2-col > div > label {
    margin-bottom: 5px;
}
</style>

<div class="container" style="padding-top: 100px; padding-bottom: 50px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1><?php echo $titulo_pagina; ?></h1>
                <a href="artistas.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar à Lista
                </a>
            </div>

            <?php // Exibição de Mensagens de Status ?>
            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>
            
            <div class="card">
                <p class="intro-text">Preencha os dados abaixo para adicionar um **novo artista**.</p>

                <form method="POST" action="<?php echo $action_url; ?>" class="edit-form">
                    
                    <input type="hidden" name="id_artista" value="<?php echo $id_artista; ?>">
                    
                    <div class="colecao-grid" style="align-items: start;">
                        
                        <fieldset>
                            <legend><i class="fas fa-user"></i> Informações Básicas do Artista</legend>
                            
                            <div class="form-group">
                                <label for="nome">Nome/Apelido do Artista <span class="required">*</span></label>
                                <input type="text" id="nome" name="nome" value="<?php echo $nome; ?>" required maxlength="128">
                            </div>

                            <div class="form-group">
                                <label for="genero_principal">Gênero Principal</label>
                                <select id="genero_principal" name="genero_principal">
                                    <option value="">-- Selecione um Gênero --</option>
                                    <?php foreach ($listas['generos'] as $g) : ?>
                                        <option value="<?php echo $g['id']; ?>" <?php echo ($genero_principal == $g['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g['descricao']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="pais_origem">País de Origem</label>
                                <select id="pais_origem" name="pais_origem">
                                    <option value="">-- Selecione um País --</option>
                                    <?php foreach ($listas['paises'] as $p) : ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo ($pais_origem == $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row-2-col">
                                <div>
                                    <label for="data_inicio">Data de Início das Atividades</label>
                                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                                </div>
                                <div>
                                    <label for="data_fim">Data de Fim das Atividades</label>
                                    <input type="date" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                                </div>
                            </div>
                        </fieldset>
                        
                        <fieldset style="margin-top: 20px;">
                            <legend><i class="fas fa-globe"></i> Mídia e Biografia</legend>

                            <div class="form-group">
                                <label for="site_oficial">Site Oficial / Link Externo</label>
                                <input type="url" id="site_oficial" name="site_oficial" value="<?php echo $site_oficial; ?>" maxlength="255">
                            </div>

                            <div class="form-group">
                                <label for="imagem_url">URL da Imagem de Perfil</label>
                                <input type="url" id="imagem_url" name="imagem_url" value="<?php echo $imagem_url; ?>" maxlength="512">
                            </div>
                            
                            <div class="form-group">
                                <label for="biografia">Biografia</label>
                                <textarea id="biografia" name="biografia" rows="8"><?php echo $biografia; ?></textarea>
                            </div>
                        </fieldset>

                    </div> 
                    
                    <div class="form-actions large-gap" style="margin-top: 20px;">
                        <a href="artistas.php" class="back-link secondary-action">
                            <i class="fas fa-times-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="save-button">
                            <i class="fas fa-plus-circle"></i> Adicionar Artista
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<?php require_once '../include/footer.php'; ?>