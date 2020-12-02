<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class EditBeforeSaveObserver implements ObserverInterface
{
    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $validProduct = $observer->getEvent()->getValideProduct();
        // Delete sync Product Name from 1C -> Site
        unset($validProduct['name']);

        return $this;
    }
}