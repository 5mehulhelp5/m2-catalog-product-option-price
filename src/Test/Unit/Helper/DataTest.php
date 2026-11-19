<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Test\Unit\Helper;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\CatalogProductOptionPrice\Model\Order\Item\Option as ItemOption;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Type\DefaultType;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the prices of the options of quote items.
 *
 * Magento's models are partial mocks: their data methods work, the methods that need the database are stubbed.
 */
#[AllowMockObjectsWithoutExpectations]
class DataTest extends TestCase
{
    /**
     * Tests that options with a percent price are calculated from the final price without options.
     *
     * The final price of the product includes the options (120), the final price without options is 100; the option
     * costs 10 % of it plus 19 % tax.
     *
     * @return void
     */
    public function testItemOptionPrices(): void
    {
        $group = $this->createStub(DefaultType::class);
        $group->method('getOptionPrice')->willReturnCallback(
            fn(string $value, float $basePrice): float => $value === 'yes' ? $basePrice * 0.1 : 0.0
        );

        $option = $this->getMockBuilder(Option::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getType', 'groupFactory', 'getProduct'])
            ->getMock();
        $option->method('getId')->willReturn('1');
        $option->method('getType')->willReturn('drop_down');
        $option->method('groupFactory')->willReturn($group);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOptionById', 'getFinalPrice'])
            ->getMock();
        $product->method('getOptionById')->willReturnCallback(
            fn(int $optionId): ?Option => $optionId === 1 ? $option : null
        );
        $product->method('getFinalPrice')->willReturn(120.0);
        $product->setData('final_price_without_options', 100.0);
        $option->method('getProduct')->willReturn($product);

        $itemOptions = ['option_ids' => '1,2', 'option_1' => 'yes', 'option_2' => 'yes'];

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOptionByCode', 'getProduct'])
            ->getMock();
        $item->method('getOptionByCode')->willReturnCallback(
            fn(string $code): ?ItemOption => array_key_exists($code, $itemOptions) ?
                new ItemOption(['option_value' => $itemOptions[ $code ]]) : null
        );
        $item->method('getProduct')->willReturn($product);

        $catalogHelper = $this->createStub(CatalogHelper::class);
        $catalogHelper->method('getTaxPrice')->willReturnCallback(
            fn(Product $product, float $price): float => round($price * 1.19, 2)
        );

        $helper = new Data(
            $catalogHelper,
            new Variables(),
            $this->createStub(ManagerInterface::class),
            new Arrays(),
            $this->createStub(CustomOptionExtensionFactory::class)
        );

        $this->assertSame([1 => 11.9], $helper->getItemOptionPrices($item));
    }

    /**
     * Tests numeric values as float.
     *
     * @return void
     */
    public function testFloatValue(): void
    {
        $helper = new Data(
            $this->createStub(CatalogHelper::class),
            new Variables(),
            $this->createStub(ManagerInterface::class),
            new Arrays(),
            $this->createStub(CustomOptionExtensionFactory::class)
        );

        $this->assertSame(12.5, $helper->floatValue('12.5'));
        $this->assertSame(3.0, $helper->floatValue(3));
        $this->assertSame(0.0, $helper->floatValue(null));
        $this->assertSame(0.0, $helper->floatValue('abc'));
    }
}
