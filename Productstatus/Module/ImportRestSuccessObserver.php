<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ImportRestSuccessObserver implements ObserverInterface
{
    /**
     * ImportRestSuccessObserver constructor.
     */
    public function __construct(
        \Magento\Indexer\Model\IndexerFactory $indexerFactory
    )
    {
        $this->indexerFactory = $indexerFactory;
    }

    public function execute(Observer $observer)
    {
        // reindex
        $indexerIds = array('cataloginventory_stock', 'catalog_product_price');

        foreach ($indexerIds as $indexerId) {
            $indexer = $this->indexerFactory->create();
            $indexer->load($indexerId);
            if ($indexer->getStatus() != 'valid') {
                $indexer->reindexRow($indexerId);
            }
        }
    }
}