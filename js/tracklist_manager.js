$(document).ready(function() {
    // 1. Re-numeração automática
    function renumber() {
        $('#tracklist-body tr').each((i, el) => {
            $(el).find('.track-num').text(i + 1);
        });
    }

    // 2. Adicionar Manual
    $('#btn-add-manual').click(() => {
        const row = `<tr>
            <td class="track-num">0</td>
            <td contenteditable="true" class="editable-title">Nova Faixa</td>
            <td contenteditable="true" class="editable-duration">0:00</td>
            <td><button type="button" class="btn-remove"><i class="fas fa-trash"></i></button></td>
        </tr>`;
        $('#tracklist-body').append(row);
        renumber();
    });

    // 3. Remover Faixa
    $(document).on('click', '.btn-remove', function() {
        if(confirm('Remover esta faixa?')) {
            $(this).closest('tr').remove();
            renumber();
        }
    });

    // 4. Importar do Discogs (Integrando com seu PHP de API)
    $('#btn-import-discogs').click(function() {
        const cat = $('#cat-numero-import').val();
        if(!cat) return alert('Digite o número de catálogo!');

        $('#import-status').html('<p><i class="fas fa-spinner fa-spin"></i> Consultando API...</p>');

        $.post('/colecao/importar_faixas_api.php', { numero_catalogo: cat, colecao_id: 1 }, function(res) {
            if(res.success) {
                if(confirm('Lançamento: ' + res.release_title + '\nSubstituir lista atual pelas faixas do Discogs?')) {
                    $('#tracklist-body').empty();
                    res.tracklist.forEach(t => {
                        $('#tracklist-body').append(`<tr>
                            <td class="track-num">${t.numero_faixa}</td>
                            <td contenteditable="true" class="editable-title">${t.titulo}</td>
                            <td contenteditable="true" class="editable-duration">${t.duracao || '0:00'}</td>
                            <td><button type="button" class="btn-remove"><i class="fas fa-trash"></i></button></td>
                        </tr>`);
                    });
                    $('#import-status').html('<p class="text-success">Importado com sucesso!</p>');
                }
            }
        }).fail(() => alert('Erro na busca.'));
    });

    // 5. O SALVAMENTO FINAL (Coleta tudo e envia via JSON)
    $('#btn-save-full-album').click(function() {
        const albumData = {
            id: $('#album_id').val(),
            titulo: $('#titulo_album').val(),
            // ... outros campos do álbum ...
            faixas: []
        };

        $('#tracklist-body tr').each(function() {
            albumData.faixas.push({
                numero: $(this).find('.track-num').text(),
                titulo: $(this).find('.editable-title').text(),
                duracao: $(this).find('.editable-duration').text()
            });
        });

        $.ajax({
            url: 'processar_edicao.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(albumData),
            success: (res) => { alert('Álbum e faixas salvos!'); window.location.href='colecao.php'; }
        });
    });
});