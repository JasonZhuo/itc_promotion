# Itc_Promotion

Headless promotion list for Magento Open Source. Administrators and an external marketing system share one ordered list of up to five catalog SKUs. The storefront reads that list through an anonymous REST endpoint and receives only products that are enabled, assigned to the current website, and salable.

Supported versions: Magento Open Source 2.4.8-p4, PHP 8.2 or 8.3. This project has MSI enabled (`Magento_InventorySalesApi`).

## Architecture

- `Api/PromotionManagementInterface` is the only business entry point for REST and Admin.
- `Model/Validator/SkuListValidator` rejects a bad payload before any write.
- `Model/Catalog/ExistingSkuChecker` resolves each request SKU to the canonical `catalog_product_entity.sku`. One missing SKU rejects the whole request.
- `Model/PromotionProductRepository` holds `GET_LOCK('itc_promotion_replace')` inside `replaceAll()` and `modify()`. `modify()` reads the latest rows and commits the replacement under that one lock. A nested call on the same instance does not acquire the lock again. Admin add, remove, move, and SKU change use `modify()`. PUT uses `replaceAll()` after validation.
- `Model/Resolver/StorefrontProductResolver` loads store-scoped products, MSI salability, and native prices.
- `Model/Cache/PromotionListCache` stores only the ordered SKU list. Prices and stock are not cached.

```
app/code/Itc/Promotion/
├── Api/
├── Controller/Adminhtml/Promotion/
├── Model/
├── etc/webapi.xml
├── etc/db_schema.xml
└── view/adminhtml/
```

There is no plugin and no observer. Nothing in Magento's lifecycle has to be intercepted.

## Business rules

| Case | Result |
| --- | --- |
| 0 SKUs | Allowed. Clears the list. |
| 1–5 unique existing SKUs | Full replacement, request order kept in `position` starting at 0. |
| More than 5, duplicates, blank SKUs, non-strings, control characters, SKU longer than 64 | Rejected. Previous rows stay. |
| SKU does not exist | Whole request rejected. No partial write. |
| Disabled product | May be stored. Public GET omits it. |
| Not salable, or not assigned to the current website | Public GET omits it. |
| Module disabled | GET returns `{"items":[]}` and does not read the cache or the table. PUT throws `Magento\Framework\Webapi\Exception` with HTTP 503. Admin controllers catch that exception and show a message; they do not return HTTP 503. |
| Admin add, delete, position change, or SKU change | Same replacement service and the same table as the API. A SKU change is one locked write. |

Filtering happens before the five-item response cap. The table itself never stores more than five rows when written through this service.

`itc_promotion_item` has no `store_id` and no `website_id`. The installation keeps one promotion list, and each SKU is stored once. A storefront request still uses the store Magento selected for that call. The stored SKU is returned only when the product is enabled, assigned to that website, and MSI-salable for that website. The same SKU can be returned for one website and omitted for another.

Request SKUs are matched to the catalog without regard to letter case. The stored value is the canonical `catalog_product_entity.sku`. Two request values that resolve to the same catalog SKU are duplicates and are rejected before any write. The database unique key is `ITC_PROMOTION_ITEM_SKU_UNQ`. There is no foreign key to `catalog_product_entity` because SKU is not that table's primary key.

## Install

From the Magento root, on a non-production environment:

```bash
bin/magento module:enable Itc_Promotion
bin/magento setup:upgrade
bin/magento cache:flush
```

`setup:upgrade` creates `itc_promotion_item`. Do not run it against production without the normal deploy approval.

`app/etc/config.php` in this repository already sets `'Itc_Promotion' => 1`.

Disable the feature without removing the module:

Stores > Configuration > ITC > Promotion > Enable Promotion Service = No.

That switch is global (`showInStore` and `showInWebsite` are off).

## Admin

Marketing > ITC > Promotion Products.

