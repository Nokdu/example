<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CatalogProductSaveAfter implements ObserverInterface
{
    /**
     * @var PurgeCache
     */
    private $purgeCache;

    public function __construct(
        \Medgadgets\Productstatus\Module\PurgeCache $purgeCache
    ) {
        $this->purgeCache = $purgeCache;
    }

    public function execute(Observer $observer)
    {
        $_product = $observer->getProduct();

        $this->purgeCache->cleanProductCache($_product);
    }
}