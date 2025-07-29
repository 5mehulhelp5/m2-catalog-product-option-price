<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Helper;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Magento\Catalog\Api\Data\CustomOptionExtension;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Api\Data\ProductOptionExtension;
use Magento\Catalog\Model\CustomOptions\CustomOption;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var \Magento\Catalog\Helper\Data */
    protected $catalogHelper;

    /** @var Variables */
    protected $variables;

    /** @var ManagerInterface */
    protected $eventManager;

    /** @var Arrays */
    protected $arrays;

    /** @var CustomOptionExtensionFactory */
    protected $customOptionExtensionFactory;

    public function __construct(
        \Magento\Catalog\Helper\Data $catalogHelper,
        Variables $variables,
        ManagerInterface $eventManager,
        Arrays $arrays,
        CustomOptionExtensionFactory $customOptionExtensionFactory
    ) {
        $this->catalogHelper = $catalogHelper;
        $this->variables = $variables;
        $this->eventManager = $eventManager;
        $this->arrays = $arrays;
        $this->customOptionExtensionFactory = $customOptionExtensionFactory;
    }

    public function getItemOptionPrices(AbstractItem $item): array
    {
        $optionIdsOption = $item->getOptionByCode('option_ids');

        $optionPrices = [];

        if ($optionIdsOption) {
            $optionIdsOptionValue = $optionIdsOption->getValue();

            if (! $this->variables->isEmpty($optionIdsOptionValue)) {
                $product = $item->getProduct();

                $finalPrice = $product->getFinalPrice();

                $qty = $item->getQty();

                $optionIds = explode(
                    ',',
                    $optionIdsOptionValue
                );

                foreach ($optionIds as $optionId) {
                    $productOption = $product->getOptionById($optionId);

                    if ($productOption) {
                        $customOption = $item->getOptionByCode('option_' . $productOption->getId());

                        try {
                            $group = $productOption->groupFactory($productOption->getType());
                        } catch (LocalizedException $exception) {
                            continue;
                        }

                        $group->setOption($productOption);
                        $group->setData(
                            'configuration_item',
                            $item
                        );
                        $group->setData(
                            'configuration_item_option',
                            $customOption
                        );

                        $optionPrice = $group->getOptionPrice(
                            $customOption->getValue(),
                            $finalPrice
                        );

                        $transportObject = new DataObject(
                            [
                                'product'        => $product,
                                'final_price'    => $finalPrice,
                                'qty'            => $qty,
                                'product_option' => $productOption,
                                'custom_option'  => $customOption,
                                'option_price'   => $optionPrice
                            ]
                        );

                        $this->eventManager->dispatch(
                            'catalog_product_get_option_price',
                            [
                                'data' => $transportObject
                            ]
                        );

                        $optionPrice = $transportObject->getData('option_price');

                        $optionPrice = $this->catalogHelper->getTaxPrice(
                            $productOption->getProduct(),
                            $optionPrice,
                            true
                        );

                        $optionPrices[ $optionId ] = $optionPrice;
                    }
                }
            }
        }

        return $optionPrices;
    }

    public function addProductOptionPriceToOrder(OrderInterface $order)
    {
        foreach ($order->getItems() as $item) {
            if ($item instanceof Item) {
                $this->addProductOptionPriceToOrderItem($item);
            }
        }

        $shippingAssignments = $order->getExtensionAttributes()->getShippingAssignments();

        foreach ($shippingAssignments as $shippingAssignment) {
            foreach ($shippingAssignment->getItems() as $item) {
                if ($item instanceof Item) {
                    $this->addProductOptionPriceToOrderItem($item);
                }
            }
        }
    }

    public function addProductOptionPriceToOrderItem(Item $item): void
    {
        $itemProductOptions = $item->getProductOptions();

        $itemProductOptionsOptions = $this->arrays->getValue(
            $itemProductOptions,
            'options',
            []
        );

        foreach ($itemProductOptionsOptions as $itemProductOptionsOption) {
            $itemProductOptionsOptionId = $this->arrays->getValue(
                $itemProductOptionsOption,
                'option_id'
            );

            /** @var Option $productOptionData */
            $productOptionData = $item->getProductOption();

            if ($productOptionData === null) {
                continue;
            }

            /** @var ProductOptionExtension $productOptionDataAttributes */
            $productOptionDataAttributes = $productOptionData->getExtensionAttributes();

            $customOptions = $productOptionDataAttributes->getCustomOptions();

            /** @var CustomOption $customOption */
            foreach ($customOptions as $customOption) {
                if ($customOption->getOptionId() == $itemProductOptionsOptionId) {
                    /** @var CustomOptionExtension $customOptionExtensionAttributes */
                    $customOptionExtensionAttributes = $customOption->getExtensionAttributes();

                    $customOptionExtensionAttributes =
                        $customOptionExtensionAttributes ? : $this->customOptionExtensionFactory->create();

                    $itemProductOptionsOptionOriginalPrice = $this->arrays->getValue(
                        $itemProductOptionsOption,
                        'original_price'
                    );
                    $itemProductOptionsOptionDiscount = $this->arrays->getValue(
                        $itemProductOptionsOption,
                        'discount'
                    );
                    $itemProductOptionsOptionPrice = $this->arrays->getValue(
                        $itemProductOptionsOption,
                        'price'
                    );

                    $transportObject = new DataObject(
                        [
                            'item'                     => $item,
                            'item_product_option_data' => $itemProductOptionsOption,
                            'custom_option'            => $customOption,
                            'original_price'           => $itemProductOptionsOptionOriginalPrice,
                            'discount'                 => $itemProductOptionsOptionDiscount,
                            'price'                    => $itemProductOptionsOptionPrice
                        ]
                    );

                    $this->eventManager->dispatch(
                        'order_item_option_price',
                        [
                            'data' => $transportObject
                        ]
                    );

                    $itemProductOptionsOptionOriginalPrice = $transportObject->getData('original_price');
                    if ($itemProductOptionsOptionOriginalPrice !== null) {
                        $customOptionExtensionAttributes->setOriginalPrice(
                            $itemProductOptionsOptionOriginalPrice
                        );
                    }

                    $itemProductOptionsOptionDiscount = $transportObject->getData('discount');
                    if ($itemProductOptionsOptionDiscount !== null) {
                        $customOptionExtensionAttributes->setDiscount($itemProductOptionsOptionDiscount);
                    }

                    $itemProductOptionsOptionPrice = $transportObject->getData('price');
                    if ($itemProductOptionsOptionPrice) {
                        $customOptionExtensionAttributes->setPrice($itemProductOptionsOptionPrice);
                    }

                    $customOption->setExtensionAttributes($customOptionExtensionAttributes);
                }
            }
        }
    }
}
