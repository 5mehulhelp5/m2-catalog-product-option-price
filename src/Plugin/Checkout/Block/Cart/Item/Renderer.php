<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Plugin\Checkout\Block\Cart\Item;

use Infrangible\CatalogProductOptionPrice\Block\Cart\Item\Options;
use Infrangible\CatalogProductOptionPrice\Helper\Data;
use Infrangible\Core\Helper\Block;
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

    public function __construct(Data $catalogProductOptionPriceHelper, Block $blockHelper)
    {
        $this->catalogProductOptionPriceHelper = $catalogProductOptionPriceHelper;
        $this->blockHelper = $blockHelper;
    }

    public function afterGetOptionList(\Magento\Checkout\Block\Cart\Item\Renderer $subject, array $result): array
    {
        $item = $subject->getItem();

        $optionPrices = $this->catalogProductOptionPriceHelper->getItemOptionPrices($item);

        foreach ($optionPrices as $optionId => $optionPrice) {
            $optionPrice = $optionPrices[ $optionId ];

            if ($optionPrice < 0.0001) {
                continue;
            }

            foreach ($result as $key => $optionData) {
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
