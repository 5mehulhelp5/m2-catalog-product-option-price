<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Catalog\Model\Product\Type;

use FeWeDev\Base\Variables;
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

    /** @var Variables */
    protected $variables;

    public function __construct(ManagerInterface $eventManager, Variables $variables)
    {
        $this->eventManager = $eventManager;
        $this->variables = $variables;
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
            floatval($finalPrice),
            $qty
        );

        $transportObject = new DataObject(
            [
                'product'                  => $product,
                'qty'                      => $qty,
                'final_price'              => $finalPrice,
                'options_price_attached'   => $optionsPrice,
                'options_price_unattached' => 0
            ]
        );

        $this->eventManager->dispatch(
            'catalog_product_get_options_price',
            [
                'data' => $transportObject
            ]
        );

        $product->setData(
            'options_price_attached',
            $transportObject->getData('options_price_attached')
        );

        $product->setData(
            'options_price_unattached',
            $transportObject->getData('options_price_unattached')
        );

        return $result;
    }

    protected function getOptionsPrice(Product $product, float $finalPrice, ?float $qty): float
    {
        $optionIdsOption = $product->getCustomOption('option_ids');

        $optionsPrice = 0;

        if ($optionIdsOption) {
            $optionIdsOptionValue = $optionIdsOption->getValue();

            if (! $this->variables->isEmpty($optionIdsOptionValue)) {
                $optionIds = explode(
                    ',',
                    $optionIdsOptionValue
                );

                foreach ($optionIds as $optionId) {
                    $productOption = $product->getOptionById($optionId);

                    if ($productOption) {
                        $customOption = $product->getCustomOption('option_' . $productOption->getId());

                        try {
                            $group = $productOption->groupFactory($productOption->getType());

                            $group->setOption($productOption);
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

                            $optionsPrice += $optionPrice;
                        } catch (LocalizedException $exception) {
                        }
                    }
                }
            }
        }

        return $optionsPrice;
    }
}
