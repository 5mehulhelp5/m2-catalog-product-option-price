<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Sales\Api;

use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class OrderRepositoryInterface
{
    /** @var Data */
    protected $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function afterGetList(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->helper->addProductOptionPriceToOrder($order);
        }

        return $orderSearchResult;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function afterGet(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->helper->addProductOptionPriceToOrder($order);

        return $order;
    }
}
