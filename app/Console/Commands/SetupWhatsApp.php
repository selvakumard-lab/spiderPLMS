<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('plms:whatsapp-setup {--qr : Save the QR code to storage/app/whatsapp-qr.html so a phone can be linked}')]
#[Description('Create this app\'s Evolution API instance (if missing) and register the incoming-message webhook')]
class SetupWhatsApp extends Command
{
    public function handle(WhatsAppService $whatsapp): int
    {
        if (! $whatsapp->isConfigured()) {
            $this->error('Set EVOLUTION_API_URL, EVOLUTION_API_KEY and EVOLUTION_INSTANCE in .env first.');

            return self::FAILURE;
        }

        $instance = config('services.evolution.instance');

        try {
            if ($whatsapp->instanceExists()) {
                $this->info("Instance '{$instance}' already exists.");
            } else {
                $whatsapp->createInstance();
                $this->info("Created instance '{$instance}'.");
            }

            $whatsapp->registerWebhook($whatsapp->webhookUrl());
            $this->info('Webhook registered: '.preg_replace('/token=.*/', 'token=***', $whatsapp->webhookUrl()));
        } catch (Throwable $e) {
            $this->error('Evolution API said: '.$e->getMessage());

            return self::FAILURE;
        }

        $state = $whatsapp->connectionState();
        $this->line("Connection: {$state}");

        if ($state !== 'open' && $this->option('qr') && ($qr = $whatsapp->qrCode())) {
            $path = storage_path('app/whatsapp-qr.html');
            file_put_contents($path, '<img alt="Scan with WhatsApp" src="'.e($qr).'" style="width:320px">');
            $this->info("Open {$path} and scan it: WhatsApp > Linked devices > Link a device.");
        } elseif ($state !== 'open') {
            $this->warn('Not linked to a phone yet. Run again with --qr, or open the Evolution manager and scan the QR code.');
        }

        return self::SUCCESS;
    }
}
