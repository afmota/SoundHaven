// Arquivo: filtro.js (Lógica AJAX para Título e Artista)

$(document).ready(function() {
    
    // Função para renderizar as linhas da tabela a partir dos dados JSON
    function renderTable(response, searchTerm) {
        var tableBody = '';
        
        if (response.length > 0) {
            $.each(response, function(i, album) {
                // Constrói uma linha da tabela (<tr>). Os nomes das chaves (album.titulo, etc.)
                // devem corresponder aos aliases da sua consulta SQL.
                tableBody += '<tr>';
                tableBody += '<td>' + (album.id || '') + '</td>';
                tableBody += '<td>' + (album.titulo || '') + '</td>';
                tableBody += '<td>' + (album.nome_artista || 'Artista Desconhecido') + '</td>';
                tableBody += '<td>' + (album.data_lancamento || 'N/A') + '</td>';
                tableBody += '<td>' + (album.tipo || 'Não Classificado') + '</td>';
                tableBody += '<td>' + (album.status || 'Desconhecida') + '</td>';
                tableBody += '<td>' + (album.formato || 'Sem Formato') + '</td>';
                
                // Coluna Ações: Vazia, conforme combinado
                tableBody += '<td></td>'; 
                
                tableBody += '</tr>';
            });
        } else {
            tableBody = '<tr><td colspan="8" style="text-align: center;">Nenhum álbum encontrado para o termo "' + (searchTerm || 'seleção') + '".</td></tr>';
        }
        
        // Atualiza o corpo da tabela
        $('table tbody').html(tableBody);
    }
    
    // ----------------------------------------------------------------------
    // 1. Lógica do Filtro por TÍTULO (Disparado a cada tecla)
    // ----------------------------------------------------------------------

    $('#search_titulo').on('keyup', function() {
        var searchTerm = $(this).val();
        
        // Se houver digitação no filtro de título, ZERA o dropdown de artista.
        if (searchTerm.length > 0) {
             $('#filter_artista').val('');
        }
        
        // Dispara a busca a partir do primeiro caractere (ou se estiver vazio, para recarregar tudo)
        if (searchTerm.length >= 1 || searchTerm.length === 0) { 
            
            $.ajax({
                url: 'busca.php', // Usa o arquivo para busca por texto
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
    // 2. Lógica do Filtro por ARTISTA (Disparado ao selecionar)
    // ----------------------------------------------------------------------
    
    $('#filter_artista').on('change', function() {
        var artistaId = $(this).val();
        
        // Zera o campo de título, pois o foco mudou para o filtro de artista.
        if (artistaId.length > 0) {
            $('#search_titulo').val('');
        }
        
        // Dispara a busca apenas se houver uma seleção (o valor vazio é a opção default)
        if (artistaId.length > 0) { 
            
            $.ajax({
                url: 'busca_artista.php', // Usa o novo arquivo para busca por ID
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
            // Se a opção "-- Selecione um Artista --" for escolhida, recarrega a lista completa
            // Simula um 'keyup' vazio no filtro de título para recarregar tudo
            $('#search_titulo').val('');
            $('#search_titulo').trigger('keyup'); 
        }
    });
});