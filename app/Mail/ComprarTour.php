<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ComprarTour extends Mailable
{
    use Queueable, SerializesModels;

    public $reserva;
    public $tour;
    public $translate;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($reserva, $tour, $translate)
    {
        $this->reserva = $reserva;
        $this->tour = $tour;
        $this->translate = $translate;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.reservas');
    }
}
