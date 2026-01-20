$(document).ready(function() {
    // 1. INICIALIZAÇÃO DOS COMPONENTES (Mantendo o que você já tinha)
    
    // Para artistas, apenas seleção
    $('.select2-artistas').select2({ 
        placeholder: "Selecione o(s) artista(s)",
        width: '100%'
    });

    // Para gêneros, estilos e produtores, habilitamos tags
    $('.select2-tags').select2({ 
        placeholder: "Selecione ou digite algo novo...",
        tags: true, 
        tokenSeparators: [',', ';'], 
        width: '100%'
    });

    // 2. PREVISÃO DE CAPA (Mantendo funcionalidade de interface)
    $('#capa_url').on('change', function() {
        const url = $(this).val();
        if (url) {
            $('#img-preview').attr('src', url);
        }
    });
});

// Função auxiliar para varrer a tabela de faixas
function capturarDadosTracklist() {
    let faixas = [];
    $('#tracklist-body tr').each(function() {
        const linha = $(this);
        faixas.push({
            posicao: linha.find('.track-pos').val() || linha.find('td:first').text(),
            titulo: linha.find('.track-title').val(),
            duracao: linha.find('.track-duration').val()
        });
    });
    return faixas;
}