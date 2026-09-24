<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Cita;

class RecordatorioCita extends Mailable
{
    use Queueable, SerializesModels;

    public $cita;
    public $tiempo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Cita $cita, $tiempo)
    {
        $this->cita = $cita;
        $this->tiempo = $tiempo;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $servicios = $this->cita->servicios->pluck('nombre')->implode(', ');
        
        return $this->view('emails.recordatorio_cita')
                    ->subject('Recordatorio de Cita - Barbería')
                    ->with([
                        'clienteNombre' => $this->cita->cliente->name,
                        'sedeNombre' => $this->cita->sede->nombre,
                        'barberoNombre' => $this->cita->barbero->name,
                        'fecha' => $this->cita->fecha,
                        'hora' => $this->cita->hora_inicio,
                        'servicios' => $servicios,
                        'tiempo' => $this->tiempo,
                    ]);
    }
}