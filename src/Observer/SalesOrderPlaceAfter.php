<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Observer;

use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Magento\Catalog\Model\Product\Option;
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

    public function __construct(Variables $variables, Arrays $arrays, ItemRepository $itemRepository)
    {
        $this->variables = $variables;
        $this->arrays = $arrays;
        $this->itemRepository = $itemRepository;
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

                    $productOptionPrice = $group->getOptionPrice(
                        $itemProductOption[ 'option_value' ],
                        $finalPrice
                    );

                    if ($item->getDiscountAmount()) {
                        $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'original_price' ] =
                            $productOptionPrice;

                        $productOptionDiscount = round(
                            $productOptionPrice * $item->getDiscountAmount() / $item->getPrice(),
                            2
                        );

                        $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'discount' ] =
                            $productOptionDiscount;

                        $productOptionPrice -= $productOptionDiscount;
                    }

                    $itemProductOptions[ 'options' ][ $itemProductOptionKey ][ 'price' ] = $productOptionPrice;
                }
            }
        }

        $item->setProductOptions($itemProductOptions);
    }
}
