<?php
/**
 * @var \Reaction\Web\View             $this
 * @var \Reaction\Exceptions\Exception $exception
 * @var string                         $exceptionName
 */
$this->title = 'Error :: ' . $exceptionName;
$traceArray = $exception->getTrace();
$traceArray = array_slice($traceArray, 0, 30);
$inFile = $exception->getFile();
?>

<h1><?= $exceptionName ?></h1>
<h2><?= $exception->getMessage() ?></h2>
<?php if (!empty($inFile)): ?>
    <h3><?= $inFile ?> #<?= $exception->getLine() ?></h3>
<?php endif; ?>

<h4>Backtrace</h4>
<ul class="list-unstyled">
<?php foreach ($traceArray as $trace): ?>
    <li>
        <?php if (!empty($trace['class'])): ?>
            <b><?= $trace['class'] . '::' . $trace['function'] ?></b>
        <?php else: ?>
            <b><?= $trace['function'] ?></b>
        <?php endif; ?>
        <br>
        <?php if (!empty($trace['file'])): ?>
            <small>
                <i><?= $trace['file'] ?> #<?= $trace['line'] ?></i>
            </small>
        <?php else: ?>
            <small>-</small>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>