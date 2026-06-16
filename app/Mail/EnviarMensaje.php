<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnviarMensaje extends Mailable
{
    use Queueable, SerializesModels;

    public $nombres;
    public $correo;
    public $telefono;
    public $mensaje;
    public $translate;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($nombres, $correo, $telefono, $mensaje, $translate)
    {
        $this->nombres = $nombres;
        $this->correo = $correo;
        $this->telefono = $telefono;
        $this->mensaje = $mensaje;
        $this->reserva = $reserva;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.formulario');
    }
}
