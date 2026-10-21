<?php

namespace App\Mail;

use App\Models\Cita;
use App\Services\CalendarioCita;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaNotificacion extends Mailable
{
    use SerializesModels;

    public const TIPOS = ['recibida', 'confirmada', 'cancelada', 'recordatorio_24h', 'recordatorio_2h'];

    public $cita;
    public $tipo;
    public $destinatario;

    /**
     * @param string $tipo         recibida | confirmada | cancelada | recordatorio_24h | recordatorio_2h
     * @param string $destinatario cliente | barbero
     */
    public function __construct(Cita $cita, string $tipo, string $destinatario = 'cliente')
    {
        $this->cita = $cita;
        $this->tipo = $tipo;
        $this->destinatario = $destinatario;
    }

    public function build()
    {
        $cita = $this->cita->loadMissing(['cliente', 'barbero', 'sede', 'servicios']);
        $esCliente = $this->destinatario === 'cliente';
        $cancelada = $this->tipo === 'cancelada';

        $asuntos = [
            'recibida' => $esCliente ? 'Recibimos tu reserva' : 'Nueva cita asignada',
            'confirmada' => 'Tu cita está confirmada',
            'cancelada' => 'Cita cancelada',
            'recordatorio_24h' => 'Recordatorio: tu cita es mañana',
            'recordatorio_2h' => 'Recordatorio: tu cita es en 2 horas',
        ];

        $this->subject($asuntos[$this->tipo] . ' - Barbería Pro')
            ->view('emails.cita')
            ->with([
                'cita' => $cita,
                'tipo' => $this->tipo,
                'esCliente' => $esCliente,
                'nombre' => $esCliente ? $cita->cliente->name : $cita->barbero->name,
                'fecha' => $cita->fecha->copy()->locale('es')->translatedFormat('l d \d\e F \d\e Y'),
                'hora' => $cita->hora_inicio . ' - ' . $cita->hora_fin,
                'servicios' => $cita->servicios->pluck('nombre')->implode(', '),
                'enlaceGoogle' => $cancelada ? null : CalendarioCita::enlaceGoogle($cita),
                'urlApp' => config('barberia.url_frontend'),
            ]);

        // El adjunto .ics permite guardar la cita (o eliminarla si se canceló) en cualquier calendario.
        return $this->attachData(
            CalendarioCita::ics($cita, $cancelada),
            'cita-' . $cita->id . '.ics',
            ['mime' => 'text/calendar; charset=UTF-8; method=' . ($cancelada ? 'CANCEL' : 'PUBLISH')]
        );
    }
}
