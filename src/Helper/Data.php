<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Helper;

use FeWeDev\Base\Variables;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item\AbstractItem;

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

    public function __construct(
        \Magento\Catalog\Helper\Data $catalogHelper,
        Variables $variables,
        ManagerInterface $eventManager
    ) {
        $this->catalogHelper = $catalogHelper;
        $this->variables = $variables;
        $this->eventManager = $eventManager;
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
}
