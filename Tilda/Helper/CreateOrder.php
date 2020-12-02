<?php


namespace Medgadgets\Tilda\Helper;


use Exception;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\CouponFactory;
use Magento\Store\Model\StoreManagerInterface;
use Medgadgets\Productstatus\Module\PurgeCache;
use Medgadgets\Reactjs\Helper\Data;
use Medgadgets\Utmtracking\Model\CampaignFactory;
use Psr\Log\LoggerInterface;

class CreateOrder
{
    const DEFAULT_MESSAGE = 'Возникла проблема при создании заказа!';
    const NOT_AVAILABLE_MESSAGE = 'К сожалению, данный товар закончился. Вы можете оставить заявку, и мы вам обязательно сообщим, когда он вновь появится в продаже!';
    const YANDEX_MARKET = 'yandexmarket';

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductRepository
     */
    private $productRepository;
    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepositoryInterface;
    /**
     * @var CartManagementInterface
     */
    private $cartManagementInterface;
    /**
     * @var Data
     */
    private $reactJsHelper;
    /**
     * @var CampaignFactory
     */
    private $campaignFactory;
    /**
     * @var CouponFactory
     */
    private $couponFactory;
    /**
     * @var PurgeCache
     */
    private $purgeCache;
    /**
     * @var ProductFactory
     */
    private $productFactory;
    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * OneClickOrder constructor.
     *
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ProductRepository $productRepository
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param CartManagementInterface $cartManagementInterface
     * @param Rate $shippingRate
     * @param ProductFactory $productFactory
     * @param Data $reactJsHelper
     * @param CampaignFactory $campaignFactory
     * @param CouponFactory $couponFactory
     * @param PurgeCache $purgeCache
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ProductRepository $productRepository,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $cartRepositoryInterface,
        CartManagementInterface $cartManagementInterface,
        Data $reactJsHelper,
        CampaignFactory $campaignFactory,
        CouponFactory $couponFactory,
        ProductFactory $productFactory,
        PurgeCache $purgeCache,
        StockRegistryInterface $stockRegistry
    )
    {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->reactJsHelper = $reactJsHelper;
        $this->campaignFactory = $campaignFactory;
        $this->couponFactory = $couponFactory;
        $this->purgeCache = $purgeCache;
        $this->productFactory = $productFactory;
        $this->stockRegistry = $stockRegistry;
    }

    /**
     * @param $sku
     * @param $email
     * @param $clientName
     * @param $utmParams
     * @param $cityId
     * @param $telephone
     * @param $couponCode
     * @param $options
     *
     *
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function create($products, $email, $clientName, $cityId, $telephone, $couponCode, $options, $addressUser, $orderComment, $tilda = false)
    {
        if (!$clientName) {
            $clientName = 'Быстрый заказ';
        }

        if (!$cityId) {
            $city = '-';
            $countryId = 'RU';
            $region = '-';
        } else {
            // FIXME lookup in database
            $city = 'Москва';
            $countryId = 'RU';
            $region = 'Московская область';
        }

        if (!$telephone) {
            $telephone = '-';
        }


        $store = $this->storeManager->getStore();
        $websiteId = $store->getWebsiteId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($email);
        if (!$customer->getEntityId()) {
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($clientName)
                ->setLastname($clientName)
                ->setEmail($email)
                ->setPassword($this->reactJsHelper->randomPassword());
            $customer->save();
        }

        $cartId = $this->cartManagementInterface->createEmptyCart();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepositoryInterface->get($cartId);

        $quote->setStore($store);

        $customer = $this->customerRepository->getById($customer->getEntityId());
        $quote->setCurrency();
        $quote->assignCustomer($customer);

        try {
            foreach ($products as $product) {
                $_product = $this->productRepository->get($product['sku']);
                $_product->setPrice($product['price']);
                $_product->setBasePrice($product['price']);
                $params['qty'] = $product['quantity'];
                $params['product'] = $_product->getId();
                $objParam = new DataObject();
                $objParam->setData($params);
                $quote->addProduct($_product, $objParam);
            }
        } catch (Exception $exception) {
            $this->logger->critical('OneClickOrder -> Exception AddProduct ' . $exception->getMessage());
        }

        $address = [
            'firstname' => $clientName,
            'lastname' => $clientName,
            'street' => $addressUser,
            'city' => $city,
            'country_id' => $countryId,
            'region' => $region,
            'postcode' => '',
            'telephone' => $telephone,
            'fax' => '-',
            'save_in_address_book' => 1,
        ];

        //Set Address to quote
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->setShippingMethod('medclickorder_medclickorder');

        $quote->setPaymentMethod('cashondelivery');
        $quote->setInventoryProcessed(false);

        if (mb_strlen($couponCode) > 2) {
            $coupon = $this->couponFactory->create();
            $coupon->load($couponCode, 'code');

            if ($coupon->getId()) {
                $quote->setCouponCode($couponCode);
            }
        }

        $quote->save();

        $quote->getPayment()->importData(['method' => 'cashondelivery']);

        $quote->collectTotals();
        $quote->save();

        $quote = $this->cartRepositoryInterface->get($quote->getId());
        $quote->setCustomerNoteNotify(false);
        $orderId = $this->cartManagementInterface->placeOrder($quote->getId());

        return $orderId;
    }

}
