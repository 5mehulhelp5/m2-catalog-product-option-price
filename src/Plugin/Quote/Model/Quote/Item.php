<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Quote\Model\Quote;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Item
{
    /** @var Calculation */
    protected $calculation;

    /** @var Data */
    protected $taxHelper;

    /** @var PriceCurrencyInterface */
    protected $priceCurrency;

    public function __construct(Calculation $calculation, Data $taxHelper, PriceCurrencyInterface $priceCurrency)
    {
        $this->calculation = $calculation;
        $this->taxHelper = $taxHelper;
        $this->priceCurrency = $priceCurrency;
    }

    public function afterCalcRowTotal(
        \Magento\Quote\Model\Quote\Item $subject,
        \Magento\Quote\Model\Quote\Item $result
    ): \Magento\Quote\Model\Quote\Item {
        $qty = $subject->getTotalQty();

        $quote = $subject->getQuote();

        $taxRateRequest = $this->calculation->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $quote->getStoreId(),
            $quote->getCustomerId()
        );

        $product = $subject->getProduct();

        $taxRateRequest->setData(
            'product_class_id',
            $product->getData('tax_class_id')
        );

        $rate = $this->calculation->getRate($taxRateRequest);

        $this->setOptionsPrice(
            $result,
            $qty,
            $rate
        );

        $this->setBaseOptionsPrice(
            $result,
            $qty,
            $rate
        );

        return $result;
    }

    private function setOptionsPrice(\Magento\Quote\Model\Quote\Item $item, float $qty, float $taxRate): void
    {
        $optionsPriceAttached = $this->getConvertedOptionsPriceAttached($item);
        $optionsPriceUnattached = $this->getConvertedOptionsPriceUnattached($item);

        if ($this->taxHelper->priceIncludesTax()) {
            $taxAmount = $this->calculation->calcTaxAmount(
                $optionsPriceAttached + $optionsPriceUnattached,
                $taxRate,
                true
            );

            $item->setData(
                'options_price',
                $this->priceCurrency->roundPrice($optionsPriceAttached + $optionsPriceUnattached - $taxAmount)
            );

            $item->setData(
                'options_price_incl_tax',
                $optionsPriceAttached + $optionsPriceUnattached
            );
        } else {
            $item->setData(
                'options_price',
                $this->priceCurrency->roundPrice($optionsPriceAttached + $optionsPriceUnattached)
            );

            $taxAmount = $this->calculation->calcTaxAmount(
                $optionsPriceAttached + $optionsPriceUnattached,
                $taxRate
            );

            $item->setData(
                'options_price_incl_tax',
                $this->priceCurrency->roundPrice($optionsPriceAttached + $optionsPriceUnattached + $taxAmount)
            );
        }

        $rowOptionsPrice =
            $this->priceCurrency->roundPrice($this->priceCurrency->roundPrice($optionsPriceAttached) * $qty) +
            $this->priceCurrency->roundPrice($optionsPriceUnattached);

        if ($this->taxHelper->priceIncludesTax()) {
            $taxAmount = $this->calculation->calcTaxAmount(
                $rowOptionsPrice,
                $taxRate,
                true
            );

            $item->setData(
                'row_options_price',
                $this->priceCurrency->roundPrice($rowOptionsPrice - $taxAmount)
            );

            $item->setData(
                'row_options_price_incl_tax',
                $this->priceCurrency->roundPrice($rowOptionsPrice)
            );
        } else {
            $taxAmount = $this->calculation->calcTaxAmount(
                $rowOptionsPrice,
                $taxRate
            );

            $item->setData(
                'row_options_price',
                $rowOptionsPrice
            );

            $item->setData(
                'row_options_price_incl_tax',
                $this->priceCurrency->roundPrice($rowOptionsPrice + $taxAmount)
            );
        }
    }

    private function getConvertedOptionsPriceAttached(\Magento\Quote\Model\Quote\Item $item): float
    {
        $price = $item->getData('converted_options_price_attached');

        if ($price === null) {
            $product = $item->getProduct();

            $price = $this->priceCurrency->convert(
                $product->getData('options_price_attached'),
                $item->getStore()
            );

            $item->setData(
                'converted_options_price_attached',
                $price
            );
        }

        return $price;
    }

    private function getConvertedOptionsPriceUnattached(\Magento\Quote\Model\Quote\Item $item): float
    {
        $price = $item->getData('converted_options_price_unattached');

        if ($price === null) {
            $product = $item->getProduct();

            $price = $this->priceCurrency->convert(
                $product->getData('options_price_unattached'),
                $item->getStore()
            );

            $item->setData(
                'converted_options_price_unattached',
                $price
            );
        }

        return $price;
    }

    private function setBaseOptionsPrice(\Magento\Quote\Model\Quote\Item $item, float $qty, float $taxRate): void
    {
        $product = $item->getProduct();

        $baseOptionsPriceAttached = $product->getData('options_price_attached');
        $baseOptionsPriceUnattached = $product->getData('options_price_unattached');

        if ($this->taxHelper->priceIncludesTax()) {
            $taxAmount = $this->calculation->calcTaxAmount(
                $baseOptionsPriceAttached + $baseOptionsPriceUnattached,
                $taxRate,
                true
            );

            $item->setData(
                'base_options_price',
                $this->priceCurrency->roundPrice($baseOptionsPriceAttached + $baseOptionsPriceUnattached - $taxAmount)
            );

            $item->setData(
                'base_options_price_incl_tax',
                $baseOptionsPriceAttached + $baseOptionsPriceUnattached
            );
        } else {
            $taxAmount = $this->calculation->calcTaxAmount(
                $baseOptionsPriceAttached + $baseOptionsPriceUnattached,
                $taxRate
            );

            $item->setData(
                'base_options_price',
                $baseOptionsPriceAttached + $baseOptionsPriceUnattached
            );

            $item->setData(
                'base_options_price_incl_tax',
                $this->priceCurrency->roundPrice($baseOptionsPriceAttached + $baseOptionsPriceUnattached + $taxAmount)
            );
        }

        $baseRowOptionsPrice =
            $this->priceCurrency->roundPrice($this->priceCurrency->roundPrice($baseOptionsPriceAttached) * $qty) +
            $this->priceCurrency->roundPrice($baseOptionsPriceUnattached);

        if ($this->taxHelper->priceIncludesTax()) {
            $taxAmount = $this->calculation->calcTaxAmount(
                $baseRowOptionsPrice,
                $taxRate,
                true
            );

            $item->setData(
                'base_row_options_price',
                $this->priceCurrency->roundPrice($baseRowOptionsPrice - $taxAmount)
            );

            $item->setData(
                'base_row_options_price_incl_tax',
                $baseRowOptionsPrice
            );
        } else {
            $taxAmount = $this->calculation->calcTaxAmount(
                $baseRowOptionsPrice,
                $taxRate
            );

            $item->setData(
                'base_row_options_price',
                $baseRowOptionsPrice
            );

            $item->setData(
                'base_row_options_price_incl_tax',
                $this->priceCurrency->roundPrice($baseRowOptionsPrice + $taxAmount)
            );
        }
    }
}
