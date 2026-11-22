# Deleted Accounts rows recovered from binlog

**Source:** `binlog.000008` (ROW `Delete_rows`) · **DB:** `accounts_stg` only (not POS)

Machine-readable twin: [`2026-11-21-deleted-accounts-orders.json`](./2026-11-21-deleted-accounts-orders.json)

## orders (deleted 2026-11-21 01:35:03 UTC / 03:35:03 Cairo)

| id | seller_id | store_profile_id | price EGP | card_id | pos_order_id | storefront_order_id | buyer | phone | created (Cairo) |
|---:|---:|---:|---:|---:|---:|---|---|---|---|
| 105024 | null | 17 | 7000.00 | 926 | 68928 | web-1795214805868-c0opkpc | John Miano | `+201119205533` | 2026-11-21 02:55:09 |
| 105067 | 32 | 13 | 50.00 | 927 | null | null | John Miano | `+201119205533` | 2026-11-21 03:57:36 |
| 105072 | 32 | 17 | 50.00 | 928 | null | null | John Miano | `+201220170162` | 2026-11-21 04:04:15 |
| 105084 | 32 | 14 | 0.00 | 929 | null | null | John Miano | `+201119205533` | 2026-11-21 04:16:21 |

## next_load_orders (deleted 2026-11-21 01:24:23 UTC / 03:24:23 Cairo)

| id | product | status | requested_by | card_price USD | storefront_order_id | sale_context | created (Cairo) |
|---:|---|---|---:|---:|---|---|---|
| 323 | PlayStation USA 50 $ | complete | 32 | 46.970 | web-1795210078737-aou6rcy |  | 2026-11-21 01:40:03 |
| 329 | PlayStation USA 100 $ | complete | 32 | 93.930 | web-1795214805868-c0opkpc |  | 2026-11-21 02:55:03 |
| 336 | PlayStation USA 50 $ | complete | 32 | 46.970 | null | {"price": "50", "buyer_name": "John Miano", "buyer_phone": "+201119205533", "card_category_id": "55", "store_profile_id": "13"} | 2026-11-21 03:57:33 |
| 337 | PlayStation USA 250$ | complete | 32 | 234.830 | null | {"price": "50", "buyer_name": "John Miano", "buyer_phone": "+201220170162", "card_category_id": "50", "store_profile_id": "17"} | 2026-11-21 04:04:12 |
| 338 | PlayStation USA 150$ | complete | 32 | 140.900 | null | {"price": "0", "buyer_name": "John Miano", "buyer_phone": "+201119205533", "card_category_id": "46", "store_profile_id": "14"} | 2026-11-21 04:16:18 |

## Links between rows

- Order **105024** ↔ Next Load **329** ↔ `web-1795214805868-c0opkpc` ↔ POS tx **68928** ↔ card **926**
- Order **105067** ↔ Next Load **336** (sale_context phone +201119205533) ↔ card **927** · seller **32**
- Order **105072** ↔ Next Load **337** (sale_context phone +201220170162) ↔ card **928** · seller **32**
- Order **105084** ↔ Next Load **338** (sale_context price 0) ↔ card **929** · seller **32**
- Next Load **323** is same-night Miano-related web order (`web-1795210078737-aou6rcy`); deleted in the same NL wipe.

