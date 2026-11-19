<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Plugin\Quote\Model\Quote;

use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Magento\Directory\Model\PriceCurrency;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Calculation;

/**
 * Stores the prices of the options of a quote item (per unit and per row, with and without tax, in the currency of
 * the quote and in the base currency) when Magento calculates the row total of the item.
 */
class Item
{
    /**
     * @param Calculation $calculation
     * @param TaxHelper $taxHelper
     * @param PriceCurrency $priceCurrency
     * @param Data $helper
     */
    public function __construct(
        protected Calculation $calculation,
        protected TaxHelper $taxHelper,
        protected PriceCurrency $priceCurrency,
        protected Data $helper
    ) {
    }

    /**
     * Stores the prices of the options.
     *
     * They are calculated from the option prices of the product with the tax rate of the product in the quote.
     *
     * @param QuoteItem $subject
     * @param QuoteItem $result
     *
     * @return QuoteItem
     */
    public function afterCalcRowTotal(QuoteItem $subject, QuoteItem $result): QuoteItem
    {
        $quote = $subject->getQuote();

        $taxRateRequest = $this->calculation->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $quote->getStoreId(),
            is_numeric($quote->getData('customer_id')) ? (int)$quote->getData('customer_id') : null
        );

        $taxRateRequest->setData('product_class_id', $subject->getProduct()->getData('tax_class_id'));

        $taxRate = $this->helper->floatValue($this->calculation->getRate($taxRateRequest));
        $qty = $this->helper->floatValue($subject->getTotalQty());
        $product = $result->getProduct();

        $this->setPrices(
            $result,
            '',
            $this->getConvertedPrice($result, 'options_price_attached'),
            $this->getConvertedPrice($result, 'options_price_unattached'),
            $qty,
            $taxRate
        );

        $this->setPrices(
            $result,
            'base_',
            $this->helper->floatValue($product->getData('options_price_attached')),
            $this->helper->floatValue($product->getData('options_price_unattached')),
            $qty,
            $taxRate
        );

        return $result;
    }

    /**
     * Sets the price of the options per unit and per row, with and without tax.
     *
     * The attached price is multiplied with the quantity, the unattached price is added once per row. The prices of
     * the products include tax or not, as configured.
     *
     * @param QuoteItem $item
     * @param string $prefix "" for the currency of the quote, "base_" for the base currency
     * @param float $priceAttached
     * @param float $priceUnattached
     * @param float $qty
     * @param float $taxRate
     *
     * @return void
     */
    private function setPrices(
        QuoteItem $item,
        string $prefix,
        float $priceAttached,
        float $priceUnattached,
        float $qty,
        float $taxRate
    ): void {
        [$price, $priceInclTax] = $this->getPricesExclAndInclTax($priceAttached + $priceUnattached, $taxRate);

        $item->setData($prefix . 'options_price', $price);
        $item->setData($prefix . 'options_price_incl_tax', $priceInclTax);

        $rowPrice = $this->priceCurrency->roundPrice($this->priceCurrency->roundPrice($priceAttached) * $qty) +
            $this->priceCurrency->roundPrice($priceUnattached);

        [$rowPrice, $rowPriceInclTax] = $this->getPricesExclAndInclTax($rowPrice, $taxRate);

        $item->setData($prefix . 'row_options_price', $rowPrice);
        $item->setData($prefix . 'row_options_price_incl_tax', $rowPriceInclTax);
    }

    /**
     * Returns the rounded price without and with tax.
     *
     * @param float $price including tax if the prices of the products include tax
     * @param float $taxRate
     *
     * @return float[]
     * @phpstan-return array{0: float, 1: float}
     */
    private function getPricesExclAndInclTax(float $price, float $taxRate): array
    {
        if ($this->taxHelper->priceIncludesTax()) {
            $taxAmount = $this->calculation->calcTaxAmount($price, $taxRate, true);

            return [
                $this->priceCurrency->roundPrice($price - $taxAmount),
                $this->priceCurrency->roundPrice($price)
            ];
        }

        $taxAmount = $this->calculation->calcTaxAmount($price, $taxRate);

        return [
            $this->priceCurrency->roundPrice($price),
            $this->priceCurrency->roundPrice($price + $taxAmount)
        ];
    }

    /**
     * Returns the option price of the product converted into the currency of the quote.
     *
     * The price is converted once per item, like the price of the item.
     *
     * @param QuoteItem $item
     * @param string $key "options_price_attached" or "options_price_unattached"
     *
     * @return float
     */
    private function getConvertedPrice(QuoteItem $item, string $key): float
    {
        $price = $item->getData('converted_' . $key);

        if ($price === null) {
            $price = $this->priceCurrency->convert(
                $this->helper->floatValue($item->getProduct()->getData($key)),
                $item->getStore()
            );

            $item->setData('converted_' . $key, $price);
        }

        return $this->helper->floatValue($price);
    }
}
