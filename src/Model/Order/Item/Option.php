<?php

declare(strict_types=1);

namespace Infrangible\CatalogProductOptionPrice\Model\Order\Item;

use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Framework\DataObject;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2025 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Option extends DataObject implements OptionInterface
{
    public function getValue()
    {
        return $this->getData('option_value');
    }
}
