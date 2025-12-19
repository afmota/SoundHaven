// Arquivo: filtro.js (Lógica AJAX para Título e Artista - CORRIGIDA)

$(document).ready(function() {
    
    // Função para renderizar as linhas da tabela a partir dos dados JSON
    function renderTable(response, searchTerm) {
        var tableBody = '';
        
        if (response.length > 0) {
            $.each(response, function(i, album) {
                // Constrói uma linha da tabela (<tr>)
                tableBody += '<tr>';
                tableBody += '<td>' + (album.id || '') + '</td>';
                tableBody += '<td>' + (album.titulo || '') + '</td>';
                tableBody += '<td>' + (album.nome_artista || 'Artista Desconhecido') + '</td>';
                tableBody += '<td>' + (album.data_lancamento || 'N/A') + '</td>';
                tableBody += '<td>' + (album.tipo || 'Não Classificado') + '</td>';
                tableBody += '<td>' + (album.status || 'Desconhecida') + '</td>';
                tableBody += '<td>' + (album.formato || 'Sem Formato') + '</td>';
                
                // CORREÇÃO: Coluna Ações com os ícones de Edição e Exclusão
                tableBody += '<td>';
                
                // Link de EDITAR
                tableBody += '<a href="editar_album.php?id=' + (album.id) + '" title="Editar Álbum">';
                tableBody += '<i class="fa-solid fa-pencil" style="color: #007bff; cursor: pointer;"></i>';
                tableBody += '</a>';
                
                // Ícone de Excluir (Ainda Estático)
                tableBody += '<i class="fa-solid fa-trash-can" style="color: #dc3545; cursor: pointer; margin-left: 8px;" title="Excluir"></i>';
                
                tableBody += '</td>'; 
                
                tableBody += '</tr>';
            });
        } else {
            tableBody = '<tr><td colspan="8" style="text-align: center;">Nenhum álbum encontrado para o termo "' + (searchTerm || 'seleção') + '".</td></tr>';
        }
        
        // Atualiza o corpo da tabela
        $('table tbody').html(tableBody);
    }
    
    // ----------------------------------------------------------------------
    // 1. Lógica do Filtro por TÍTULO
    // ----------------------------------------------------------------------

    $('#search_titulo').on('keyup', function() {
        var searchTerm = $(this).val();
        
        // Se houver digitação no filtro de título, ZERA o dropdown de artista.
        if (searchTerm.length > 0) {
             $('#filter_artista').val('');
        }
        
        if (searchTerm.length >= 1 || searchTerm.length === 0) { 
            
            $.ajax({
                url: 'busca.php',
                method: 'GET',
                data: { query: searchTerm }, 
                dataType: 'json', 
                
                success: function(response) {
                    renderTable(response, searchTerm);
                },
                error: function() {
                    $('table tbody').html('<tr><td colspan="8" style="color: red; text-align: center;">Erro ao carregar dados.</td></tr>');
                }
            });
        }
    });

    // ----------------------------------------------------------------------
    // 2. Lógica do Filtro por ARTISTA
    // ----------------------------------------------------------------------
    
    $('#filter_artista').on('change', function() {
        var artistaId = $(this).val();
        
        // Zera o campo de título.
        if (artistaId.length > 0) {
            $('#search_titulo').val('');
        }
        
        // Dispara a busca se houver uma seleção
        if (artistaId.length > 0) { 
            
            $.ajax({
                url: 'busca_artista.php',
                method: 'GET',
                data: { artista_id: artistaId }, 
                dataType: 'json', 
                
                success: function(response) {
                    renderTable(response, 'Artista Selecionado');
                },
                error: function() {
                    $('table tbody').html('<tr><td colspan="8" style="color: red; text-align: center;">Erro ao carregar dados do artista.</td></tr>');
                }
            });
        } else {
            // Recarrega a lista completa
            $('#search_titulo').val('');
            $('#search_titulo').trigger('keyup'); 
        }
    });
});