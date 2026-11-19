<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Test\Unit\Plugin\Quote\Model\Quote\Item;

use Infrangible\CatalogProductOptionPrice\Plugin\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\ToOrderItem as QuoteToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the prices of the options are copied from the quote item to the order item.
 *
 * Magento's models are partial mocks: their data methods work, the methods that need the database are stubbed.
 */
#[AllowMockObjectsWithoutExpectations]
class ToOrderItemTest extends TestCase
{
    /**
     * Tests the copied prices.
     *
     * @return void
     */
    public function testAfterConvert(): void
    {
        $quoteItem = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $orderItem = $this->getMockBuilder(OrderItem::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        foreach (ToOrderItem::COLUMNS as $index => $column) {
            $quoteItem->setData($column, $index + 0.5);
        }

        $result = (new ToOrderItem())->afterConvert(
            $this->createStub(QuoteToOrderItem::class),
            $orderItem,
            $quoteItem
        );

        $this->assertSame($orderItem, $result);

        foreach (ToOrderItem::COLUMNS as $index => $column) {
            $this->assertSame($index + 0.5, $orderItem->getData($column));
        }
    }
}
