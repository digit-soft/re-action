<?php
/**
 * This view is used by Reaction/Console/controllers/MigrateController.php.
 *
 * The following variables are available in this view:
 */
/* @var $className string the new migration class name without namespace */
/* @var $namespace string the new migration class namespace */

echo "<?php\n";
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

use Reaction\Db\ConnectionInterface;
use Reaction\Db\Migration;
use Reaction\Console\Exception;
use function Reaction\Promise\reject;

/**
 * Class <?= $className . "\n" ?>
 */
class <?= $className ?> extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(ConnectionInterface $connection)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(ConnectionInterface $connection)
    {
        echo "<?= $className ?> cannot be reverted.\n";

        return reject(new Exception("Can not revert migration"));
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "<?= $className ?> cannot be reverted.\n";

        return reject(new Exception("Can not revert migration"));
    }
    */
}
