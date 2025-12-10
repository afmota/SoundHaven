<?php
// Arquivo: adicionar_artista.php
// Formulário para adição de um novo artista (tabela 'artistas').

require_once '../db/conexao.php';
require_once '../funcoes.php'; 
// require_once '../seguranca.php'; // Inclua sua checagem de login/admin se aplicável

// ----------------------------------------------------
// 1. CARREGAR DADOS AUXILIARES (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
    // Lista de Países (Necessária para o select de origem)
    'paises' => "SELECT id, nome FROM paises ORDER BY nome ASC",
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
// 2. VARIÁVEIS PARA PREENCHIMENTO DO FORMULÁRIO (INÍCIO VAZIO)
// ----------------------------------------------------
$titulo_pagina = "Adicionar Novo Artista";

// Inicializa variáveis como vazias ou com valor padrão (para novo registro)
$id_artista = null; // NULL para indicar que é um INSERT
$nome = '';
$pais_origem = null; 
$genero_principal = '';
$data_inicio = '';
$data_fim = '';
$biografia = '';
$site_oficial = '';
$imagem_url = '';
$ativo = 1; // Artista novo geralmente começa ativo

// URL para onde o formulário será enviado
$action_url = 'salvar_artista.php';

require_once '../include/header.php'; // Assume que você usa header.php
?>

<main class="container">
    <div class="content-header">
        <h2><i class="fas fa-plus-circle"></i> <?php echo $titulo_pagina; ?></h2>
        <a href="artistas.php" class="btn-action"><i class="fas fa-arrow-left"></i> Voltar à Lista</a>
    </div>

    <?php // Sua lógica para exibir $_SESSION['mensagem_status'] e $_SESSION['tipo_mensagem'] aqui ?>

    <form action="<?php echo $action_url; ?>" method="POST" class="form-padrao">
        
        <input type="hidden" name="id_artista" value="<?php echo $id_artista; ?>">

        <div class="form-group">
            <label for="nome">Nome/Apelido do Artista <span class="required">*</span></label>
            <input type="text" id="nome" name="nome" value="<?php echo $nome; ?>" required maxlength="128">
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

        <div class="form-group">
            <label for="genero_principal">Gênero Principal (Ex: Rock, Jazz)</label>
            <input type="text" id="genero_principal" name="genero_principal" value="<?php echo $genero_principal; ?>" maxlength="64">
        </div>

        <div class="form-group-row">
            <div class="form-group">
                <label for="data_inicio">Data de Início das Atividades</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="form-group">
                <label for="data_fim">Data de Fim das Atividades</label>
                <input type="date" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
        </div>

        <div class="form-group-row">
            <div class="form-group">
                <label for="imagem_url">URL da Imagem de Perfil</label>
                <input type="url" id="imagem_url" name="imagem_url" value="<?php echo $imagem_url; ?>" maxlength="512">
            </div>
            <div class="form-group">
                <label for="site_oficial">Site Oficial / Link Externo</label>
                <input type="url" id="site_oficial" name="site_oficial" value="<?php echo $site_oficial; ?>" maxlength="255">
            </div>
        </div>
        
        <div class="form-group">
            <label for="biografia">Biografia</label>
            <textarea id="biografia" name="biografia" rows="5"><?php echo $biografia; ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="ativo">Status</label>
            <select id="ativo" name="ativo">
                <option value="1" <?php echo ($ativo == 1) ? 'selected' : ''; ?>>Ativo (Visível)</option>
                <option value="0" <?php echo ($ativo == 0) ? 'selected' : ''; ?>>Lixeira (Oculto)</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Adicionar Artista</button>
            <a href="artistas.php" class="btn-secondary">Cancelar</a>
        </div>
    </form>
</main>

<?php require_once '../include/footer.php'; ?>