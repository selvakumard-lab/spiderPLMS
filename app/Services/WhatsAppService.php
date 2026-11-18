<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * WhatsApp through a self-hosted Evolution API server: send text, check the connection,
 * register the webhook and parse incoming message events.
 */
class WhatsAppService
{
    public function isConfigured(): bool
    {
        return filled(config('services.evolution.url')) && filled(config('services.evolution.key')) && filled(config('services.evolution.instance'));
    }

    /**
     * Send a text message to a number (digits with country code).
     *
     * @return string The WhatsApp message id.
     */
    public function sendText(string $to, string $body): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp is not configured. Add the Evolution API settings to the server settings.');
        }

        try {
            $response = $this->http()->post('/message/sendText/'.$this->instance(), [
                'number' => preg_replace('/\D/', '', $to),
                'text' => $body,
            ])->throw();
        } catch (RequestException $e) {
            throw new RuntimeException($this->errorText($e), previous: $e);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not reach the WhatsApp server. Try again shortly.', previous: $e);
        }

        return (string) ($response->json('key.id') ?? '');
    }

    /** Connection state of the instance: open (linked to a phone), connecting, close, or unknown. */
    public function connectionState(): string
    {
        if (! $this->isConfigured()) {
            return 'unconfigured';
        }

        try {
            return (string) ($this->http()->timeout(8)->get('/instance/connectionState/'.$this->instance())->json('instance.state') ?? 'unknown');
        } catch (Throwable) {
            return 'unreachable';
        }
    }

    /** Whether the instance exists on the Evolution server. */
    public function instanceExists(): bool
    {
        $response = $this->http()->get('/instance/fetchInstances', ['instanceName' => $this->instance()]);

        return $response->successful() && collect((array) $response->json())->contains(fn ($i) => ($i['name'] ?? $i['instance']['instanceName'] ?? null) === $this->instance());
    }

    /** Creates this app's own instance. Other instances on the server are never touched. */
    public function createInstance(): void
    {
        $this->http()->post('/instance/create', [
            'instanceName' => $this->instance(),
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ])->throw();
    }

    /** Points this instance's incoming-message events at the app. */
    public function registerWebhook(string $url): void
    {
        $this->http()->post('/webhook/set/'.$this->instance(), [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'byEvents' => false,
                'base64' => false,
                'events' => ['MESSAGES_UPSERT'],
            ],
        ])->throw();
    }

    /** The QR code (base64 image) to link a phone, or null when already linked. */
    public function qrCode(): ?string
    {
        return $this->http()->get('/instance/connect/'.$this->instance())->json('base64');
    }

    /** The address Evolution must call. The secret token proves a delivery came from our Evolution server. */
    public function webhookUrl(): string
    {
        $base = (string) config('services.evolution.webhook_url') ?: url('/api/v1/webhooks/whatsapp');

        return $base.(str_contains($base, '?') ? '&' : '?').'token='.config('services.evolution.webhook_token');
    }

    public function isValidToken(?string $token): bool
    {
        $secret = (string) config('services.evolution.webhook_token');

        return $secret !== '' && $token !== null && hash_equals($secret, $token);
    }

    /**
     * Flatten a webhook body into its incoming one-to-one text messages. Groups and our own messages are ignored.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{id: string, from: string, name: string|null, text: string}>
     */
    public function incomingMessages(array $payload): array
    {
        $event = strtolower(str_replace('_', '.', (string) ($payload['event'] ?? '')));

        if ($event !== 'messages.upsert' || ($payload['instance'] ?? $this->instance()) !== $this->instance()) {
            return [];
        }

        $data = (array) ($payload['data'] ?? []);
        $items = isset($data['key']) ? [$data] : (array) ($data['messages'] ?? (array_is_list($data) ? $data : []));
        $found = [];

        foreach ($items as $item) {
            $key = (array) ($item['key'] ?? []);
            $jid = (string) ($key['remoteJid'] ?? '');
            $text = $item['message']['conversation'] ?? $item['message']['extendedTextMessage']['text'] ?? null;

            if (($key['fromMe'] ?? false) || str_ends_with($jid, '@g.us') || blank($text) || blank($key['id'] ?? null)) {
                continue;
            }

            // Newer WhatsApp versions hide the number behind an @lid id and put the real one beside it.
            $number = (string) ($key['senderPn'] ?? $key['remoteJidAlt'] ?? $jid);
            $from = preg_replace('/\D/', '', explode('@', $number)[0]);

            if ($from === '') {
                continue;
            }

            $found[] = ['id' => (string) $key['id'], 'from' => $from, 'name' => $item['pushName'] ?? null, 'text' => (string) $text];
        }

        return $found;
    }

    private function instance(): string
    {
        return (string) config('services.evolution.instance');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.evolution.url'), '/'))
            ->withHeaders(['apikey' => (string) config('services.evolution.key')])
            ->acceptJson()->timeout(20);
    }

    private function errorText(RequestException $e): string
    {
        $message = $e->response->json('response.message.0') ?? $e->response->json('message');
        $message = is_array($message) ? json_encode($message) : (string) $message;

        if (str_contains(strtolower($message), 'exists') || str_contains($message, '"exists":false')) {
            return 'That number is not on WhatsApp. Check the client\'s WhatsApp number.';
        }

        return $message !== '' ? 'WhatsApp could not send it: '.$message : 'WhatsApp rejected the message.';
    }
}
