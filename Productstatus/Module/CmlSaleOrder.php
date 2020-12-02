<?php


namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductFactory;


class CmlSaleOrder implements ObserverInterface {

    /**
     * @var \Medgadgets\Productstatus\Logger\OrderLogger
     */
    private $_logger;
    /** @var ProductFactory */
    private $_productLoader;
    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $_product;


    public function __construct(
        ProductFactory $_productLoader,
        \Medgadgets\Productstatus\Logger\OrderLogger $logger)
    {
        $this->_productLoader              = $_productLoader;
        $this->_logger                     = $logger;

    }

    public function execute(Observer $observer){
        $orderTr = $observer->getEvent()->getOrder();
        $this->_logger->info('Observer OrderId: ' . $orderTr->getId());
        foreach( $orderTr->getAllVisibleItems() as $valueItem ) {
            $typeProductOrderCml = $valueItem->getProductType();
            $typeProductReal = $this->_productLoader->create()->load($valueItem->getProductId())->getTypeId();
            if ($typeProductOrderCml != $typeProductReal){
                $message = 'Ошибка выгрузки, нужно удалить из очереди заказ № ' . $orderTr->getId();
                $message .= "<img src=\"https://i.imgur.com/g4CWC6O.png\" alt=\"Выгрузка\">";
                $this->sendEmail($message);
            }

        }
        $this->_logger->info('Observer END');
    }
    public function sendEmail($message) {
        $mail = new \Zend_Mail('UTF-8');
        $mail->setType(\Zend_Mime::MULTIPART_RELATED);
        $mail->addTo('2060las@gmail.com');
        $mail->addTo('info@medgadgets.ru');
        $mail->setBodyHtml($message);
        $mail->setSubject("Ошибка выгрузки в 1С");
        $mail->setFrom('robot@medgadgets.ru', "Robot Medgadgets");
        $mail->send();

    }
}
