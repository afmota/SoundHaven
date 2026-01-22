<div class="container">
    <div class="main-layout">
        <div class="content-area">
            <div class="card-estatisticas">
                <h2 style="margin-bottom: 25px; color: var(--cor-texto-principal); border-bottom: 3px solid var(--cor-destaque); padding-bottom: 10px;">
                    Estatísticas da Coleção 📊
                </h2>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Itens na Coleção</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $stats['geral']['total_itens']; ?></p>
                    </div>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Valor Total Registrado</h3>
                        <p style="font-size: 2em; font-weight: bold; color: #28a745;">R$ <?php echo number_format($stats['geral']['soma_precos'], 2, ',', '.'); ?></p>
                    </div>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Preço Médio por Item</h3>
                        <p style="font-size: 2em; font-weight: bold;">R$ <?php echo number_format($stats['geral']['media_preco'], 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px;">
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Músicas Registradas</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $stats['faixas']['total_faixas']; ?></p>
                    </div>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Tempo Total de Reprodução</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $stats['faixas']['tempo_total']; ?></p>
                    </div>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="font-size: 0.9em; color: var(--cor-texto-secundario);">Duração Média por Música</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $stats['faixas']['tempo_medio']; ?></p>
                    </div>
                </div>

                <div class="stats-detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    
                    <div class="card" style="padding: 20px; display: flex; flex-direction: column; align-items: center;">
                        <h3 style="align-self: flex-start;">Distribuição por Formato</h3>
                        <div style="width: 100%; max-width: 250px; margin: 0 auto 20px auto;">
                            <canvas id="formatoPieChart"></canvas>
                        </div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--cor-destaque); text-align: left;">
                                    <th style="padding: 8px;">Formato</th>
                                    <th style="padding: 8px;">Qtd</th>
                                    <th style="padding: 8px;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['formatos'] as $f): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 8px;"><?php echo htmlspecialchars($f['formato']); ?></td>
                                    <td style="padding: 8px;"><?php echo $f['total']; ?></td>
                                    <td style="padding: 8px;"><?php echo number_format($f['percentual'], 1, ',', '.'); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 30px;">
                        <div class="card" style="padding: 20px; flex: 1;">
                            <h3>Lançamentos por Ano 📅</h3>
                            <canvas id="anosBarChart"></canvas>
                        </div>
                        <div class="card" style="padding: 20px; flex: 1;">
                            <h3>Primeira e Última Aquisição 🛍️</h3>
                            <div style="margin-top: 20px;">
                                <div style="margin-bottom: 15px;">
                                    <p style="color: var(--cor-texto-secundario); font-size: 0.9em; margin-bottom: 5px;">Primeira:</p>
                                    <p style="font-size: 1.2em; font-weight: bold;"><?php echo date('d/m/Y', strtotime($stats['geral']['data_mais_antiga'])); ?></p>
                                </div>
                                <div>
                                    <p style="color: var(--cor-texto-secundario); font-size: 0.9em; margin-bottom: 5px;">Última:</p>
                                    <p style="font-size: 1.2em; font-weight: bold; color: var(--cor-destaque);"><?php echo date('d/m/Y', strtotime($stats['geral']['data_mais_recente'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="padding: 20px; grid-column: span 2;">
                        <h3>Destaques de Duração ⏱️</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 20px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
                                <thead><tr style="border-bottom: 1px solid var(--cor-destaque);"><th colspan="2" style="padding-bottom: 10px; color: var(--cor-texto-secundario);">Músicas Extremas</th></tr></thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);"><td style="padding: 12px 5px;"><strong>Mais Longa:</strong><br><small style="color: var(--cor-texto-secundario);"><?php echo htmlspecialchars($stats['faixas']['faixa_mais_longa']['album'] ?? 'N/A'); ?></small></td><td style="text-align: right; vertical-align: top;"><?php echo htmlspecialchars($stats['faixas']['faixa_mais_longa']['titulo'] ?? 'N/A'); ?> (<?php echo $stats['faixas']['faixa_mais_longa']['duracao'] ?? '00:00'; ?>)</td></tr>
                                    <tr><td style="padding: 12px 5px;"><strong>Mais Curta:</strong><br><small style="color: var(--cor-texto-secundario);"><?php echo htmlspecialchars($stats['faixas']['faixa_mais_curta']['album'] ?? 'N/A'); ?></small></td><td style="text-align: right; vertical-align: top;"><?php echo htmlspecialchars($stats['faixas']['faixa_mais_curta']['titulo'] ?? 'N/A'); ?> (<?php echo $stats['faixas']['faixa_mais_curta']['duracao'] ?? '00:00'; ?>)</td></tr>
                                </tbody>
                            </table>
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.95em;">
                                <thead><tr style="border-bottom: 1px solid var(--cor-destaque);"><th colspan="2" style="padding-bottom: 10px; color: var(--cor-texto-secundario);">Álbuns Extremos</th></tr></thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);"><td style="padding: 12px 5px;"><strong>Mais Longo:</strong></td><td style="text-align: right;"><?php echo htmlspecialchars($stats['faixas']['album_mais_longo']['titulo'] ?? 'N/A'); ?> (<?php echo $stats['faixas']['album_mais_longo']['duracao'] ?? '00:00'; ?>)</td></tr>
                                    <tr><td style="padding: 12px 5px;"><strong>Mais Curto:</strong></td><td style="text-align: right;"><?php echo htmlspecialchars($stats['faixas']['album_mais_curto']['titulo'] ?? 'N/A'); ?> (<?php echo $stats['faixas']['album_mais_curto']['duracao'] ?? '00:00'; ?>)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div class="card" style="padding: 20px;">
                        <h3>Top Gêneros</h3>
                        <canvas id="generoBarChart"></canvas>
                    </div>
                    <div class="card" style="padding: 20px;">
                        <h3>Top Estilos</h3>
                        <canvas id="estiloBarChart"></canvas>
                    </div>
                    <div class="card" style="padding: 20px;">
                        <h3>Top 5 Gravadoras</h3>
                        <canvas id="gravadoraBarChart"></canvas>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="card" style="padding: 20px;">
                        <h3>Top 10 Artistas (Álbuns)</h3>
                        <canvas id="artistaBarChart"></canvas>
                    </div>
                    <div class="card" style="padding: 20px;">
                        <h3>Top 10 Artistas (Faixas)</h3>
                        <canvas id="artistaFaixasBarChart"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>const STATS_DATA = <?php echo json_encode($stats); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="../js/estatisticas.js"></script>