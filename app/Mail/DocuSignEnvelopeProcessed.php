<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DocuSignEnvelope;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class DocuSignEnvelopeProcessed extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<string>  $validation_errors
     */
    public function __construct(
        public readonly DocuSignEnvelope $envelope,
        public readonly array $validation_errors,
        private readonly ?User $user
    ) {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        if ($this->user === null) {
            $this->to(config('services.treasurer_email_address'));
        } else {
            $this->to($this->user->email, $this->user->name)
                ->cc(config('services.treasurer_email_address'));
        }

        return $this->cc(config('services.developer_email_address'))
            ->withSymfonyMessage(static function (Email $email): void {
                $email->replyTo(config('services.developer_email_address'));
            })
            ->subject(
                '[LOOP-'.$this->envelope->id.'] '.ucfirst(DocuSignEnvelope::$types[$this->envelope->type])
                .' form processed with '.
                (
                    count($this->validation_errors) === 0 ? 'no problems detected' : (
                        count($this->validation_errors) === 1 ? '1 problem detected' :
                            count($this->validation_errors).' problems detected'
                    )
                )
            )
            ->text('mail.docusignenvelopeprocessed')
            ->tag('docusign-envelope-processed')
            ->metadata('envelope-id', strval($this->envelope->id));
    }
}
