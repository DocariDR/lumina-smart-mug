(function () {
    'use strict';
    var quantity = document.getElementById('quantity');
    var usdTotal = document.getElementById('usd-total');
    var xofTotal = document.getElementById('xof-total');
    if (!quantity || !usdTotal || !xofTotal) {
        return;
    }
    var formatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    function refreshTotals() {
        var count = Number.parseInt(quantity.value, 10) || 1;
        var unitCents = Number.parseInt(usdTotal.dataset.unitCents, 10) || 0;
        var shippingCents = Number.parseInt(usdTotal.dataset.shippingCents, 10) || 0;
        var rateScaled = BigInt(xofTotal.dataset.rateScaled || '0');
        var rateScale = Number.parseInt(xofTotal.dataset.rateScale, 10) || 1000000;
        var eurXof = BigInt(xofTotal.dataset.eurXof || '655957');
        var eurXofScale = Number.parseInt(xofTotal.dataset.eurXofScale, 10) || 1000;
        var usdCents = (count * unitCents) + shippingCents;
        // BigInt : le produit dépasse 2^53 avec 3 mugs, Number serait inexact.
        // Même formule et même arrondi que CurrencyConverter.php.
        var denominator = BigInt(100 * rateScale * eurXofScale);
        var xof = 0n;
        if (rateScaled > 0n) {
            var numerator = BigInt(usdCents) * rateScaled * eurXof;
            xof = (numerator + denominator - 1n) / denominator;
        }
        usdTotal.textContent = (usdCents / 100).toFixed(2);
        xofTotal.textContent = formatter.format(Number(xof));
    }
    quantity.addEventListener('change', refreshTotals);
    refreshTotals();
})();