// Arquivo: filtro.js (Lógica AJAX Simples)

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
                
                // Coluna Ações: Vazia
                tableBody += '<td></td>'; 
                
                tableBody += '</tr>';
            });
        } else {
            tableBody = '<tr><td colspan="8" style="text-align: center;">Nenhum álbum encontrado para o termo "' + searchTerm + '".</td></tr>';
        }
        
        // Atualiza o corpo da tabela
        $('table tbody').html(tableBody);
    }
    
    // Monitora o evento de digitação no campo #search
    $('#search').on('keyup', function() {
        var searchTerm = $(this).val();
        
        // CORREÇÃO: Faz a busca a partir de 1 caractere ou quando está vazio (para recarregar tudo)
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
});