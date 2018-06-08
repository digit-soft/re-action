<?php
/* @var $exception \Reaction\Exceptions\Exception */
/* @var $handler \Reaction\Web\ErrorHandler */
?>
<div class="previous">
    <span class="arrow">&crarr;</span>
    <h2>
        <span>Caused by:</span>
        <?php $name = $handler->getExceptionName($exception) ?>
        <?php if ($name !== null): ?>
            <span><?= $handler->htmlEncode($name) ?></span> &ndash;
            <?= $handler->addTypeLinks(get_class($exception)) ?>
        <?php else: ?>
            <span><?= $handler->htmlEncode(get_class($exception)) ?></span>
        <?php endif; ?>
    </h2>
    <h3><?= nl2br($handler->htmlEncode($exception->getMessage())) ?></h3>
    <p>in <span class="file"><?= $exception->getFile() ?></span> at line <span class="line"><?= $exception->getLine() ?></span></p>
    <?= $handler->renderPreviousExceptions($exception) ?>
</div>
