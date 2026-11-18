<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('plms:whatsapp-setup {tenant : Workspace id or slug} {--qr : Save the QR code to storage/app/whatsapp-qr.html so a phone can be linked}')]
#[Description('Create a workspace\'s own Evolution API instance (if missing) and register the incoming-message webhook. Workspaces can also do this themselves by scanning the QR code on their WhatsApp page.')]
class SetupWhatsApp extends Command
{
    public function handle(WhatsAppService $base): int
    {
        if (! $base->isConfigured()) {
            $this->error('Set EVOLUTION_API_URL, EVOLUTION_API_KEY and EVOLUTION_INSTANCE (the instance name prefix) in .env first.');

            return self::FAILURE;
        }

        $reference = (string) $this->argument('tenant');
        $tenant = Tenant::query()->with('plan')->where(ctype_digit($reference) ? 'id' : 'slug', $reference)->first();

        if ($tenant === null) {
            $this->error("No workspace matches '{$reference}'.");

            return self::FAILURE;
        }

        if (! $tenant->planAllows('whatsapp_chat')) {
            $this->warn("{$tenant->name}'s plan does not include WhatsApp chat; the workspace cannot use it until it is upgraded.");
        }

        $whatsapp = $base->forTenant($tenant);
        $instance = $tenant->whatsappInstance();

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
            $this->warn('Not linked to a phone yet. Run again with --qr, or let the workspace admin scan the QR code on the WhatsApp page.');
        }

        return self::SUCCESS;
    }
}
