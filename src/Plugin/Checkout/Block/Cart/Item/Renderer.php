<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Checkout\Block\Cart\Item;

use Infrangible\CatalogProductOptionPrice\Block\Cart\Item\Options;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\Core\Helper\Block;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Renderer
{
    /** @var Data */
    protected $catalogProductOptionPriceHelper;

    /** @var Block */
    protected $blockHelper;

    /** @var ManagerInterface */
    protected $eventManager;

    public function __construct(
        Data $catalogProductOptionPriceHelper,
        Block $blockHelper,
        ManagerInterface $eventManager
    ) {
        $this->catalogProductOptionPriceHelper = $catalogProductOptionPriceHelper;
        $this->blockHelper = $blockHelper;
        $this->eventManager = $eventManager;
    }

    public function afterGetOptionList(\Magento\Checkout\Block\Cart\Item\Renderer $subject, array $result): array
    {
        $item = $subject->getItem();

        $optionPrices = $this->catalogProductOptionPriceHelper->getItemOptionPrices($item);

        foreach ($optionPrices as $optionId => $optionPrice) {
            $optionPrice = $optionPrices[ $optionId ];

            $display = $optionPrice < 0.0001;

            $transportObject = new DataObject(
                [
                    'item'      => $item,
                    'option_id' => $optionId,
                    'price'     => $optionPrice,
                    'display'   => $display
                ]
            );

            $this->eventManager->dispatch(
                'catalog_product_option_price_item_renderer',
                [
                    'data' => $transportObject
                ]
            );

            $display = $transportObject->getData('display');

            if ($display) {
                continue;
            }

            foreach ($result as $key => $optionData) {
                if (! array_key_exists(
                    'option_id',
                    $optionData
                )) {
                    continue;
                }

                if ($optionId == $optionData[ 'option_id' ]) {
                    unset($result[ $key ]);
                }
            }
        }

        return $result;
    }

    public function afterGetActions(
        \Magento\Checkout\Block\Cart\Item\Renderer $subject,
        string $result,
        AbstractItem $item
    ): string {
        return $this->blockHelper->renderChildBlock(
            $subject,
            Options::class,
            ['item' => $item, 'action_html' => $result]
        );
    }
}
