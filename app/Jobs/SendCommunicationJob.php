<?php

namespace App\Jobs;

use App\Models\Communication;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Communication $communication,
    ) {}

    public function handle(WhatsAppProviderInterface $whatsapp): void
    {
        try {
            match ($this->communication->channel) {
                'whatsapp' => $this->sendWhatsApp($whatsapp),
                'email'    => $this->sendEmail(),
                default    => throw new \InvalidArgumentException("Unknown channel: {$this->communication->channel}"),
            };

            $this->communication->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Communication send failed', [
                'id'    => $this->communication->id,
                'error' => $e->getMessage(),
            ]);

            $this->communication->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendWhatsApp(WhatsAppProviderInterface $whatsapp): void
    {
        $result = $whatsapp->send(
            $this->communication->to,
            $this->communication->body,
        );

        $this->communication->update([
            'metadata' => array_merge(
                $this->communication->metadata ?? [],
                ['provider_response' => $result],
            ),
        ]);
    }

    private function sendEmail(): void
    {
        Mail::raw($this->communication->body, function ($mail) {
            $mail->to($this->communication->to)
                 ->subject($this->communication->subject ?? 'Notificación del taller');
        });
    }
}
