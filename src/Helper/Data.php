<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Helper;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Api\Data\CustomOptionInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item;

/**
 * Prices of the product options of quote items and order items.
 */
class Data
{
    /**
     * @param CatalogHelper $catalogHelper
     * @param Variables $variables
     * @param ManagerInterface $eventManager
     * @param Arrays $arrays
     * @param CustomOptionExtensionFactory $customOptionExtensionFactory
     */
    public function __construct(
        protected CatalogHelper $catalogHelper,
        protected Variables $variables,
        protected ManagerInterface $eventManager,
        protected Arrays $arrays,
        protected CustomOptionExtensionFactory $customOptionExtensionFactory
    ) {
    }

    /**
     * Returns the prices of the selected options of the quote item including tax by option ID.
     *
     * Observers of the event "catalog_product_get_option_price" can change the price of each option. Options that
     * Magento cannot create a group for are left out.
     *
     * @param AbstractItem $item
     *
     * @return float[]
     * @phpstan-return array<int, float>
     */
    public function getItemOptionPrices(AbstractItem $item): array
    {
        // Magento declares the option as always set, it is null if the item has no options
        /** @var OptionInterface|null $optionIdsOption */
        $optionIdsOption = $item->getOptionByCode('option_ids');

        if ($optionIdsOption === null) {
            return [];
        }

        $optionIdsValue = $this->variables->stringValueOrDefault($optionIdsOption->getValue());

        if ($optionIdsValue === '') {
            return [];
        }

        $product = $item->getProduct();
        // the base of the option prices with a percent price type, see the plugin of the product price
        $finalPrice = $this->floatValue(
            $product->getData('final_price_without_options') ?? $product->getFinalPrice()
        );
        $optionPrices = [];

        foreach (explode(',', $optionIdsValue) as $optionId) {
            $productOption = $product->getOptionById($this->variables->intValue($optionId));

            if ($productOption === null) {
                continue;
            }

            /** @var OptionInterface|null $customOption */
            $customOption = $item->getOptionByCode('option_' . $productOption->getId());

            if ($customOption === null) {
                continue;
            }

            try {
                $group = $productOption->groupFactory($productOption->getType());
            } catch (LocalizedException) {
                continue;
            }

            $group->setOption($productOption);
            $group->setData('configuration_item', $item);
            $group->setData('configuration_item_option', $customOption);

            $transportObject = new DataObject(
                [
                    'product'        => $product,
                    'final_price'    => $finalPrice,
                    'qty'            => $item->getQty(),
                    'product_option' => $productOption,
                    'custom_option'  => $customOption,
                    'option_price'   => $group->getOptionPrice(
                        $this->variables->stringValueOrDefault($customOption->getValue()),
                        $finalPrice
                    )
                ]
            );

            $this->eventManager->dispatch('catalog_product_get_option_price', ['data' => $transportObject]);

            $optionPrices[ (int)$optionId ] = $this->floatValue(
                $this->catalogHelper->getTaxPrice(
                    $productOption->getProduct(),
                    $this->floatValue($transportObject->getData('option_price')),
                    true
                )
            );
        }

        return $optionPrices;
    }

    /**
     * Adds the prices of the options of the order items to the extension attributes of their custom options.
     *
     * Covers the items of the order and the items of its shipping assignments.
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    public function addProductOptionPriceToOrder(OrderInterface $order): void
    {
        foreach ($order->getItems() as $item) {
            if ($item instanceof Item) {
                $this->addProductOptionPriceToOrderItem($item);
            }
        }

        foreach ($order->getExtensionAttributes()?->getShippingAssignments() ?? [] as $shippingAssignment) {
            foreach ($shippingAssignment->getItems() as $item) {
                if ($item instanceof Item) {
                    $this->addProductOptionPriceToOrderItem($item);
                }
            }
        }
    }

    /**
     * Adds the prices of the options of the order item to the extension attributes of its custom options.
     *
     * These are the original price, the discount and the price, as stored when the order was placed.
     *
     * Observers of the event "order_item_option_price" can change the prices.
     *
     * @param Item $item
     *
     * @return void
     */
    public function addProductOptionPriceToOrderItem(Item $item): void
    {
        $customOptions = $item->getProductOption()?->getExtensionAttributes()?->getCustomOptions() ?? [];

        if (count($customOptions) === 0) {
            return;
        }

        // Magento declares the product options as array, they are null for items without options
        /** @var array<string, mixed>|null $productOptions */
        $productOptions = $item->getProductOptions();
        $itemProductOptions = $this->arrays->getValue($productOptions ?? [], 'options', []);

        foreach (is_array($itemProductOptions) ? $itemProductOptions : [] as $itemProductOption) {
            if (! is_array($itemProductOption)) {
                continue;
            }

            $optionId = $this->variables->stringValueOrDefault($itemProductOption[ 'option_id' ] ?? null);

            foreach ($customOptions as $customOption) {
                if ($this->variables->stringValueOrDefault($customOption->getOptionId()) === $optionId) {
                    $this->addPrices($item, $itemProductOption, $customOption);
                }
            }
        }
    }

    /**
     * Returns a numeric value as float, 0 for other values (prices that are not set).
     *
     * @param mixed $value
     *
     * @return float
     */
    public function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float)$value : 0.0;
    }

    /**
     * Adds the prices of the option to the extension attributes of the custom option.
     *
     * @param Item $item
     * @param array $itemProductOption
     * @phpstan-param array<int|string, mixed> $itemProductOption
     * @param CustomOptionInterface $customOption
     *
     * @return void
     */
    protected function addPrices(Item $item, array $itemProductOption, CustomOptionInterface $customOption): void
    {
        $transportObject = new DataObject(
            [
                'item'                     => $item,
                'item_product_option_data' => $itemProductOption,
                'custom_option'            => $customOption,
                'original_price'           => $itemProductOption[ 'original_price' ] ?? null,
                'discount'                 => $itemProductOption[ 'discount' ] ?? null,
                'price'                    => $itemProductOption[ 'price' ] ?? null
            ]
        );

        $this->eventManager->dispatch('order_item_option_price', ['data' => $transportObject]);

        $extensionAttributes =
            $customOption->getExtensionAttributes() ?? $this->customOptionExtensionFactory->create();

        $originalPrice = $transportObject->getData('original_price');

        if (is_numeric($originalPrice)) {
            $extensionAttributes->setOriginalPrice((float)$originalPrice);
        }

        $discount = $transportObject->getData('discount');

        if (is_numeric($discount)) {
            $extensionAttributes->setDiscount((float)$discount);
        }

        $price = $transportObject->getData('price');

        if (is_numeric($price)) {
            $extensionAttributes->setPrice((float)$price);
        }

        $customOption->setExtensionAttributes($extensionAttributes);
    }
}
