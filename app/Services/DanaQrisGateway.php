<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DanaQrisGateway
{
    private const PATH = '/v1.0/qr/qr-mpm-generate.htm';

    public function configured(): bool
    {
        return collect([
            config('services.dana.partner_id'),
            config('services.dana.merchant_id'),
            config('services.dana.store_id'),
            config('services.dana.private_key'),
        ])->every(fn ($value) => filled($value));
    }

    /** @return array{reference: ?string, payload: ?string, image: ?string, expires_at: CarbonInterface} */
    public function generate(Order $order, ?string $clientIp): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Konfigurasi DANA QRIS belum lengkap.');
        }

        $timestamp = now('Asia/Jakarta')->format('Y-m-d\TH:i:sP');
        $expiresAt = now('Asia/Jakarta')->addMinutes((int) config('services.dana.qris_expiry_minutes', 15));
        $body = [
            'merchantId' => config('services.dana.merchant_id'),
            'storeId' => config('services.dana.store_id'),
            'partnerReferenceNo' => substr($order->order_number, 0, 25),
            'amount' => ['value' => number_format((float) $order->total, 2, '.', ''), 'currency' => 'IDR'],
            'validityPeriod' => $expiresAt->format('Y-m-d\TH:i:sP'),
            'additionalInfo' => [
                'terminalSource' => 'MER',
                'envInfo' => [
                    'sourcePlatform' => 'IPG',
                    'orderTerminalType' => 'WEB',
                    'terminalType' => 'WEB',
                    'clientIp' => $clientIp,
                    'websiteLanguage' => 'id_ID',
                ],
            ],
        ];

        if (filled(config('services.dana.sub_merchant_id'))) {
            $body['subMerchantId'] = config('services.dana.sub_merchant_id');
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $externalId = now()->format('ymdHis').random_int(1000, 9999);
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(10)
            ->withHeaders([
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->signature('POST', self::PATH, $json, $timestamp),
                'X-PARTNER-ID' => config('services.dana.partner_id'),
                'X-EXTERNAL-ID' => $externalId,
                'CHANNEL-ID' => config('services.dana.channel_id', '95221'),
                'ORIGIN' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ])
            ->withBody($json, 'application/json')
            ->post(rtrim((string) config('services.dana.base_url'), '/').self::PATH)
            ->throw()
            ->json();

        if (! str_starts_with((string) ($response['responseCode'] ?? ''), '200')) {
            throw new RuntimeException((string) ($response['responseMessage'] ?? 'DANA menolak pembuatan QRIS.'));
        }

        return [
            'reference' => is_string($response['referenceNo'] ?? null) ? $response['referenceNo'] : null,
            'payload' => is_string($response['qrContent'] ?? null) ? $response['qrContent'] : null,
            'image' => $this->normalizeImage($response['qrImage'] ?? null, $response['qrUrl'] ?? null),
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyNotification(string $rawBody, string $timestamp, string $signature): bool
    {
        $publicKey = $this->keyContents((string) config('services.dana.public_key'));
        if ($publicKey === '') {
            return false;
        }

        $stringToVerify = 'POST:/payments/dana/notify:'.strtolower(hash('sha256', $rawBody)).':'.$timestamp;
        $key = openssl_pkey_get_public($publicKey);

        return $key !== false
            && openssl_verify($stringToVerify, base64_decode($signature, true) ?: '', $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function signature(string $method, string $path, string $body, string $timestamp): string
    {
        $stringToSign = $method.':'.$path.':'.strtolower(hash('sha256', $body)).':'.$timestamp;
        $privateKey = openssl_pkey_get_private($this->keyContents((string) config('services.dana.private_key')));
        if ($privateKey === false || ! openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Private key DANA tidak valid atau tidak dapat dibaca.');
        }

        return base64_encode($signature);
    }

    private function keyContents(string $configured): string
    {
        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);

        return is_file($path) ? (string) file_get_contents($path) : str_replace('\\n', "\n", $configured);
    }

    private function normalizeImage(mixed $image, mixed $url): ?string
    {
        if (is_string($image) && $image !== '') {
            return str_starts_with($image, 'data:') ? $image : 'data:image/png;base64,'.$image;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }
}
