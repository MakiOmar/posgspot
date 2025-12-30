# ⚡ Quick Setup: Add Button to WooCommerce

## 5-Minute Setup

### Step 1️⃣: Get Your Values

| What | Where to Find | Example |
|------|---------------|---------|
| **Business ID** | POS Admin → URL when editing business | `1` |
| **Bearer Token** | POS Database → `businesses.woocommerce_wh_ou_secret` | `>u!iXA@Gss~=` |
| **POS URL** | Your POS site address | `https://pos.yoursite.com` |

### Step 2️⃣: Copy the Code

Open: `COPY_PASTE_READY.php`

### Step 3️⃣: Update 3 Lines

```php
define('POS_BUSINESS_ID', 1);                             // ← Your business ID
define('POS_API_URL', 'https://pos.yoursite.com');       // ← Your POS URL
define('POS_BEARER_TOKEN', 'paste_your_token_here');     // ← Your Bearer token
```

### Step 4️⃣: Paste to functions.php

WordPress Admin → Appearance → Theme File Editor → `functions.php`

Paste at the bottom of the file.

### Step 5️⃣: Test It

1. Go to: **WooCommerce → Orders**
2. Open any order
3. Find: **Order actions** dropdown (right sidebar)
4. Select: **"Update POS Custom Meta"**
5. Click: **Update** button
6. Check: Order notes for ✅ or ❌ message

---

## 🎯 What It Does

```
WooCommerce Order → Click Button → POS API → Updates staff_note
```

Extracts from WooCommerce:
- ✅ Game Title
- ✅ Type
- ✅ Account
- ✅ Password

Updates in POS:
- ✅ `transactions.staff_note` field

---

## 📱 Success Message

```
✅ POS Custom Meta Updated Successfully
Invoice: POS-0001
Order ID: 5525
```

---

## 🚨 Common Errors

| Error | Fix |
|-------|-----|
| `Unauthorized: Invalid or missing Bearer token` | Wrong token or format. Use `Authorization: Bearer token` |
| `Order not found in POS` | Order not synced yet, run sync first |
| `Connection timeout` | Wrong POS URL or server down |

---

## 📚 Need More Help?

- **Full Guide:** `WOOCOMMERCE_BUTTON_GUIDE.md`
- **API Docs:** `UPDATE_CUSTOM_META_API.md`
- **Quick Start:** `QUICK_START_UPDATE_META.md`

---

## ✅ Checklist

- [ ] Got Business ID
- [ ] Got Bearer Token  
- [ ] Got POS URL
- [ ] Copied code from `COPY_PASTE_READY.php`
- [ ] Updated 3 configuration lines
- [ ] Pasted to `functions.php`
- [ ] Saved file
- [ ] Tested on one order
- [ ] It works! 🎉

