<?php
declare(strict_types=1);
namespace Lumina\Payment;

final class FedaPayClient
{
    private const SANDBOX_BASE_URL = 'https://sandbox-api.fedapay.com/v1';
    private const LIVE_BASE_URL = 'https://api.fedapay.com/v1';

    private readonly string $environment;
    private readonly string $apiKey;

    /** @param array{api_key:string,environment:string,timeout?:int} $config */
    public function __construct(private readonly array $config)
    {
        if (!function_exists('curl_init')) {
            throw new FedaPayException('L’extension cURL est requise pour FedaPay.');
        }
        $environment = strtolower(trim((string) ($config['environment'] ?? '')));
        if (!in_array($environment, ['sandbox', 'live'], true)) {
            throw new FedaPayException('Environnement FedaPay invalide (sandbox ou live attendu).');
        }
        // Les clés FedaPay commencent par sk_sandbox_ ou sk_live_ : une clé d'un autre environnement
        // (ou un placeholder copié) est refusée avant tout appel réseau.
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $expectedPrefix = $environment === 'live' ? 'sk_live_' : 'sk_sandbox_';
        if (!str_starts_with($apiKey, $expectedPrefix) || strlen($apiKey) <= strlen($expectedPrefix)) {
            throw new FedaPayException('Clé secrète FedaPay absente ou incompatible avec l’environnement ' . $environment . '.');
        }
        $this->environment = $environment;
        $this->apiKey = $apiKey;
    }

    /** @return array{transaction_id:string,payment_url:string} */
    public function createTransactionAndToken(
        string $description,
        int $amountXof,
        string $email,
        string $customerName,
        string $callbackUrl,
        string $merchantReference,
    ): array {
        if ($amountXof < 1) {
            throw new FedaPayException('Montant FedaPay invalide.');
        }
        $name = trim($customerName);
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $transaction = $this->request('POST', '/transactions', [
            'description' => $description,
            'amount' => $amountXof,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => $callbackUrl,
            'merchant_reference' => $merchantReference,
            'customer' => [
                'firstname' => $parts[0] ?? $name,
                'lastname' => $parts[1] ?? '',
                'email' => $email,
            ],
        ]);
        $transactionId = $this->extractString($transaction, [
            ['v1/transaction', 'id'],
            ['transaction', 'id'],
            ['id'],
        ]);
        if ($transactionId === null) {
            throw new FedaPayException('Réponse FedaPay sans identifiant de transaction.');
        }
        $tokenResponse = $this->request('POST', '/transactions/' . rawurlencode($transactionId) . '/token');
        $paymentUrl = $this->extractString($tokenResponse, [
            ['url'],
            ['token', 'url'],
            ['v1/token', 'url'],
        ]);
        if ($paymentUrl === null || !str_starts_with($paymentUrl, 'https://') || !filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            throw new FedaPayException('Réponse FedaPay sans URL de paiement HTTPS valide.');
        }
        // La redirection ne doit jamais sortir des domaines FedaPay.
        $host = parse_url($paymentUrl, PHP_URL_HOST);
        if (!is_string($host) || !str_ends_with(strtolower($host), '.fedapay.com')) {
            throw new FedaPayException('URL de paiement inattendue.');
        }
        return ['transaction_id' => $transactionId, 'payment_url' => $paymentUrl];
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $baseUrl = $this->environment === 'live' ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
        $handle = curl_init($baseUrl . $path);
        if ($handle === false) {
            throw new FedaPayException('Initialisation cURL impossible.');
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 20),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        // Pas de curl_close() : depuis PHP 8.5 l'appel est déprécié et sans effet, la ressource est libérée automatiquement.

        if ($body === false) {
            throw new FedaPayException('Appel FedaPay impossible : ' . $curlError);
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new FedaPayException('Réponse FedaPay invalide (HTTP ' . $status . ').', 0, $exception);
        }
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            // Le message renvoyé par FedaPay est journalisé pour le diagnostic (sans la clé ni le corps complet).
            $detail = is_array($decoded) && is_string($decoded['message'] ?? null)
                ? ' : ' . mb_substr($decoded['message'], 0, 200)
                : '';
            throw new FedaPayException('FedaPay a refusé la requête (HTTP ' . $status . ')' . $detail . '.');
        }
        return $decoded;
    }

    /**
     * Lit une valeur à des chemins explicites, sans recherche récursive :
     * évite de prendre par erreur un « id » d'un objet imbriqué (client, par exemple).
     * @param list<list<string>> $candidates
     */
    private function extractString(array $data, array $candidates): ?string
    {
        foreach ($candidates as $path) {
            $node = $data;
            foreach ($path as $key) {
                if (!is_array($node) || !array_key_exists($key, $node)) {
                    continue 2;
                }
                $node = $node[$key];
            }
            if (is_int($node) && $node > 0) {
                return (string) $node;
            }
            if (is_string($node) && trim($node) !== '') {
                return trim($node);
            }
        }
        return null;
    }
}