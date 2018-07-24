<?php
namespace bossanova808\commerceregisteroncheckout\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

class Install extends Migration
{
    public $driver;

    protected $tableName = '{{%commerceregisteroncheckout}}';

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        $this->createTable(
            $this->tableName,
            [
                'id'                          => $this->primaryKey(),
                'dateCreated'                 => $this->dateTime()->notNull(),
                'dateUpdated'                 => $this->dateTime()->notNull(),
                'uid'                         => $this->uid(),
                'orderNumber'                 => $this->string()->null()->defaultValue(null),
                'EPW'                         => $this->string()->null()->defaultValue(null),
                'lastUsedShippingAddressId'   => $this->integer()->null()->defaultValue(null),
                'lastUsedBillingAddressId'   => $this->integer()->null()->defaultValue(null),
            ]
        );

        return true;
    }

    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->dropTableIfExists('{{%commerceregisteroncheckout}}');

        return true;
    }
}