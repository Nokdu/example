<?php


namespace Medgadgets\Tilda\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Order extends AbstractDb {

    protected function _construct()

    {

        $this->_init('tilda_orders', 'tilda_id');

    }

}