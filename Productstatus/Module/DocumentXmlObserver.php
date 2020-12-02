<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class DocumentXmlObserver implements ObserverInterface {

    /** @var ProductRepository */
    private $productRepository;
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;
    /**
     * @var Configurable
     */
    private $configurable;

    public function __construct(
        \Medgadgets\Productstatus\Logger\OrderLogger $logger,
        \Medgadgets\Shiptor\Helper\Data $shiptorHelper,
        \Medgadgets\Shiptor\Helper\Deliverydate $deliveryHelper,
        CategoryRepositoryInterface $categoryRepositoryInterface,
        ProductRepository $productRepository,
        Configurable $configurable
    ) {
        $this->_logger              = $logger;
        $this->_shiptorHelper       = $shiptorHelper;
        $this->_deliveryHelper      = $deliveryHelper;
        $this->productRepository    = $productRepository;
        $this->categoryRepository   = $categoryRepositoryInterface;
        $this->configurable         = $configurable;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $xml = $observer->getEvent()->getXml();

	$this->_logger->info('DocumentXmlObserver');

        if ($this->_deliveryHelper->isRegister($order->getId())) {
            $node = $xml->{'Контейнер'}->{'Документ'}->{'ЗначенияРеквизитов'};
            $addressField = $node->addChild('ЗначениеРеквизита');
            $addressField->addChild('Наименование', 'ВремяДоставки');
            $addressField->addChild('Значение', $this->_deliveryHelper->isRegister($order->getId()));

            if (mb_strlen($order->getDeliveryComment()) > 2) {
                $addressField = $node->addChild('ЗначениеРеквизита');
                $addressField->addChild('Наименование', 'КомментарийДоставки');
                $addressField->addChild('Значение', $order->getDeliveryComment());
            }
            $addressFieldTo = $node->addChild('ЗначениеРеквизита');
            $addressFieldTo->addChild('Наименование', 'ПромежутокДоставкиОт');
            $addressFieldTo->addChild('Значение', $order->getDeliveryFrom());

            $addressFieldFrom = $node->addChild('ЗначениеРеквизита');
            $addressFieldFrom->addChild('Наименование', 'ПромежутокДоставкиДо');
            $addressFieldFrom->addChild('Значение', $order->getDeliveryTo());
        }

        if ($this->_shiptorHelper->isRegister($order->getId())) {
            $node = $xml->{'Контейнер'}->{'Документ'}->{'ЗначенияРеквизитов'};
            $addressField = $node->addChild('ЗначениеРеквизита');
            $addressField->addChild('Наименование', 'НомерШиптора'); //добавили в выгрузку
            $addressField->addChild('Значение', $this->_shiptorHelper->isRegister($order->getIncrementId()));
        }

        $xmlProducts = $xml->xpath('//Товары/Товар');
        $this->_logger->info('$xmlProducts: ' . print_r($xmlProducts, true));
        foreach ($xmlProducts as $xmlProduct){
            $info = $xmlProduct->xpath('ЗначенияРеквизитов');
            if ($xmlProduct->xpath('Артикул')){
                $_product = $this->productRepository->get($xmlProduct->xpath('Артикул')[0]->__toString());
                $addressField = $info[0]->addChild('ЗначениеРеквизита');
                $addressField->addChild('Наименование', 'Цена');
                $addressField->addChild('Значение', $_product->getPrice());
                if ($_product->getSpecialPrice()){
                    $addressField = $info[0]->addChild('ЗначениеРеквизита');
                    $addressField->addChild('Наименование', 'АкционнаяЦена');
                    $addressField->addChild('Значение', $_product->getSpecialPrice());
                }
                if ($_product->getTypeId() === 'simple') {
                    $parentProductIds = $this->configurable->getParentIdsByChild($_product->getId());
                    if (isset($parentProductIds) && !empty($parentProductIds[0])) {
                        $_product = $this->productRepository->getById($parentProductIds[0]);
                    }
                }
                if ($_product->getBreads()) {
                    $gaCategory = [];
                    foreach (explode('/', $_product->getBreads()) as $categoryIdItem) {
                        if ($categoryIdItem == 1 || $categoryIdItem == 2) {
                            continue;
                        }
                        try {
                            $category     = $this->categoryRepository->get($categoryIdItem, 1);
                            $gaCategory[] = $category->getName();
                        } catch (NoSuchEntityException $e) {
                            continue;
                        }
                    }
                    $addressField = $info[0]->addChild('ЗначениеРеквизита');
                    $addressField->addChild('Наименование', 'КатегорияТовара');
                    $addressField->addChild('Значение', $this->categoryUnit($gaCategory));
                }
                $manufacturer = $_product->getAttributeText('manufacturer');
                if ($manufacturer){
                    $addressField = $info[0]->addChild('ЗначениеРеквизита');
                    $addressField->addChild('Наименование', 'Производитель');
                    $addressField->addChild('Значение', $manufacturer);
                }
            }
            $this->_logger->info('$info: ' . print_r($info, true));
        }



        $this->_logger->info('OrderId: ' . $order->getId() . ': ' . print_r($xml, true));
        $xml->asXML('/www/site/medgadgets_2/pub/shop/' . 'products_data_ohm.xml');
        return $this;
    }

    private function categoryUnit($category){
        $categoryName = 'Магазин';
        if (count($category) > 1){
            for ($categoryCountId = 1; $categoryCountId < count($category); $categoryCountId++){
                if ($categoryCountId != count($category)){
                    $categoryName .= '/';
                }
                $categoryName .= $category[$categoryCountId];
            }
        }
        return $categoryName;
    }
}