- Add Product accepts up to 5 product SKUs separated by commas, and optional positions in the same order, also separated by commas. An empty Position field appends them. One save writes the whole batch.
- Position is edited in the grid. Click the number, type a whole number from 0 through 65535, and click Update. That row keeps the number you entered. The other rows are not renumbered. The list is sorted by those numbers.
- Delete removes one SKU.
- The grid and the API read and write `itc_promotion_item`.
- The grid has no store switcher. It edits the one shared list. Product name, price, special price, and status on the grid are the default-scope values (`store_id` 0). Stock status is the default stock index (`cataloginventory_stock_status`, website 0, stock 1). Those columns are the catalog's global values. They are not a store-view override, and the stock column is not the MSI salability result used by the public GET.

ACL:

- `Itc_Promotion::manage` for the Admin screen.
- `Itc_Promotion::config` for the system configuration.
- `Itc_Promotion::update` for `PUT /V1/promotions/products`.

Admin POSTs use Magento's form key.

## Integration token

System > Extensions > Integrations. Create an integration and grant **Replace Promotion List via API** (`Itc_Promotion::update`). Activate it and use the access token as a Bearer token. Do not commit that token.

Magento accepts that access token as a Bearer token only when **Stores > Configuration > Services > OAuth > Allow OAuth Access Tokens to be used as standalone Bearer tokens** is Yes (`oauth/consumer/enable_integration_as_bearer`).

Anonymous clients cannot call PUT. GET does not require a token.

## API Usage and Examples

`etc/webapi.xml` declares two routes. Both use `Itc\Promotion\Api\PromotionManagementInterface`. Admin add, remove, move, and SKU change call the same service from `Controller/Adminhtml/Promotion`, but they are not REST routes.

Magento selects the store from the REST URL. `/rest/V1/...` uses the default store. `/rest/{storeCode}/V1/...` uses that store view. Name, short description, website, currency, and prices follow that store.

### GET /V1/promotions/products

| | |
| --- | --- |
| Method | `GET` |
| Service | `getStorefrontProducts()` |
| Authentication | Anonymous (`<resource ref="anonymous"/>`) |
| ACL | None |
| Request body | None |

Success is Magento's normal service response, HTTP 200. The body is `StorefrontProductListInterface`: an `items` array of `StorefrontProductInterface`.

```bash
curl -sS "https://your-magento.example/rest/V1/promotions/products"
```

```bash
curl -sS "https://your-magento.example/rest/STORE_CODE/V1/promotions/products"
```

Example shape. The numbers and URL are placeholders, not catalog data.

```json
{
  "items": [
    {
      "sku": "PROMO-001",
      "product_id": 123,
      "name": "Example Product",
      "short_description": "Example description",
      "price": 100,
      "special_price": 80,
      "final_price": 75,
      "currency": "USD",
      "image": "https://your-magento.example/media/catalog/product/example.jpg"
    }
  ]
}
```

An empty list, a disabled module, or a list whose products are all ineligible returns:

```json
{"items":[]}
```

Field sources:

- `sku` is the canonical catalog SKU stored in `itc_promotion_item`.
- `product_id`, `name`, and `short_description` come from the store-scoped product. `short_description` is HTML-stripped. An empty description is `null`.
- `price` is `getPriceInfo()->getPrice('regular_price')->getAmount()->getValue()`.
- `special_price` is the raw `special_price` attribute. It is returned when set, including when from/to dates make it inactive. An empty attribute is `null`. Date activity is reflected by `final_price`.
- `final_price` is `getPriceInfo()->getPrice('final_price')->getAmount()->getValue()` after `addPriceData()` for the not-logged-in customer group and the current website. This module does not calculate discounts itself. Cart price rules and coupons are not applied.
- `currency` is `getCurrentCurrencyCode()` after frontend emulation starts for the request store.
- `image` is `Magento\Catalog\Helper\Image` with image id `product_base_image` when the `image` attribute is a non-empty value other than `no_selection`. An empty image, `no_selection`, an empty helper URL, or a helper exception yields `null`. The product is still returned.

