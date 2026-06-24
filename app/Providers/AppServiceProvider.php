<?php

namespace App\Providers;

use App\Services\WhatsApp\LogWhatsAppProvider;
use App\Services\WhatsApp\TwilioWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
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
        // Multiidioma: español, inglés y portugués (Brasil).
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['es', 'en', 'pt_BR'])
                ->labels([
                    'es'    => 'Español',
                    'en'    => 'English',
                    'pt_BR' => 'Português (BR)',
                ])
                ->visible(insidePanels: true, outsidePanels: true);
        });
    }
}
