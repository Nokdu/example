<?php
namespace Medgadgets\Productstatus\Logger;

use Monolog\Logger;

class ProductHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/1c_sync.log';
}