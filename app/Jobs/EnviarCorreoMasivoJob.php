<?php

namespace App\Jobs;

use App\Mail\CorreoMasivo;
use App\Models\CampanaCorreo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarCorreoMasivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $campanaId,
        public array $destinatarios  // [['nombre' => '...', 'correo' => '...']]
    ) {}

    public function handle(): void
    {
        $campana = CampanaCorreo::find($this->campanaId);
        if (!$campana) {
            return;
        }

        if ($campana->estado === 'pendiente') {
            $campana->update(['estado' => 'procesando']);
        }

        $enviados = 0;
        $fallidos = 0;

        foreach ($this->destinatarios as $destinatario) {
            if (empty($destinatario['correo'])) {
                $fallidos++;
                continue;
            }

            try {
                Mail::to($destinatario['correo'])
                    ->send(new CorreoMasivo(
                        $destinatario['nombre'],
                        $campana->asunto,
                        $campana->mensaje
                    ));
                $enviados++;
            } catch (\Throwable $e) {
                $fallidos++;
                Log::error("Error enviando correo a {$destinatario['correo']}: " . $e->getMessage());
            }
        }

        $campana->increment('total_enviados', $enviados);
        $campana->increment('total_fallidos', $fallidos);

        $campana->refresh();
        $procesados = $campana->total_enviados + $campana->total_fallidos;

        if ($procesados >= $campana->total_destinatarios) {
            $campana->update([
                'estado' => $campana->total_fallidos === $campana->total_destinatarios
                    ? 'fallido'
                    : 'completado',
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $campana = CampanaCorreo::find($this->campanaId);
        if ($campana) {
            $campana->update(['estado' => 'fallido']);
        }
        Log::error("Job EnviarCorreoMasivo falló para campaña {$this->campanaId}: " . $exception->getMessage());
    }
}
