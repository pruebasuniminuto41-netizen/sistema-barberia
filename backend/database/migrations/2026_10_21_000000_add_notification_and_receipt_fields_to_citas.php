<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotificationAndReceiptFieldsToCitas extends Migration
{
    public function up()
    {
        Schema::table('citas', function (Blueprint $table) {
            // El comprobante se guarda en la base de datos: el disco de Render se borra en cada despliegue.
            $table->text('comprobante_pago_data')->nullable();
            $table->string('comprobante_pago_mime', 100)->nullable();
            // Evita enviar el mismo recordatorio varias veces.
            $table->timestamp('recordatorio_24h_enviado_at')->nullable();
            $table->timestamp('recordatorio_2h_enviado_at')->nullable();
            // Se incrementa en cada cambio para que el calendario actualice el evento.
            $table->unsignedInteger('secuencia_calendario')->default(0);
        });
    }

    public function down()
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropColumn([
                'comprobante_pago_data',
                'comprobante_pago_mime',
                'recordatorio_24h_enviado_at',
                'recordatorio_2h_enviado_at',
                'secuencia_calendario',
            ]);
        });
    }
}
