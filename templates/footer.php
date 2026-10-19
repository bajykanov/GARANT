</main>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="brand-text footer-brand">
                    <strong>ГАРАНТ</strong>
                    <small>продажа и обмен под защитой платформы</small>
                </div>
                <p class="mt-3 mb-0">Учебный маркетплейс. Оплата замораживается у гаранта и переходит продавцу только после подтверждения получения. Обмен закрывается, когда подтвердили обе стороны.</p>
            </div>
            <div class="col-md-3">
                <h2>Разделы</h2>
                <a href="<?= e(url('catalog.php')) ?>">Каталог</a>
                <a href="<?= e(url('index.php')) ?>#escrow">Эскроу</a>
                <a href="<?= e(url('register.php')) ?>">Регистрация</a>
            </div>
            <div class="col-md-4">
                <h2>Срок гаранта</h2>
                <p>После отправки у покупателя есть <?= (int) ESCROW_DAYS ?> дн. на подтверждение. Дальше — спор или автоматический возврат, как задано в config.php.</p>
            </div>
        </div>
        <div class="footer-bottom">Деньги в этой версии учебные: платёж только фиксируется в журнале эскроу.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
clear_form_state();
