<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Mail;

use App\Models\EmailRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class EmailRequestProcessed extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<string>  $validation_errors
     */
    public function __construct(public readonly EmailRequest $email, public readonly array $validation_errors)
    {
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $this->to(config('services.treasurer_email_address'), config('services.treasurer_name'));

        User::permission('update-email-requests')->get()->each(function (User $user, int $key): void {
            $this->cc($user->email, $user->name);
        });

        return $this
            ->withSymfonyMessage(static function (Email $email): void {
                $email->replyTo(config('services.developer_email_address'));
            })
            ->subject(
                '[LOOP-'.$this->email->id.'] Email request processed with '.
                (
                    count($this->validation_errors) === 0 ? 'no problems detected' : (
                        count($this->validation_errors) === 1 ? '1 problem detected' :
                            count($this->validation_errors).' problems detected'
                    )
                )
            )
            ->text('mail.emailrequestprocessed')
            ->tag('email-request-processed')
            ->metadata('email-request-id', strval($this->email->id));
    }
}
