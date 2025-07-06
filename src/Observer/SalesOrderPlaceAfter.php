<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Observer;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Instances;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\ItemRepository;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class SalesOrderPlaceAfter implements ObserverInterface
{
    /** @var Variables */
    protected $variables;

    /** @var Arrays */
    protected $arrays;

    /** @var ItemRepository */
    protected $itemRepository;

    /** @var Data */
    protected $catalogHelper;

    /** @var Instances */
    protected $instanceHelper;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        Variables $variables,
        Arrays $arrays,
        ItemRepository $itemRepository,
        Data $catalogHelper,
        Instances $instanceHelper,
        ManagerInterface $eventManager
    ) {
        $this->variables = $variables;
        $this->arrays = $arrays;
        $this->itemRepository = $itemRepository;
        $this->catalogHelper = $catalogHelper;
        $this->instanceHelper = $instanceHelper;
        $this->eventManager = $eventManager;
    }

    /**
     * @throws \Exception
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');

        if ($order instanceof Order) {
            foreach ($order->getItems() as $item) {
                $this->addPriceToOrderItem($item);
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    protected function addPriceToOrderItem(Item $item): void
    {
        $product = $item->getProduct();

        $itemProductOptions = $item->getProductOptions();

        foreach ($this->arrays->getValue(
            $itemProductOptions,
            'options',
            []
        ) as $itemProductOptionKey => $itemProductOption) {
            $itemProductOptionId = $this->arrays->getValue(
                $itemProductOption,
                'option_id'
            );

            /** @var Option $productOption */
            foreach ($product->getProductOptionsCollection() as $productOption) {
                if ($productOption->getId() == $itemProductOptionId) {
                    $productOption->setData(
                        'item',
                        $item
                    );

                    $finalPrice = $product->getFinalPrice();

                    $group = $productOption->groupFactory($productOption->getType());

                    $group->setOption($productOption);
                    $group->setData(
                        'configuration_item',
                        $item
                    );
                    $group->setData(
                        'configuration_item_option',
                        $productOption
                    );

                    $customOption = $this->instanceHelper->getInstance(
                        \Infrangible\CatalogProductOptionPrice\Model\Order\Item\Option::class,
                        ['data' => $itemProductOption]
                    );

                    $optionPrice = $group->getOptionPrice(
                        $itemProductOption[ 'option_value' ],
                        $finalPrice
                    );

                    $transportObject = new DataObject(
                        [
                            'product'        => $product,
                            'final_price'    => $finalPrice,
                            'qty'            => $item->getQtyOrdered(),
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
                        $product,
                        $optionPrice,
                        true
                    );

                    if ($item->getDiscountAmount()) {
                        $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'original_price' ] = $optionPrice;

                        $productOptionDiscount = round(
                            $optionPrice * $item->getDiscountAmount() / $item->getRowTotalInclTax(),
                            2
                        );

                        $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'discount' ] =
                            $productOptionDiscount;

                        $optionPrice -= $productOptionDiscount;
                    }

                    $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'price' ] = $optionPrice;
                }
            }
        }

        $item->setProductOptions($itemProductOptions);
    }
}