Magento's web API serializer omits a data-object property whose value is `null`. A missing `short_description`, `special_price`, or `image` key means the service value is `null`.

Eligibility, in stored order, then capped at five (`StorefrontEligibility::MAX_RESULTS`):

- The product must load for the store.
- Status must be enabled.
- `website_ids` must include the current store's website.
- MSI salability must be true: `StockResolverInterface` for the website sales channel, then `AreProductsSalableInterface` for that stock id. Status or quantity alone is not treated as salable.

Ineligible stored SKUs are skipped. They are not replaced by products outside the stored list. Configurable, bundle, and grouped products use the same native price models and are not expanded into child matrices.

Customer-group limitation: before prices are read, the customer session group is set to the not-logged-in group and restored afterward. Catalog price rules for other customer groups are not selected. Currency limitation: the amount is whatever Magento's price model returns for the emulated store currency. This module does not define currency rates. A salability or catalog exception is not caught here; Magento converts it with its normal web API error handling.

### PUT /V1/promotions/products

| | |
| --- | --- |
| Method | `PUT` |
| Service | `replaceBySkus(string[] $skus)` |
| Authentication | Integration access token used as a Bearer token |
| ACL | `Itc_Promotion::update` (Replace Promotion List via API) |
| Request body | `{"skus":["SKU-1","SKU-2"]}` |

Bearer use also requires Magento's OAuth setting `oauth/consumer/enable_integration_as_bearer`. This module does not issue tokens.

```bash
curl -sS -X PUT "https://your-magento.example/rest/V1/promotions/products" \
  -H "Authorization: Bearer YOUR_INTEGRATION_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"skus":["PROMO-001","PROMO-002"]}'
```

Clear the list:

```bash
curl -sS -X PUT "https://your-magento.example/rest/V1/promotions/products" \
  -H "Authorization: Bearer YOUR_INTEGRATION_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"skus":[]}'
```

Success is HTTP 200. The body is `ReplaceResultInterface`: the canonical SKUs that were stored, and `count`.

```json
{"skus":["PROMO-001","PROMO-002"],"count":2}
```

```json
{"skus":[],"count":0}
```

PUT replaces the whole table. It does not append. Validation runs before the named lock and before the transaction:

1. `SkuListValidator` allows 0 through 5 values. More than five, a non-string, a blank value, a value longer than 64 characters, an ASCII control character, or an exact duplicate throws `InputException`.
2. `ExistingSkuChecker` loads `catalog_product_entity.sku` with `LOWER(sku) IN (?)` and the request SKUs folded by `mb_strtolower(..., 'UTF-8')`. It returns the stored catalog SKU. One missing SKU rejects the whole request. The SQL predicate does not rely on the column collation being case-insensitive. `LOWER()` and `mb_strtolower` can still differ for some non-ASCII characters.
3. The validator runs again so two request values that fold to one catalog SKU are rejected as duplicates.

`replaceAll()` then acquires `GET_LOCK('itc_promotion_replace', 10)`, deletes every `itc_promotion_item` row, and inserts the new rows in one transaction. `position` is the zero-based request order. Commit is followed by one `RELEASE_LOCK`. The SKU-list cache tag is cleaned only after that method returns. A validation failure does not lock, write, or clean the cache.

A failed insert rolls the transaction back and throws `CouldNotSaveException` with `Could not save the promotion list.` The named lock is still released. If the lock is not acquired, the transaction does not start, `RELEASE_LOCK` is not called, and the exception message is `Could not lock the promotion list for update.`

Applicable errors, from this module's exceptions and `Magento\Framework\Webapi\ErrorProcessor`:

| Condition | Exception | HTTP status |
| --- | --- | --- |
| No credentials, or the integration lacks `Itc_Promotion::update` | Magento `AuthorizationException` or `AuthenticationException` | 401 |
| Malformed JSON or a missing `Content-Type` | Magento `InputException` | 400 |
| More than five SKUs, duplicate, malformed SKU, or unknown SKU | `InputException` | 400 |
| Module disabled | `Magento\Framework\Webapi\Exception` with code 503 | 503 |
| Lock timeout or a failed save | `CouldNotSaveException`, which is a `LocalizedException` | 400 |

