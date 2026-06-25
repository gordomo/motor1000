<?php

namespace App\Providers;

use App\Services\WhatsApp\LogWhatsAppProvider;
use App\Services\WhatsApp\TwilioWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // Si APP_URL es https, forzar generación de URLs https (assets, links).
        // Respaldo del trustProxies: evita el "Mixed Content" detrás del proxy SSL
        // aunque el proxy no reenvíe X-Forwarded-Proto.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Anti-spam del turnero público: límite por IP.
        RateLimiter::for('public-booking', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

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
