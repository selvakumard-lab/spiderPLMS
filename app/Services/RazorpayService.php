<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Razorpay Orders API client (create an order, verify the checkout signature).
 */
class RazorpayService
{
    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    /**
     * @param  array<string, string>  $notes
     * @return array{id: string, amount: int, currency: string}
     */
    public function createOrder(int $amountMinor, string $currency, string $receipt, array $notes = []): array
    {
        try {
            $response = Http::withBasicAuth($this->keyId(), (string) config('services.razorpay.key_secret'))
                ->acceptJson()->timeout(20)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountMinor,
                    'currency' => $currency,
                    'receipt' => substr($receipt, 0, 40),
                    'notes' => $notes,
                ])->throw();
        } catch (RequestException $e) {
            $message = $e->response->json('error.description') ?? 'Razorpay rejected the order.';
            throw new RuntimeException($message, previous: $e);
        }

        return $response->json();
    }

    public function isValidSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }
}
