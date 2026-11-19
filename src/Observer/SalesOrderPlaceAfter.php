<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Observer;

use FeWeDev\Base\Variables;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\CatalogProductOptionPrice\Model\Order\Item\OptionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * Stores the prices of the options of the order items in their product options when the order is placed: the price
 * including tax and, for items with a discount, the original price and the share of the discount.
 */
class SalesOrderPlaceAfter implements ObserverInterface
{
    /**
     * @param Variables $variables
     * @param CatalogHelper $catalogHelper
     * @param ManagerInterface $eventManager
     * @param OptionFactory $optionFactory
     * @param PriceCurrency $priceCurrency
     * @param Data $helper
     */
    public function __construct(
        protected Variables $variables,
        protected CatalogHelper $catalogHelper,
        protected ManagerInterface $eventManager,
        protected OptionFactory $optionFactory,
        protected PriceCurrency $priceCurrency,
        protected Data $helper
    ) {
    }

    /**
     * Adds the prices to the items of the placed order.
     *
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');

        if ($order instanceof Order) {
            foreach ($order->getItems() as $item) {
                if ($item instanceof Item) {
                    $this->addPriceToOrderItem($item);
                }
            }
        }
    }

    /**
     * Adds the prices of the options to the product options of the item.
     *
     * The prices are calculated from the final price of the product (of the parent item for the items of bundle and
     * configurable products). Observers of the event "catalog_product_get_option_price" can change the price of each
     * option.
     *
     * @param Item $item
     *
     * @return void
     * @throws LocalizedException
     */
    protected function addPriceToOrderItem(Item $item): void
    {
        $parentItem = $item->getParentItem();
        $product = $parentItem instanceof Item ? $parentItem->getProduct() : $item->getProduct();

        if ($product === null) {
            return;
        }

        // Magento declares the product options as array, they are null for items without options
        /** @var array<string, mixed>|null $itemProductOptions */
        $itemProductOptions = $item->getProductOptions();
        $itemProductOptions ??= [];
        $itemOptions = $itemProductOptions[ 'options' ] ?? [];

        if (! is_array($itemOptions) || count($itemOptions) === 0) {
            return;
        }

        $productOptions = $this->getProductOptions($product);
        $finalPrice = $this->helper->floatValue($product->getFinalPrice());

        foreach ($itemOptions as $key => $itemOption) {
            if (! is_array($itemOption)) {
                continue;
            }

            $optionId = $this->variables->stringValueOrDefault($itemOption[ 'option_id' ] ?? null);
            $productOption = $productOptions[ $optionId ] ?? null;

            if ($productOption === null) {
                continue;
            }

            $optionPrice = $this->getOptionPrice($item, $product, $productOption, $itemOption, $finalPrice);

            if ($this->helper->floatValue($item->getDiscountAmount()) > 0) {
                $itemOptions[ $key ][ 'original_price' ] = $optionPrice;

                $discount = $this->getDiscount($item, $optionPrice);

                $itemOptions[ $key ][ 'discount' ] = $discount;

                $optionPrice -= $discount;
            }

            $itemOptions[ $key ][ 'price' ] = $optionPrice;
        }

        $itemProductOptions[ 'options' ] = $itemOptions;

        $item->setProductOptions($itemProductOptions);
    }

    /**
     * Returns the options of the product by option ID.
     *
     * @param Product $product
     *
     * @return Option[]
     * @phpstan-return array<string, Option>
     */
    protected function getProductOptions(Product $product): array
    {
        $productOptions = [];

        foreach ($product->getProductOptionsCollection() as $productOption) {
            if ($productOption instanceof Option) {
                $productOptions[ $this->variables->stringValueOrDefault($productOption->getId()) ] = $productOption;
            }
        }

        return $productOptions;
    }

    /**
     * Returns the price of the option including tax.
     *
     * @param Item $item
     * @param Product $product
     * @param Option $productOption
     * @param array $itemOption
     * @phpstan-param array<int|string, mixed> $itemOption
     * @param float $finalPrice
     *
     * @return float
     * @throws LocalizedException
     */
    protected function getOptionPrice(
        Item $item,
        Product $product,
        Option $productOption,
        array $itemOption,
        float $finalPrice
    ): float {
        $productOption->setData('item', $item);

        $customOption = $this->optionFactory->create(['data' => $itemOption]);

        $group = $productOption->groupFactory($productOption->getType());
        $group->setOption($productOption);
        $group->setData('configuration_item', $item);
        $group->setData('configuration_item_option', $customOption);

        $transportObject = new DataObject(
            [
                'product'        => $product,
                'final_price'    => $finalPrice,
                'qty'            => $item->getQtyOrdered(),
                'product_option' => $productOption,
                'custom_option'  => $customOption,
                'option_price'   => $group->getOptionPrice(
                    $this->variables->stringValueOrDefault($customOption->getValue()),
                    $finalPrice
                )
            ]
        );

        $this->eventManager->dispatch('catalog_product_get_option_price', ['data' => $transportObject]);

        return $this->helper->floatValue(
            $this->catalogHelper->getTaxPrice(
                $product,
                $this->helper->floatValue($transportObject->getData('option_price')),
                true
            )
        );
    }

    /**
     * Returns the share of the discount of the item for the price of the option.
     *
     * The share is the price in the ratio of the discount to the row total of the item including tax, rounded.
     *
     * @param Item $item
     * @param float $optionPrice
     *
     * @return float
     */
    protected function getDiscount(Item $item, float $optionPrice): float
    {
        $rowTotalInclTax = $this->helper->floatValue($item->getRowTotalInclTax());

        if ($rowTotalInclTax <= 0) {
            return 0.0;
        }

        return $this->priceCurrency->roundPrice(
            $optionPrice * $this->helper->floatValue($item->getDiscountAmount()) / $rowTotalInclTax
        );
    }
}
