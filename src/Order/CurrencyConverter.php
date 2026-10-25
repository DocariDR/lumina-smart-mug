<?php
declare(strict_types=1);
namespace Lumina\Order;
use InvalidArgumentException;
/**
 * Conversion USD vers XOF, entière, sans flottant, arrondie au supérieur.
 * Chaîne : USD (centimes) -> EUR (taux USD/EUR) -> XOF (taux EUR/XOF).
 */
final class CurrencyConverter
{
    /** Échelle du taux USD/EUR : 920000 = 0,920000. */
    public const RATE_SCALE = 1000000;
    /** Échelle du taux EUR/XOF : 655957 = 655,957. */
    public const EUR_XOF_SCALE = 1000;
    /** 1 EUR = 655,957 XOF (parité fixe de la zone franc CFA). */
    public const EUR_XOF_RATE_SCALED = 655957;
    public function __construct(
        private readonly int $usdToEurRateScaled,
        private readonly string $rateDate,
    ) {
        if ($this->usdToEurRateScaled < 1 || $this->rateDate === '') {
            throw new InvalidArgumentException('Taux de conversion indisponible.');
        }
    }
    /** @return array{total_xof:int, rate_scaled:int, rate_scale:int, eur_xof_rate_scaled:int, eur_xof_scale:int, rate_at:string} */
    public function convertUsdCentsToXof(int $usdCents): array
    {
        if ($usdCents < 1) {
            throw new InvalidArgumentException('Montant USD invalide.');
        }
        // centimes -> dollars (100) ; échelles des deux taux
        $numerator = $usdCents * $this->usdToEurRateScaled * self::EUR_XOF_RATE_SCALED;
        $denominator = 100 * self::RATE_SCALE * self::EUR_XOF_SCALE;
        return [
            'total_xof' => intdiv($numerator + $denominator - 1, $denominator),
            'rate_scaled' => $this->usdToEurRateScaled,
            'rate_scale' => self::RATE_SCALE,
            'eur_xof_rate_scaled' => self::EUR_XOF_RATE_SCALED,
            'eur_xof_scale' => self::EUR_XOF_SCALE,
            'rate_at' => $this->rateDate,
        ];
    }
    public function formattedRate(): string
    {
        return number_format($this->usdToEurRateScaled / self::RATE_SCALE, 6, '.', '');
    }
}