<?php


namespace Medgadgets\Tilda\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection {
    protected function _construct()
    {
        $this->_init('Medgadgets\Tilda\Model\Order', 'Medgadgets\Tilda\Model\ResourceModel\Order');
    }
}