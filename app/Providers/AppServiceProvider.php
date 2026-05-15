<?php

namespace App\Providers;

use App\Services\WhatsApp\LogWhatsAppProvider;
use App\Services\WhatsApp\TwilioWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppProviderInterface::class, function () {
            return match (config('services.whatsapp.provider', 'log')) {
                'twilio' => new TwilioWhatsAppProvider(),
                default  => new LogWhatsAppProvider(),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
