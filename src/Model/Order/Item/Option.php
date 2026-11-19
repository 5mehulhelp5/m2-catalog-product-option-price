<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionPrice\Model\Order\Item;

use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Framework\DataObject;

/**
 * An option of an order item (from its product options) as configuration item option, for the observers of the
 * event "catalog_product_get_option_price" when the order is placed.
 */
class Option extends DataObject implements OptionInterface
{
    /**
     * Returns the value of the option.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->getData('option_value');
    }
}
