<?php


namespace Medgadgets\Tilda\Controller\Order;


use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Medgadgets\Tilda\Helper\CreateOrder;
use Medgadgets\Tilda\Model\OrderFactory;

use Psr\Log\LoggerInterface;

class Index extends Action implements FrontControllerInterface
{

    private $request;
    private $logger;
    private $resultJsonFactory;
    protected $_order;
    protected $orderComment = 'Заказ с Тильды';
    private $_createOrder;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Http $request,
        CreateOrder $createOrder,
        OrderFactory $order
    )
    {
        $this->logger = $logger;
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_order = $order;
        $this->_createOrder = $createOrder;
        parent::__construct($context);
    }

    public function execute()
    {
        $content = $this->request->getParams();
        $log = new \Monolog\Logger('order', [new \Monolog\Handler\StreamHandler(BP . '/var/log/tilda.log')]);
        $log->info(print_r($content, true));
        if (!$this->isOrderCreate($content)) {
            $orderId = $this->createOrder($content);
            $this->saveOrderInfo($content, $orderId);
        }
        $result = $this->resultJsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        return $result;
    }

    /**
     * @param array $content
     * @param $orderId
     */
    protected function saveOrderInfo(array $content, $orderId): void
    {
        if (isset($content['payment']['orderid'])) {
            $model = $this->_order->create();
            $data = array(
                'name' => $content['Name'] ?? '',
                'email' => $content['Email'] ?? '',
                'phone' => $content['Phone'] ?? '',
                'address' => $content['Адрес_доставки'] ?? '',
                'promo' => $content['Промокод'] ?? $content['Промокод'] ?? '',
                'paymentsystem' => $content['paymentsystem'] ?? '',
                'orderid' => $content['payment']['orderid'] ?? '',
                'amount' => $content['payment']['amount'] ?? '',
                'magento_order_id' => $orderId
            );
            $model->setData($data);
            $model->save();
        }
    }

    protected function createOrder(array $content)
    {
        if (isset($content['formname'])) {
            $productData = [];
            if (count($content['payment']['products']) > 0) {
                foreach ($content['payment']['products'] as $value) {
                    $productData[] = ['name' => $value['name'], 'sku' => $value['sku'], 'quantity' => $value['quantity'], 'price' => $value['price']];
                }
            } else {
                $data = ['error' => 1, 'msg' => CreateOrder::DEFAULT_MESSAGE];
                $this->logger->critical('OneClickOrderTilda -> SKU ' . $content . ' ' . $data['msg']);
            }

            $email = $content['Email'];
            $clientName = $content['Name'];
            $cityId = '';
            $telephone = $content['Phone'];
            $options = $this->request->getParam('option');
            $couponCode = $content['payment']['promocode'] ?? $content['payment']['promocode'] ?? '';
            $_orderComment = $this->orderComment;
            $address = $content['Адрес_доставки'] ?? $content['Адрес_доставки'] ?? '-';

            if (mb_strlen($email) <= 5) {
                $data = ['error' => 1, 'msg' => 'Вы не указали свой Email-адрес.'];
                $this->logger->critical('OneClickOrderTilda -> EMAIL ' . $email . ' ' . $data['msg']);
            } else {
                try {
                    return $this->_createOrder->create(
                        $productData,
                        $email,
                        $clientName,
                        $cityId,
                        $telephone,
                        $couponCode,
                        $options,
                        $address,
                        $_orderComment,
                        true
                    );
                } catch (\Exception $exception) {
                    $this->logger->critical('OneClickOrderTilda -> Exception ' . $exception->getMessage());
                    $data = ['error' => 1, 'msg' => $exception->getMessage()];
                }
            }
        }
    }

    private function isOrderCreate($content): bool
    {
        $orderCreate = false;
        if (isset($content['payment']['orderid'])) {
            $tildaOrder = $this->_order->create();
            $collection = $tildaOrder->getCollection()->addFieldToFilter('orderid', $content['payment']['orderid']);
            $resultData = $collection->load();
            if (count($resultData) > 0) {
                $orderCreate = true;
            }
        }
        return $orderCreate;
    }
}
