<?php 
// Arquivo: footer.php
// Este arquivo fecha o HTML e inclui os scripts.
?>
    </div> <footer>
        &copy; <?php echo date('Y'); ?> Acervo Digital | Desenvolvido com PHP, PDO e Amor.
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        <script src="filtro.js"></script>
    <?php endif; ?>
</body>
</html>