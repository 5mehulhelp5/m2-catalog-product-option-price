<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Sales\Api;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Magento\Catalog\Api\Data\CustomOptionExtension;
use Magento\Catalog\Api\Data\CustomOptionExtensionFactory;
use Magento\Catalog\Api\Data\ProductOptionExtension;
use Magento\Catalog\Model\CustomOptions\CustomOption;
use Magento\Catalog\Model\Product\Option;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\Order\Item;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class OrderRepositoryInterface
{
    /** @var Arrays */
    protected $arrays;

    /** @var Variables */
    protected $variables;

    /** @var CustomOptionExtensionFactory */
    protected $customOptionExtensionFactory;

    public function __construct(
        Arrays $arrays,
        Variables $variables,
        CustomOptionExtensionFactory $customOptionExtensionFactory
    ) {
        $this->arrays = $arrays;
        $this->variables = $variables;
        $this->customOptionExtensionFactory = $customOptionExtensionFactory;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function afterGetList(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->addCustomerBenefitApiFlag($order);
        }

        return $orderSearchResult;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function afterGet(
        \Magento\Sales\Api\OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->addCustomerBenefitApiFlag($order);

        return $order;
    }

    private function addCustomerBenefitApiFlag(OrderInterface $order)
    {
        foreach ($order->getItems() as $item) {
            if ($item instanceof Item) {
                $this->addPriceToOrderItem($item);
            }
        }

        $shippingAssignments = $order->getExtensionAttributes()->getShippingAssignments();

        foreach ($shippingAssignments as $shippingAssignment) {
            foreach ($shippingAssignment->getItems() as $item) {
                if ($item instanceof Item) {
                    $this->addPriceToOrderItem($item);
                }
            }
        }
    }

    protected function addPriceToOrderItem(Item $item): void
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
                    if ($itemProductOptionsOptionOriginalPrice !== null) {
                        $customOptionExtensionAttributes->setOriginalPrice(
                            $itemProductOptionsOptionOriginalPrice
                        );
                    }

                    $itemProductOptionsOptionDiscount = $this->arrays->getValue(
                        $itemProductOptionsOption,
                        'discount'
                    );
                    if ($itemProductOptionsOptionDiscount !== null) {
                        $customOptionExtensionAttributes->setDiscount($itemProductOptionsOptionDiscount);
                    }

                    $itemProductOptionsOptionPrice = $this->arrays->getValue(
                        $itemProductOptionsOption,
                        'price'
                    );
                    if ($itemProductOptionsOptionPrice) {
                        $customOptionExtensionAttributes->setPrice($itemProductOptionsOptionPrice);
                    }

                    $customOption->setExtensionAttributes($customOptionExtensionAttributes);
                }
            }
        }
    }
}
