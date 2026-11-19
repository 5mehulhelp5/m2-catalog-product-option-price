# Changelog

## 3.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.2 or newer. Older versions of Magento and PHP 7.1 to 8.1 are no longer
  supported; use version 2.x for these.
- Requires `infrangible/m2-foundation` 3.0, `fewedev/base` 2.4 and the Magento modules it uses; before, the module
  used fewedev/base and m2-core without requiring them. It no longer uses m2-core.
- After the final price of a product is calculated, the data `final_price` of the product is the final price of Magento
  (with options) again; the final price without options, the base of percent option prices, is stored in the data
  `final_price_without_options`.
- All classes have native types, and the constructors use constructor property promotion; the observer and the
  plugins get other constructor arguments.
- The plugin of `ToOrderItem` is an after plugin instead of an around plugin.

### Fixed

- The option prices of quote items (`Helper\Data::getItemOptionPrices()`, used for example for the cart) calculate
  options with a percent price from the final price without options; before, they used the final price including the
  options, so a 10 % option of a product for 100 showed 12 instead of 10 when the options together cost 20.
- An item with a discount and a row total of 0 no longer fails with a division by zero when the order is placed.
- Options that the product no longer has, missing custom options of quote items and products without options no
  longer fail with an error.
- Orders without shipping assignments and order items without custom options no longer cause warnings in the order
  repository.
- When the order is placed, the option groups get the option of the order item as configuration item option, as
  Magento expects; before, they got the product option.
- A price of 0 (for example after a full discount) is added to the extension attributes of the order item options.
- The option prices of the product are loaded once per order item instead of once per option.

### Changed

- The columns of the option prices in `quote_item` and `sales_order_item` are created by declarative schema.
- All option prices of quote items are rounded with Magento's `PriceCurrency::roundPrice()`; before, some prices
  without tax in the base currency were not, and the rounding used a method that only the implementation of the
  price currency interface has.

### Added

- Unit tests, a README and this changelog.
