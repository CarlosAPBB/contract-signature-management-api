<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contract;
    public $signedFilePath;

    /**
     * Create a new message instance.
     */
    public function __construct($contract, $signedFilePath)
    {
        $this->contract = $contract;
        $this->signedFilePath = $signedFilePath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope()
    {
        return (new Envelope)
            ->subject('Contrato Firmado');
    }

    /**
     * Get the message content definition.
     */
    public function content()
    {
        return new Content('contract_signed');
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments()
    {
        return [];
    }
}
