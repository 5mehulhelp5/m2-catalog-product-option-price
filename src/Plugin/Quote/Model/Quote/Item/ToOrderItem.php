<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Plugin\Quote\Model\Quote\Item;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Copies the prices of the options from the quote item to the order item.
 */
class ToOrderItem
{
    /** The columns of the prices in the tables quote_item and sales_order_item. */
    public const COLUMNS = [
        'options_price',
        'base_options_price',
        'row_options_price',
        'base_row_options_price',
        'options_price_incl_tax',
        'base_options_price_incl_tax',
        'row_options_price_incl_tax',
        'base_row_options_price_incl_tax'
    ];

    /**
     * Copies the prices to the converted order item.
     *
     * @param \Magento\Quote\Model\Quote\Item\ToOrderItem $subject
     * @param OrderItemInterface $result
     * @param AbstractItem $item
     *
     * @return OrderItemInterface
     * @noinspection PhpUnusedParameterInspection
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Item\ToOrderItem $subject,
        OrderItemInterface $result,
        AbstractItem $item
    ): OrderItemInterface {
        if ($result instanceof DataObject) {
            foreach (self::COLUMNS as $column) {
                $result->setData($column, $item->getData($column));
            }
        }

        return $result;
    }
}