`CouldNotSaveException` is not given its own HTTP code in this module. Magento's `ErrorProcessor` maps `LocalizedException` other than not-found and authorization failures to HTTP 400. The save failure is logged without the bearer token. The client message is the localized exception text, not a stack trace.

`GET` and `PUT` are the only methods in `webapi.xml`. This module does not define a status code for any other method.

## Cache

`PromotionListCache` stores only the ordered SKU array.

| | |
| --- | --- |
| Cache id | `itc_promotion_sku_list` |
| Tag | `itc_promotion_list` |
| Lifetime | 86400 seconds |
| Value | JSON array of SKU strings |

GET uses the cache when the payload is a JSON array of strings. Otherwise it reads `itc_promotion_item` ordered by `position` and saves that list. Prices, images, and salability are resolved on every GET and are not cached.

`replaceBySkus()`, `addProduct()`, `removeProduct()`, `moveProduct()`, and `changeProduct()` call `invalidate()` only after the repository returns. That cleans tag `itc_promotion_list`. A thrown validation or save error does not clean it.

## Performance Considerations

The public list is at most five SKUs, so the cached value is small. Each GET still loads those products, calls MSI salability once for that SKU list, and reads native price info for the current store. Website assignment is loaded with the product collection through `addWebsiteNamesToResult()`, so it is one catalog-website query for the loaded products. Those costs are outside the SKU-list cache. Magento's `AreProductsSalable` implementation checks each SKU it is given.

Writes take one named lock for up to 10 seconds, then one transaction that deletes and reinserts the whole table. Admin changes use `modify()`, which reads the latest rows and writes them under that same lock. A nested call on the same repository instance does not call `GET_LOCK` again. A caller that did not get the lock does not call `RELEASE_LOCK`.

No latency, throughput, or lock-wait measurement is recorded here.

| Check | Result |
| --- | --- |
| Anonymous GET latency | Not measured |
| ACL-protected PUT latency | Not measured |
| Cache hit versus table read | Not measured |
| Price-info and MSI salability cost per GET | Not measured |
| Two MySQL connections contending for `GET_LOCK('itc_promotion_replace', 10)` | Not verified |
| Overlapping Admin adds against a live database | Not verified |

Unit tests under `Test/Unit/Model/PromotionProductRepositoryTest.php` cover lock acquisition, rollback, and a second repository instance on a connection double. They do not open two MySQL connections.

## Tests

From the Magento root:

```bash
find app/code/Itc/Promotion -type f -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit -c app/code/Itc/Promotion/phpunit.xml.dist
vendor/bin/phpcs --standard=Magento2 app/code/Itc/Promotion
```

The PHPUnit suite is the module unit suite in `Test/Unit`. It does not start Magento and does not call HTTP. `dev/tests/api-functional` is not part of this module and is not configured here. This document does not record command results.

## Security

- PUT is not anonymous. GET is.
- Authorization is Magento integration OAuth plus `Itc_Promotion::update`. This module does not invent tokens or store credentials.
- Admin routes use `Itc_Promotion::manage` and Magento CSRF form keys.
- Client errors do not return SQL or stack traces.

## Known limits

- The public response is at most five eligible products. Ineligible stored SKUs are skipped.
- Image URLs depend on the frontend view config for `product_base_image`.
- Guest catalog prices are used. Logged-in customer-group catalog rules are not selected.
- Case-insensitive SKU resolution uses `LOWER(sku)` together with `mb_strtolower(..., 'UTF-8')`. Those two folds can differ for some non-ASCII characters.

## Operation guides

Admin and API steps, with the screenshots embedded in each document:

- [Function Traning Guid Chinese.docx](Function%20Traning%20Guid%20Chinese.docx)
- [Function Traning Guid English.docx](Function%20Traning%20Guid%20English.docx)
