<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class FormularioDeContacto extends Mailable
{
    use Queueable, SerializesModels;

    public $mensaje;
    public $tour;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mensaje, $tour)
    {
        $this->mensaje = $mensaje;
        $this->tour = $tour;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.send');
    }
}
