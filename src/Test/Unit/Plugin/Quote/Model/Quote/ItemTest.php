<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Test\Unit\Plugin\Quote\Model\Quote;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\CatalogProductOptionPrice\Plugin\Quote\Model\Quote\Item;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the prices of the options of a quote item with and without tax, per unit and per row.
 *
 * Magento's models are partial mocks: their data methods work, the methods that need the database are stubbed.
 */
#[AllowMockObjectsWithoutExpectations]
class ItemTest extends TestCase
{
    /**
     * Returns the plugin with a tax rate of 19 % and a currency rate of 2 for the currency of the quote.
     *
     * @param bool $priceIncludesTax
     *
     * @return Item
     */
    private function createPlugin(bool $priceIncludesTax): Item
    {
        $calculation = $this->createStub(Calculation::class);
        $calculation->method('getRateRequest')->willReturn(new DataObject());
        $calculation->method('getRate')->willReturn(19.0);
        $calculation->method('calcTaxAmount')->willReturnCallback(
            fn(float $price, float $taxRate, bool $priceIncludeTax = false): float => $priceIncludeTax ?
                $price - $price / (1 + $taxRate / 100) : $price * $taxRate / 100
        );

        $taxHelper = $this->createStub(TaxHelper::class);
        $taxHelper->method('priceIncludesTax')->willReturn($priceIncludesTax);

        $priceCurrency = $this->createStub(PriceCurrency::class);
        $priceCurrency->method('roundPrice')->willReturnCallback(fn(float $price): float => round($price, 2));
        $priceCurrency->method('convert')->willReturnCallback(fn(float $amount): float => $amount * 2);

        return new Item($calculation, $taxHelper, $priceCurrency, $this->createHelper());
    }

    /**
     * Returns the helper of the module.
     *
     * @return Data
     */
    private function createHelper(): Data
    {
        return new Data(
            $this->createStub(CatalogHelper::class),
            new Variables(),
            $this->createStub(ManagerInterface::class),
            new Arrays(new Variables()),
            $this->createStub(CustomOptionExtensionFactory::class)
        );
    }

    /**
     * Returns a quote item of the product with the option prices and the quantity.
     *
     * @param float $priceAttached
     * @param float $priceUnattached
     * @param float $qty
     *
     * @return QuoteItem
     */
    private function createItem(float $priceAttached, float $priceUnattached, float $qty): QuoteItem
    {
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $product->setData('options_price_attached', $priceAttached);
        $product->setData('options_price_unattached', $priceUnattached);

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote', 'getProduct', 'getTotalQty', 'getStore'])
            ->getMock();
        $item->method('getQuote')->willReturn($this->createStub(Quote::class));
        $item->method('getProduct')->willReturn($product);
        $item->method('getTotalQty')->willReturn($qty);

        return $item;
    }

    /**
     * Tests prices without tax: the attached price is multiplied with the quantity, the unattached price is added
     * once.
     *
     * @return void
     */
    public function testPricesExcludingTax(): void
    {
        $item = $this->createItem(10.0, 5.0, 2.0);

        $this->createPlugin(false)->afterCalcRowTotal($item, $item);

        $this->assertSame(
            [
                'options_price'                   => 30.0,
                'options_price_incl_tax'          => 35.7,
                'row_options_price'               => 50.0,
                'row_options_price_incl_tax'      => 59.5,
                'base_options_price'              => 15.0,
                'base_options_price_incl_tax'     => 17.85,
                'base_row_options_price'          => 25.0,
                'base_row_options_price_incl_tax' => 29.75
            ],
            $this->getPrices($item)
        );
    }

    /**
     * Tests prices including tax.
     *
     * @return void
     */
    public function testPricesIncludingTax(): void
    {
        $item = $this->createItem(11.9, 0.0, 3.0);

        $this->createPlugin(true)->afterCalcRowTotal($item, $item);

        $this->assertSame(
            [
                'options_price'                   => 20.0,
                'options_price_incl_tax'          => 23.8,
                'row_options_price'               => 60.0,
                'row_options_price_incl_tax'      => 71.4,
                'base_options_price'              => 10.0,
                'base_options_price_incl_tax'     => 11.9,
                'base_row_options_price'          => 30.0,
                'base_row_options_price_incl_tax' => 35.7
            ],
            $this->getPrices($item)
        );
    }

    /**
     * Tests that missing option prices of the product count as 0.
     *
     * @return void
     */
    public function testMissingPrices(): void
    {
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote', 'getProduct', 'getTotalQty', 'getStore'])
            ->getMock();
        $item->method('getQuote')->willReturn($this->createStub(Quote::class));
        $item->method('getProduct')->willReturn($product);
        $item->method('getTotalQty')->willReturn(1);

        $this->createPlugin(false)->afterCalcRowTotal($item, $item);

        $this->assertSame(0.0, $item->getData('row_options_price_incl_tax'));
        $this->assertSame(0.0, $item->getData('base_options_price'));
    }

    /**
     * Returns the prices of the options of the item.
     *
     * @param QuoteItem $item
     *
     * @return array<string, mixed>
     */
    private function getPrices(QuoteItem $item): array
    {
        $prices = [];

        $columns = ['options_price', 'options_price_incl_tax', 'row_options_price', 'row_options_price_incl_tax'];

        foreach (['', 'base_'] as $prefix) {
            foreach ($columns as $column) {
                $prices[ $prefix . $column ] = $item->getData($prefix . $column);
            }
        }

        return $prices;
    }
}
