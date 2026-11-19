<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Test\Unit\Observer;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\CatalogProductOptionPrice\Model\Order\Item\Option as ItemOption;
use Infrangible\CatalogProductOptionPrice\Model\Order\Item\OptionFactory;
use Infrangible\CatalogProductOptionPrice\Observer\SalesOrderPlaceAfter;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Type\DefaultType;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the prices of the options that are stored in the product options of the order items.
 *
 * Magento's models are partial mocks: their data methods work, the methods that need the database are stubbed.
 */
#[AllowMockObjectsWithoutExpectations]
class SalesOrderPlaceAfterTest extends TestCase
{
    /**
     * Places an order with an item and returns the options of the item afterward.
     *
     * The option with ID 1 costs 10 % of the final price of 100 plus 19 % tax.
     *
     * @param array<string, mixed> $itemData
     *
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function placeOrder(array $itemData): array
    {
        $group = $this->createStub(DefaultType::class);
        $group->method('getOptionPrice')->willReturnCallback(
            fn(string $value, float $basePrice): float => $value === 'yes' ? $basePrice * 0.1 : 0.0
        );

        $option = $this->getMockBuilder(Option::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getType', 'groupFactory'])
            ->getMock();
        $option->method('getId')->willReturn('1');
        $option->method('getType')->willReturn('field');
        $option->method('groupFactory')->willReturn($group);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProductOptionsCollection', 'getFinalPrice'])
            ->getMock();
        $product->method('getProductOptionsCollection')->willReturn([$option]);
        $product->method('getFinalPrice')->willReturn(100.0);

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProduct'])
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->setData($itemData);
        $item->setProductOptions(
            [
                'options' => [
                    ['option_id' => '1', 'option_value' => 'yes'],
                    ['option_id' => '2', 'option_value' => 'yes']
                ]
            ]
        );

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItems'])
            ->getMock();
        $order->method('getItems')->willReturn([$item]);

        $catalogHelper = $this->createStub(CatalogHelper::class);
        $catalogHelper->method('getTaxPrice')->willReturnCallback(
            fn(Product $product, float $price): float => $price * 1.19
        );

        $optionFactory = $this->createStub(OptionFactory::class);
        $optionFactory->method('create')->willReturnCallback(
            fn(array $data): ItemOption => new ItemOption($data[ 'data' ])
        );

        $priceCurrency = $this->createStub(PriceCurrency::class);
        $priceCurrency->method('roundPrice')->willReturnCallback(fn(float $price): float => round($price, 2));

        $eventManager = $this->createStub(ManagerInterface::class);

        $observer = new SalesOrderPlaceAfter(
            new Variables(),
            $catalogHelper,
            $eventManager,
            $optionFactory,
            $priceCurrency,
            new Data(
                $catalogHelper,
                new Variables(),
                $eventManager,
                new Arrays(),
                $this->createStub(CustomOptionExtensionFactory::class)
            )
        );

        $observer->execute(new Observer(['order' => $order]));

        /** @var array<int, array<string, mixed>> $options */
        $options = $item->getProductOptions()[ 'options' ];

        return $options;
    }

    /**
     * Tests the price of an option without discount; options that the product does not have get no price.
     *
     * @return void
     * @throws LocalizedException
     */
    public function testPriceWithoutDiscount(): void
    {
        $options = $this->placeOrder(['row_total_incl_tax' => 130.9]);

        $this->assertEqualsWithDelta(11.9, $options[ 0 ][ 'price' ], 0.0001);
        $this->assertArrayNotHasKey('discount', $options[ 0 ]);
        $this->assertArrayNotHasKey('price', $options[ 1 ]);
    }

    /**
     * Tests the share of the discount of the item: 10 % discount of the row total.
     *
     * @return void
     * @throws LocalizedException
     */
    public function testPriceWithDiscount(): void
    {
        $options = $this->placeOrder(['discount_amount' => 13.09, 'row_total_incl_tax' => 130.9]);

        $this->assertEqualsWithDelta(11.9, $options[ 0 ][ 'original_price' ], 0.0001);
        $this->assertSame(1.19, $options[ 0 ][ 'discount' ]);
        $this->assertEqualsWithDelta(10.71, $options[ 0 ][ 'price' ], 0.0001);
    }

    /**
     * Tests a discount of an item without row total, which divided by zero before.
     *
     * @return void
     * @throws LocalizedException
     */
    public function testDiscountWithoutRowTotal(): void
    {
        $options = $this->placeOrder(['discount_amount' => 5.0, 'row_total_incl_tax' => 0.0]);

        $this->assertSame(0.0, $options[ 0 ][ 'discount' ]);
        $this->assertEqualsWithDelta(11.9, $options[ 0 ][ 'price' ], 0.0001);
    }
}
