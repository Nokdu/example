<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class StockAfterSaveObserver implements ObserverInterface
{
    /**
     * StockAfterSaveObserver constructor.
     */
    public function __construct(
        \Medgadgets\Productstatus\Logger\ProductLogger $logger,
        \Magento\Catalog\Model\ProductFactory $_productLoader,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Medgadgets\Productstatus\Module\PurgeCache $purgeCache,
        \Magento\Catalog\Model\ProductRepository $productRepository
    )
    {
        $this->_logger = $logger;
        $this->_productLoader = $_productLoader;
        $this->configurable = $configurable;
        $this->stockRegistry = $stockRegistry;
        $this->_purgeCache = $purgeCache;
        $this->productRepository = $productRepository;
    }

    public function execute(Observer $observer)
    {
        // changeProductStatus
        $this->_logger->info($observer->getEntity()->getEntityId() . ': Start');

        $product = $this->_productLoader->create()->load($observer->getEntity()->getEntityId());

        $this->_logger->info($observer->getEntity()->getEntityId() .
            ': Rests: ' . print_r($observer->getItem()->getData('rests'), true));

        $warehouse = 'РОЗНИЧНЫЙ';

        if (isset($observer->getItem()->getData('rests')['a2708881-6520-11ea-80c4-0050569a030b']) and
            $observer->getItem()->getData('rests')['a2708881-6520-11ea-80c4-0050569a030b'] > 0) {
            $warehouse = 'ВИРТУАЛЬНЫЙ';
        }

        if (isset($observer->getItem()->getData('rests')['af8e867e-4819-11ea-80c3-0050569a030b']) and
            $observer->getItem()->getData('rests')['af8e867e-4819-11ea-80c3-0050569a030b'] > 0) {
            $warehouse = 'РОЗНИЧНЫЙ';
        }


        $parentIds = $this->configurable->getParentIdsByChild($product->getId());
        $this->_logger->info($observer->getEntity()->getEntityId() .
            ': ParentsIds: ' . print_r($parentIds, true));
        $this->_logger->info($observer->getEntity()->getEntityId() .
            ': Nalichie Before: ' . $product->getNalichie());

        $productStock = $this->stockRegistry->getStockItem($product->getId());
        $productQty = $productStock->getQty();
        $this->_logger->info($observer->getEntity()->getEntityId() . ': Qty: ' . $productQty);

        if ($product->getTypeId() == 'simple') {
            if ($productQty <= 0) {
                if ($productStock->getIsInStock()) {

                    $this->_logger->info('STOCK! IS NULL');
                    $productStock->setIsInStock(0);
                }

                if ($product->getNalichie() == 1579) {
                    $product->setData('nalichie', 1578); // Предзаказ
                    $product->getResource()->saveAttribute($product, 'nalichie');
                }

                if ($product->getData('status2') == 18) {
                    $product->setData('status2', 2063); // Товар уедет к вам через 0-3 рабочих дня.
                    $product->getResource()->saveAttribute($product, 'status2');
                }

                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Stock: ' . $productStock->getIsInStock());
                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Nalichie: ' . $product->getNalichie());
                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Status2: ' . $product->getAttributeText('status2'));
            }

            if ($productQty > 0) {
                if (!$productStock->getIsInStock()) {
                    $productStock->setIsInStock(1);
                }

                if ($product->getNalichie() == 1578 or $product->getNalichie() == 1580) {
                    $product->setData('nalichie', 1579); // Есть в наличии
                    $product->getResource()->saveAttribute($product, 'nalichie');
                }

                switch ($warehouse) {
                    case 'РОЗНИЧНЫЙ':
                        $product->setData('status2', 18); // Доступность: Есть в наличии
                        $product->getResource()->saveAttribute($product, 'status2');
                        break;
                    case 'ВИРТУАЛЬНЫЙ':
                        $product->setData('status2', 2063); // Товар уедет к вам через 0-3 рабочих дня.
                        $product->getResource()->saveAttribute($product, 'status2');

                        $this->_logger->info($observer->getEntity()->getEntityId() . ': VIRTUAL sklad: True');
                        break;
                    case 'БПДР':
                        $product->setData('status2', 32757); // ПБДР
                        $product->getResource()->saveAttribute($product, 'status2');

                        $this->_logger->info($observer->getEntity()->getEntityId() . ': BPDR sklad: True');
                        break;
                    default:
                        $product->setData('status2', 18); // Доступность: Есть в наличии
                        $product->getResource()->saveAttribute($product, 'status2');
                        break;
                }

                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Stock: ' . $productStock->getIsInStock());
                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Nalichie: ' . $product->getNalichie());
                $this->_logger->info($observer->getEntity()->getEntityId() .
                    ': Status2: ' . $product->getAttributeText('status2'));
            }
        }

        if (isset($parentIds[0])) {
            $parentProduct = $this->_productLoader->create()->load($parentIds[0]);

            $isActive = 0;
            $needAction = false;

            foreach ($this->configurable->getUsedProducts($parentProduct, null) as $as_product) {
                $asProductStock = $this->stockRegistry->getStockItem($as_product->getId());
                if ($asProductStock->getQty() > 0 and $asProductStock->getIsInStock()) {
                    if ($as_product->getId() != $product->getId()) {
                        $isActive = 1;
                        $needAction = true;
                    }
                }

                if ($asProductStock->getQty() == 0 and !$asProductStock->getIsInStock()) {
//                    $isActive = 0;
                    $needAction = true;
                }

                if ($productQty > 0 and $productStock->getIsInStock()) {
                    $isActive = 1;
                    $needAction = true;
                }
            }

            $parentProductStock = $this->stockRegistry->getStockItem($parentProduct->getId());
            $parentProductStock->setIsInStock(1);
            $parentProductStock->save();

            if ($needAction and $isActive == 1) {
                if (!$parentProductStock->getIsInStock()) {
                    $parentProductStock->setIsInStock(1);
                }

                if ($parentProduct->getNalichie() == 1578 or $parentProduct->getNalichie() == 1580 or
                    !$parentProduct->getNalichie()) {
                    $parentProduct->setData('nalichie', 1579); // Есть в наличии
                    $parentProduct->getResource()->saveAttribute($parentProduct, 'nalichie');
                }

                $parentProduct->setData('status2', 18); // Доступность: Есть в наличии
                $parentProduct->getResource()->saveAttribute($parentProduct, 'status2');

                $needAction = false;
            }

            if ($needAction and $isActive == 0) {
                if ($parentProductStock->getIsInStock()) {
                    // $parentProductStock->setIsInStock(0);
                }

                if ($parentProduct->getNalichie() == 1579 or !$parentProduct->getNalichie()) {
                    $parentProduct->setData('nalichie', 1578); // Предзаказ
                    $parentProduct->getResource()->saveAttribute($parentProduct, 'nalichie');
                }

                if ($parentProduct->getData('status2') == 18) {
                    $parentProduct->setData('status2', 2063); // Товар уедет к вам через 0-3 рабочих дня.
                    $parentProduct->getResource()->saveAttribute($parentProduct, 'status2');
                }
            }

            foreach ($parentIds as $productId) {
                $this->_purgeCache->sendPurgeRequest($this->productRepository->getById($productId)->getUrlKey());
            }
        }

        $this->_logger->info($observer->getEntity()->getEntityId() .
            ': Nalichie After: ' . $product->getNalichie());
        $this->_logger->info($observer->getEntity()->getEntityId() . ': End');
        $this->_logger->info('--------------------------------');

        $this->_purgeCache->sendPurgeRequest($product->getUrlKey());
    }
}
