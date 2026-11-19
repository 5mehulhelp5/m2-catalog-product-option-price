<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Plugin\Sales\Api;

use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;

/**
 * Adds the prices of the options to the custom options of the order items that the order repository returns, for
 * example in the REST API.
 */
class OrderRepositoryInterface
{
    /**
     * @param Data $helper
     */
    public function __construct(protected Data $helper)
    {
    }

    /**
     * Adds the prices to the items of the orders.
     *
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $orderSearchResult
     *
     * @return OrderSearchResultInterface
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterGetList(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->helper->addProductOptionPriceToOrder($order);
        }

        return $orderSearchResult;
    }

    /**
     * Adds the prices to the items of the order.
     *
     * @param \Magento\Sales\Api\OrderRepositoryInterface $subject
     * @param OrderInterface $order
     *
     * @return OrderInterface
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterGet(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->helper->addProductOptionPriceToOrder($order);

        return $order;
    }
}
