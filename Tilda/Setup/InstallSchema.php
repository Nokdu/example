<?php


namespace Medgadgets\Tilda\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class InstallSchema implements InstallSchemaInterface{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Create table 'greeting_message'
         */
        $table = $setup->getConnection()
            ->newTable($setup->getTable('tilda_orders'))
            ->addColumn(
                'tilda_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Tilda ID'
            )->addColumn(
                'name',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false, 'default' => ''],
                'Name'
            )->addColumn(
                'email',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Email'
            )->addColumn(
                'phone',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Phone'
            )->addColumn(
                'address',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'delivery address'
            )->addColumn(
                'promo',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'promo'
            )->addColumn(
                'paymentsystem',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Paymentsystem'
            )->addColumn(
                'orderid',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Order Id'
            )->addColumn(
                'amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Amount'
            )->addColumn(
                'magento_order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false, 'default' => ''],
                'Order id magento'
            )
            ->setComment("Tilda Webhook form");
        $setup->getConnection()->createTable($table);
    }
}