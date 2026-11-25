</main>
<nav class="bottom-nav">
  <?php foreach ([['home', 'Home', 'home'], ['jobs', 'Jobs', 'jobs'], ['schedule', 'Schedule', 'cal'], ['training', 'Training', 'cap'], ['profile', 'Profile', 'user']] as [$f, $l, $i]): ?>
    <a href="<?= $f ?>.php" class="<?= $active === $f ? 'on' : '' ?>"><?= ic($i) ?><span><?= $l ?></span></a>
  <?php endforeach; ?>
</nav>
</div>
<script src="assets/js/app.js"></script>
</body>

</html>