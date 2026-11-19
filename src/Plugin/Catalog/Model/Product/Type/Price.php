<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Plugin\Catalog\Model\Product\Type;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Stores the prices of the selected product options on the product, separated into the prices that are attached to
 * the product (multiplied with the quantity) and the prices that are not (added once per row).
 */
class Price
{
    /**
     * @param ManagerInterface $eventManager
     * @param Variables $variables
     * @param Data $helper
     */
    public function __construct(
        protected ManagerInterface $eventManager,
        protected Variables $variables,
        protected Data $helper
    ) {
    }

    /**
     * Calculates the prices of the options from the final price without options and stores them in the data
     * "options_price_attached" and "options_price_unattached" of the product.
     *
     * Like Magento, the final price without options is calculated again from the base price and the event
     * "catalog_product_get_final_price". It is the base of the option prices with a percent price type and is stored in
     * the data "final_price_without_options" of the product; the data "final_price" keeps the result of Magento.
     * Observers of the event "catalog_product_get_options_price" can change both option prices, for example to add the
     * prices of options with an own product.
     *
     * @param Product\Type\Price $subject
     * @param float $result
     * @param float|null $qty
     * @param Product $product
     *
     * @return float
     */
    public function afterGetFinalPrice(Product\Type\Price $subject, float $result, ?float $qty, Product $product): float
    {
        if ($qty === null && $product->getData('calculated_final_price') !== null) {
            return $result;
        }

        $product->setData('final_price', $subject->getBasePrice($product, $qty));

        $this->eventManager->dispatch('catalog_product_get_final_price', ['product' => $product, 'qty' => $qty]);

        $finalPrice = $this->helper->floatValue($product->getData('final_price'));

        $product->setData('final_price_without_options', $finalPrice);
        $product->setData('final_price', $result);

        $transportObject = new DataObject(
            [
                'product'                  => $product,
                'qty'                      => $qty,
                'final_price'              => $finalPrice,
                'options_price_attached'   => $this->getOptionsPrice($product, $finalPrice, $qty),
                'options_price_unattached' => 0.0
            ]
        );

        $this->eventManager->dispatch('catalog_product_get_options_price', ['data' => $transportObject]);

        $product->setData(
            'options_price_attached',
            $this->helper->floatValue($transportObject->getData('options_price_attached'))
        );
        $product->setData(
            'options_price_unattached',
            $this->helper->floatValue($transportObject->getData('options_price_unattached'))
        );

        return $result;
    }

    /**
     * Returns the sum of the prices of the selected options.
     *
     * Observers of the event "catalog_product_get_option_price" can change the price of each option. Options that
     * Magento cannot create a group for are left out.
     *
     * @param Product $product
     * @param float $finalPrice
     * @param float|null $qty
     *
     * @return float
     */
    protected function getOptionsPrice(Product $product, float $finalPrice, ?float $qty): float
    {
        $optionIdsOption = $product->getCustomOption('option_ids');

        if ($optionIdsOption === null) {
            return 0.0;
        }

        $optionIdsValue = $this->variables->stringValueOrDefault($optionIdsOption->getValue());

        if ($optionIdsValue === '') {
            return 0.0;
        }

        $optionsPrice = 0.0;

        foreach (explode(',', $optionIdsValue) as $optionId) {
            $productOption = $product->getOptionById($this->variables->intValue($optionId));

            if ($productOption === null) {
                continue;
            }

            $customOption = $product->getCustomOption('option_' . $productOption->getId());

            if ($customOption === null) {
                continue;
            }

            try {
                $group = $productOption->groupFactory($productOption->getType());
            } catch (LocalizedException) {
                continue;
            }

            $group->setOption($productOption);
            $group->setData('configuration_item_option', $customOption);

            $transportObject = new DataObject(
                [
                    'product'        => $product,
                    'final_price'    => $finalPrice,
                    'qty'            => $qty,
                    'product_option' => $productOption,
                    'custom_option'  => $customOption,
                    'option_price'   => $group->getOptionPrice(
                        $this->variables->stringValueOrDefault($customOption->getValue()),
                        $finalPrice
                    )
                ]
            );

            $this->eventManager->dispatch('catalog_product_get_option_price', ['data' => $transportObject]);

            $optionsPrice += $this->helper->floatValue($transportObject->getData('option_price'));
        }

        return $optionsPrice;
    }
}
