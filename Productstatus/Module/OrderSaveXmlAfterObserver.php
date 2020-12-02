<?php

namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderSaveXmlAfterObserver implements ObserverInterface
{
    public function __construct(
        \Magento\Sales\Api\OrderStatusHistoryRepositoryInterface $orderStatusRepository,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->orderStatusRepository = $orderStatusRepository;
        $this->_logger = $logger;
    }

    public function execute(Observer $observer)
    {
        // setOrderComplete
        $order = $observer->getEvent()->getOrder();
        $node = $observer->getEvent()->getDocument();

        if ($order->getState() != Order::STATE_COMPLETE &&
            array_key_exists('Номер отгрузки по 1С', $node->getRekvizit()) &&
            !empty($node->getRekvizit()['Номер отгрузки по 1С'])) {

            $order->setState(Order::STATE_COMPLETE);
            $order->setStatus(Order::STATE_COMPLETE);

            $comment = $order->addStatusHistoryComment('Order marked as complete 1C program.');
            try {
                $this->orderStatusRepository->save($comment);
            } catch (\Exception $originalException) {
                $exception = new \Exception('Could not save Rugento 1C Sync comment', 0, $originalException);
                $this->_logger->critical(
                    $exception->getMessage() . PHP_EOL . $exception->getTraceAsString()
                );
            }

            $order->save();
        }

        return $this;
    }
}