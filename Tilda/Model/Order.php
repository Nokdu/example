<?php


namespace Medgadgets\Tilda\Model;


class Order extends \Magento\Framework\Model\AbstractModel  {
    protected function _construct()
    {
        $this->_init('Medgadgets\Tilda\Model\ResourceModel\Order');
    }
}