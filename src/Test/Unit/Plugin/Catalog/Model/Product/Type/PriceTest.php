<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Test\Unit\Plugin\Catalog\Model\Product\Type;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\CatalogProductOptionPrice\Model\Order\Item\Option as ItemOption;
use Infrangible\CatalogProductOptionPrice\Plugin\Catalog\Model\Product\Type\Price;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Type\DefaultType;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the option prices that are stored on the product when its final price is calculated.
 *
 * Magento's models are partial mocks: their data methods work, the methods that need the database are stubbed.
 */
#[AllowMockObjectsWithoutExpectations]
class PriceTest extends TestCase
{
    /** @var string[] names of the dispatched events */
    private array $events = [];

    /**
     * Returns the plugin; observers reduce the final price to 90 and add 7 as unattached price.
     *
     * @return Price
     */
    private function createPlugin(): Price
    {
        $eventManager = $this->createStub(ManagerInterface::class);
        $eventManager->method('dispatch')->willReturnCallback(
            function (string $eventName, array $data = []): void {
                $this->events[] = $eventName;

                if ($eventName === 'catalog_product_get_final_price') {
                    $data[ 'product' ]->setData('final_price', 90.0);
                } elseif ($eventName === 'catalog_product_get_options_price') {
                    $data[ 'data' ]->setData('options_price_unattached', 7);
                }
            }
        );

        $helper = new Data(
            $this->createStub(CatalogHelper::class),
            new Variables(),
            $eventManager,
            new Arrays(),
            $this->createStub(CustomOptionExtensionFactory::class)
        );

        return new Price($eventManager, new Variables(), $helper);
    }

    /**
     * Returns an option whose price is 10 % of the final price, or an option without group.
     *
     * @param int $id
     * @param bool $withGroup
     *
     * @return Option
     */
    private function createOption(int $id, bool $withGroup = true): Option
    {
        $group = $this->createStub(DefaultType::class);
        $group->method('getOptionPrice')->willReturnCallback(
            fn(string $value, float $basePrice): float => $value === 'yes' ? $basePrice * 0.1 : 0.0
        );

        $option = $this->getMockBuilder(Option::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getType', 'groupFactory'])
            ->getMock();
        $option->method('getId')->willReturn((string)$id);
        $option->method('getType')->willReturn('field');

        if ($withGroup) {
            $option->method('groupFactory')->willReturn($group);
        } else {
            $option->method('groupFactory')->willThrowException(new LocalizedException(new Phrase('No group')));
        }

        return $option;
    }

    /**
     * Returns a product with the selected options.
     *
     * @param array<string, string> $customOptions code => value
     * @param Option[] $options
     *
     * @return Product
     */
    private function createProduct(array $customOptions, array $options): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomOption', 'getOptionById'])
            ->getMock();
        $product->method('getCustomOption')->willReturnCallback(
            fn(string $code): ?ItemOption => array_key_exists($code, $customOptions) ?
                new ItemOption(['option_value' => $customOptions[ $code ]]) : null
        );
        $product->method('getOptionById')->willReturnCallback(
            fn(int $optionId): ?Option => $options[ (string)$optionId ] ?? null
        );

        return $product;
    }

    /**
     * Tests the attached price of the selected options and the unattached price of the observer.
     *
     * @return void
     */
    public function testOptionPrices(): void
    {
        $product = $this->createProduct(
            ['option_ids' => '1,2,3,4', 'option_1' => 'yes', 'option_2' => 'yes', 'option_4' => 'yes'],
            ['1' => $this->createOption(1), '2' => $this->createOption(2), '4' => $this->createOption(4, false)]
        );

        $subject = $this->createStub(Product\Type\Price::class);
        $subject->method('getBasePrice')->willReturn(100.0);

        $result = $this->createPlugin()->afterGetFinalPrice($subject, 108.0, 1.0, $product);

        $this->assertSame(108.0, $result);
        $this->assertSame(18.0, $product->getData('options_price_attached'));
        $this->assertSame(7.0, $product->getData('options_price_unattached'));
        $this->assertSame(90.0, $product->getData('final_price_without_options'));
        $this->assertSame(108.0, $product->getData('final_price'));
        $this->assertSame(
            [
                'catalog_product_get_final_price',
                'catalog_product_get_option_price',
                'catalog_product_get_option_price',
                'catalog_product_get_options_price'
            ],
            $this->events
        );
    }

    /**
     * Tests that a calculated final price is used without calculating the option prices.
     *
     * @return void
     */
    public function testCalculatedFinalPrice(): void
    {
        $product = $this->createProduct([], []);
        $product->setData('calculated_final_price', 50.0);

        $result = $this->createPlugin()->afterGetFinalPrice(
            $this->createStub(Product\Type\Price::class),
            50.0,
            null,
            $product
        );

        $this->assertSame(50.0, $result);
        $this->assertNull($product->getData('options_price_attached'));
        $this->assertSame([], $this->events);
    }

    /**
     * Tests a product without options.
     *
     * @return void
     */
    public function testWithoutOptions(): void
    {
        $product = $this->createProduct([], []);

        $subject = $this->createStub(Product\Type\Price::class);
        $subject->method('getBasePrice')->willReturn(100.0);

        $this->createPlugin()->afterGetFinalPrice($subject, 90.0, null, $product);

        $this->assertSame(0.0, $product->getData('options_price_attached'));
        $this->assertSame(7.0, $product->getData('options_price_unattached'));
    }
}
