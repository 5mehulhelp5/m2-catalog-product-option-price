# Infrangible Catalog Product Option Price

Magento 2 module that calculates the prices of the custom options of products for the cart, the order and the API.
Magento only adds the option prices to the price of a product; this module also keeps them separately, so that other
modules can show them, for example the option prices in the cart.

## Prices

- **Product:** when Magento calculates the final price of a product, the module stores the price of the selected
  options in the data `options_price_attached` (multiplied with the quantity) and `options_price_unattached` (added once
  per row, for example options with an own product), and the final price without options in
  `final_price_without_options`, the base of options with a percent price.
- **Quote item and order item:** the columns `options_price`, `row_options_price` and their variants `base_*` (base
  currency) and `*_incl_tax` hold the option prices per unit and per row, with and without tax. They are copied from
  the quote item to the order item.
- **Order item options:** when an order is placed, each option in the product options of the item gets its `price`
  including tax; for items with a discount also the `original_price` and its share of the `discount`.
- **API:** the order repository adds these prices to the extension attributes `original_price`, `discount` and `price`
  of the custom options of the order items, for example in `GET /V1/orders/:id`.

## Events

| Event                               | Data                                                                                   |
|-------------------------------------|----------------------------------------------------------------------------------------|
| `catalog_product_get_option_price`  | `data`: `product`, `final_price`, `qty`, `product_option`, `custom_option`, `option_price` (changeable) |
| `catalog_product_get_options_price` | `data`: `product`, `qty`, `final_price`, `options_price_attached`, `options_price_unattached` (changeable) |
| `order_item_option_price`           | `data`: `item`, `item_product_option_data`, `custom_option`, `original_price`, `discount`, `price` (changeable) |

For example, [Infrangible Catalog Product Option Product](https://www.infrangible.dev/modules/catalog-product-option-product)
adds the prices of options with an own product as unattached price, and
[Infrangible Bundle Option Qty Price](https://www.infrangible.dev/modules/bundle-option-qty-price) changes option
prices by quantity.

## Requirements

- Magento 2.4.6 to 2.4.9
- PHP 8.2 or newer

## Installation

```bash
composer require infrangible/m2-catalog-product-option-price
bin/magento module:enable Infrangible_CatalogProductOptionPrice
bin/magento setup:upgrade
```

## License

Infrangible Catalog Product Option Price is licensed under the MIT License - see the LICENSE file for details.
