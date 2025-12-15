<?php
// Arquivo: src/View/colecao.modal.view.php

// Este arquivo contém a estrutura HTML do modal,
// O JavaScript para abri-lo está na colecao.view.php.
?>

<div id="albumModal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        
        <div id="modal-loader" style="text-align: center; padding: 50px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3em; color: var(--cor-destaque);"></i>
            <p style="margin-top: 15px;">Carregando detalhes do álbum...</p>
        </div>
        
        <div id="modal-details" style="display: none;">
            <div class="modal-grid">
                
                <div class="modal-col-capa">
                    <img id="modal-capa-img" class="modal-capa" src="" alt="Capa do Álbum">
                </div>
                
                <div class="modal-col-details modal-details-container">
                    <h2 id="modal-titulo"></h2>
                    <p id="modal-artistas" style="color: var(--cor-destaque); font-weight: bold;"></p>
                    <p id="modal-lancamento"></p>
                    <p id="modal-gravadora"></p>

                    <div id="modal-relacionamentos" class="modal-details-section">
                        </div>

                    <div id="modal-copia" class="modal-details-section">
                        <h3>Detalhes da Cópia</h3>
                        <div class="modal-info-group">
                            <div class="modal-info-item">
                                <strong>Formato</strong>
                                <span id="modal-formato"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Aquisição</strong>
                                <span id="modal-aquisicao"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Preço Pago</strong>
                                <span id="modal-preco"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Condição</strong>
                                <span id="modal-condicao"></span>
                            </div>
                            <div class="modal-info-item">
                                <strong>Nº Catálogo</strong>
                                <span id="modal-catalogo"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="modal-observacoes" class="modal-details-section">
                        <h3>Observações</h3>
                        <p id="modal-obs-text" style="white-space: pre-wrap;"></p>
                    </div>

                    <div id="modal-tracklist" class="modal-details-section">
                        <h3>Lista de Faixas</h3>
                        <div id="import-message-area">
                            <ul id="tracklist-ul" style="list-style-type: none; padding-left: 0;">
                                <li id="tracklist-status">Carregando lista de faixas...</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="modal-actions" class="modal-actions">
                </div>
            
        </div>
    </div>
</div>