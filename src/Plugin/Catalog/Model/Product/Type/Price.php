<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Catalog\Model\Product\Type;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Price
{
    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(ManagerInterface $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    public function afterGetFinalPrice(Product\Type\Price $subject, float $result, ?float $qty, Product $product): float
    {
        if ($qty === null && $product->getCalculatedFinalPrice() !== null) {
            return $result;
        }

        $finalPrice = $subject->getBasePrice(
            $product,
            $qty
        );

        $product->setFinalPrice($finalPrice);

        $this->eventManager->dispatch(
            'catalog_product_get_final_price',
            ['product' => $product, 'qty' => $qty]
        );

        $finalPrice = $product->getData('final_price');

        $optionsPrice = $this->getOptionsPrice(
            $product,
            $finalPrice
        );

        $transportObject = new DataObject(
            [
                'product'       => $product,
                'qty'           => $qty,
                'final_price'   => $finalPrice,
                'options_price' => $optionsPrice
            ]
        );

        $this->eventManager->dispatch(
            'catalog_product_get_options_price',
            [
                'data' => $transportObject
            ]
        );

        $product->setData(
            'options_price',
            $transportObject->getData('options_price')
        );

        return $result;
    }

    protected function getOptionsPrice(Product $product, float $finalPrice): float
    {
        $optionIds = $product->getCustomOption('option_ids');

        $optionsPrice = 0;

        if ($optionIds) {
            foreach (explode(
                ',',
                $optionIds->getValue() ?? ''
            ) as $optionId) {
                $option = $product->getOptionById($optionId);

                if ($option) {
                    $customOption = $product->getCustomOption('option_' . $option->getId());

                    try {
                        $group = $option->groupFactory($option->getType());

                        $group->setOption($option);
                        $group->setData(
                            'configuration_item_option',
                            $customOption
                        );

                        $optionsPrice += $group->getOptionPrice(
                            $customOption->getValue(),
                            $finalPrice
                        );
                    } catch (LocalizedException $exception) {
                    }
                }
            }
        }

        return $optionsPrice;
    }
}
