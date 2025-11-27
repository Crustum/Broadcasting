<?php
/**
 * @var \Cake\View\View $this
 * @var \Throwable $error
 */
 die($error->getMessage());
?>
<h1>Internal Server Error</h1>
<p><?= h($error->getMessage()) ?></p>
