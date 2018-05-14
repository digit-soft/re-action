<?php
/**
 * @var \Reaction\Web\View             $this
 * @var \Reaction\Exceptions\Exception $exception
 * @var string                         $exceptionName
 */
$this->title = 'Error :: ' . $exceptionName;
$traceArray = $exception->getTrace();
$inFile = $exception->getFile();
\Reaction\Helpers\ArrayHelper::processTrace($traceArray, \Reaction\Helpers\ArrayHelper::IGN_ALL);
?>
<div class="container py-3">
    <h1><?= $exceptionName ?></h1>
    <h2><?= $exception->getMessage() ?></h2>
    <?php if (!empty($inFile)): ?>
        <h4 class="font-italic text-danger"><?= $inFile ?> #<?= $exception->getLine() ?></h4>
    <?php endif; ?>

    <h4 class="text-muted">Backtrace (<?= count($traceArray) ?>)</h4>
    <ul class="list-group">
        <?php foreach ($traceArray as $trace): ?>
            <?php
                $args = [];
                for ($i = 0; $i < count($trace['args']); $i++) {
                    $argType = $trace['argTypes'][$i];
                    $arg = $trace['args'][$i];
                    $args[] = '<i class="text-muted">' . $argType . '</i><strong>' . ($arg !== "" ? " " . $arg : $arg) . '</strong>';
                }
            ?>
            <li class="list-group-item py-1">
                <?php if (!empty($trace['class'])): ?>
                    <b><?= $trace['class'] . '::' . $trace['function'] ?></b>
                <?php else: ?>
                    <b><?= $trace['function'] ?></b>
                <?php endif; ?>
                (<small class="text-info"><?= implode(', ', $args) ?></small>)
                <br>
                <?php if (!empty($trace['file'])): ?>
                    <small class="text-muted">
                        <i><?= $trace['file'] ?> #<?= $trace['line'] ?></i>
                    </small>
                <?php else: ?>
                    <small>-</small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
