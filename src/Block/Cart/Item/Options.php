<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Block\Cart\Item;

use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Options extends Template
{
    /** @var Configuration */
    protected $productConfigurationHelper;

    /** @var Data */
    protected $catalogProductOptionPriceHelper;

    /** @var PriceCurrencyInterface */
    protected $priceCurrency;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        Context $context,
        Configuration $productConfigurationHelper,
        Data $catalogProductOptionPriceHelper,
        PriceCurrencyInterface $priceCurrency,
        ManagerInterface $eventManager,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );

        $this->productConfigurationHelper = $productConfigurationHelper;
        $this->catalogProductOptionPriceHelper = $catalogProductOptionPriceHelper;
        $this->priceCurrency = $priceCurrency;
        $this->eventManager = $eventManager;
    }

    protected function _construct()
    {
        $this->setData(
            'template',
            $this->getTemplateName()
        );

        parent::_construct();
    }

    public function getTemplateName(): string
    {
        return 'Infrangible_CatalogProductOptionPrice::cart/item/options.phtml';
    }

    public function getItem(): AbstractItem
    {
        return $this->getData('item');
    }

    public function getOptionList(): array
    {
        return $this->getCustomOptions($this->getItem());
    }

    public function getCustomOptions(AbstractItem $item): array
    {
        $optionPrices = $this->catalogProductOptionPriceHelper->getItemOptionPrices($item);

        $options = [];

        $itemOptionIds = $item->getOptionByCode('option_ids');

        if ($itemOptionIds && $itemOptionIds->getValue()) {
            $product = $item->getProduct();

            $optionIds = explode(
                ',',
                $itemOptionIds->getValue()
            );

            foreach ($optionIds as $optionId) {
                if (! array_key_exists(
                    $optionId,
                    $optionPrices
                )) {
                    continue;
                }

                $optionPrice = $optionPrices[ $optionId ];

                $display = $optionPrice > 0;

                $transportObject = new DataObject(
                    [
                        'item'      => $item,
                        'option_id' => $optionId,
                        'price'     => $optionPrice,
                        'display'   => $display
                    ]
                );

                $this->eventManager->dispatch(
                    'catalog_product_option_price_item_options',
                    [
                        'data' => $transportObject
                    ]
                );

                $optionPrice = $transportObject->getData('price');
                $display = $transportObject->getData('display');

                if (! $display) {
                    continue;
                }

                $option = $product->getOptionById($optionId);

                if ($option) {
                    $itemOption = $item->getOptionByCode('option_' . $option->getId());

                    try {
                        $group = $option->groupFactory($option->getType());
                    } catch (LocalizedException $exception) {
                        continue;
                    }

                    $group->setOption($option);
                    $group->setData(
                        'configuration_item',
                        $item
                    );
                    $group->setData(
                        'configuration_item_option',
                        $itemOption
                    );

                    $options[] = [
                        'label'       => $option->getTitle(),
                        'value'       => $group->getFormattedOptionValue($itemOption->getValue()),
                        'print_value' => $group->getPrintableOptionValue($itemOption->getValue()),
                        'option_id'   => $option->getId(),
                        'option_type' => $option->getType(),
                        'custom_view' => $group->isCustomizedView(),
                        'price'       => $this->priceCurrency->roundPrice($optionPrice * $item->getQty())
                    ];
                }
            }
        }

        return $options;
    }

    public function getFormatedOptionValue($optionValue): array
    {
        $params = [
            'max_length'   => 55,
            'cut_replacer' => ' <a href="#" class="dots tooltip toggle" onclick="return false">...</a>'
        ];

        return $this->productConfigurationHelper->getFormattedOptionValue(
            $optionValue,
            $params
        );
    }
}
