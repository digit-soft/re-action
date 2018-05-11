<?php
/**
 * @var \Reaction\Web\View $this
 * @var string             $content
 */
\Reaction\Assets\ReactionAsset::register($this);
?>
<?php $this->beginPage() ?><!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $this->title ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>
<?= $content; ?>
<?= $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>