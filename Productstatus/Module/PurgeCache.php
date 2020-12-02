<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Medgadgets\Productstatus\Module;

use Magento\Framework\Cache\InvalidateLogger;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class PurgeCache
{
    const HEADER_X_MAGENTO_TAGS_PATTERN = 'X-Purge-Pattern';

    /**
     * @var \Magento\PageCache\Model\Cache\Server
     */
    protected $cacheServer;

    /**
     * @var \Magento\CacheInvalidate\Model\SocketFactory
     */
    protected $socketAdapterFactory;

    /**
     * @var InvalidateLogger
     */
    private $logger;
    /**
     * @var Configurable
     */
    private $configurable;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;

    /**
     * Constructor
     *
     * @param \Magento\PageCache\Model\Cache\Server        $cacheServer
     * @param \Magento\CacheInvalidate\Model\SocketFactory $socketAdapterFactory
     * @param InvalidateLogger                             $logger
     * @param Configurable                                 $configurable
     * @param \Magento\Catalog\Model\ProductRepository     $productRepository
     */
    public function __construct(
        \Magento\PageCache\Model\Cache\Server $cacheServer,
        \Magento\CacheInvalidate\Model\SocketFactory $socketAdapterFactory,
        InvalidateLogger $logger,
        Configurable $configurable,
        \Magento\Catalog\Model\ProductRepository $productRepository
    ) {
        $this->cacheServer = $cacheServer;
        $this->socketAdapterFactory = $socketAdapterFactory;
        $this->logger = $logger;
        $this->configurable = $configurable;
        $this->productRepository = $productRepository;
    }

    /**
     * Send curl purge request
     * to invalidate cache by tags pattern
     *
     * @param string $tagsPattern
     * @return bool Return true if successful; otherwise return false
     */
    public function sendPurgeRequest($tagsPattern)
    {
        $socketAdapter = $this->socketAdapterFactory->create();
        $servers = $this->cacheServer->getUris();
        $headers = [self::HEADER_X_MAGENTO_TAGS_PATTERN => $tagsPattern];
        $socketAdapter->setOptions(['timeout' => 10]);
        foreach ($servers as $server) {
            $headers['Host'] = $server->getHost();
            try {
                $socketAdapter->connect($server->getHost(), $server->getPort());
                $socketAdapter->write(
                    'PURGE',
                    $server,
                    '1.1',
                    $headers
                );
                $socketAdapter->read();
                $socketAdapter->close();
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage(), compact('server', 'tagsPattern'));
                return false;
            }
        }

        $this->logger->execute(compact('servers', 'tagsPattern'));
        return true;
    }

    public function cleanProductCache(\Magento\Catalog\Model\Product $_product)
    {
        /* @var $_product \Magento\Catalog\Model\Product */
        if ($_product->getTypeId() != Configurable::TYPE_CODE) {
            foreach ($this->configurable->getParentIdsByChild($_product->getId()) as $productId) {
                $this->sendPurgeRequest($this->productRepository->getById($productId)->getUrlKey());
            }
        }

        $this->sendPurgeRequest($_product->getUrlKey());
    }
}