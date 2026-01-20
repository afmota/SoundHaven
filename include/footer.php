<?php 
// Arquivo: include/footer.php
// Este arquivo fecha o HTML e inclui os scripts.
?>
    </div> <footer>
        &copy; <?php echo date('Y'); ?> SoundHaven - Acervo Digital | Desenvolvido com PHP, PDO e Amor.
    </footer>

    <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
        <script src="/js/filtro.js"></script>
    <?php endif; ?>
</body>
</html>