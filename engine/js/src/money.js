// Formats integer minor units for display. rate converts from shop currency to the shopper's
// presentment currency (Shopify.currency.rate on the storefront).
export function formatMoney(centsValue, currency = 'USD', locale = undefined, rate = 1) {
  const major = (Number(centsValue) || 0) * (Number(rate) || 1) / 100;
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(major);
  } catch (e) {
    return currency + ' ' + major.toFixed(2);
  }
}
