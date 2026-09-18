  </div>
</main>
<footer class="conteneur flex flex-wrap justify-center gap-x-6 gap-y-2 pb-8 text-sm text-text-3">
  <a class="hover:text-primary-ink" href="/conditions.php">Conditions générales</a>
  <a class="hover:text-primary-ink" href="/confidentialite.php">Confidentialité</a>
  <a class="hover:text-primary-ink" href="mailto:contact@monrevenu.xyz">contact@monrevenu.xyz</a>
</footer>
<div id="toasts" class="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(actif('/assets/js/app-shell.js')) ?>"></script>
<?php foreach ($scripts_page ?? [] as $script): ?>
<script src="<?= e(actif($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
