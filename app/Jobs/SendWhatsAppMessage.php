<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SendWhatsAppMessage extends Job implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;

    protected $recipient;
    protected $message;

    /**
     * Create a new job instance.
     *
     * @param string $recipient
     * @param string $message
     * @return void
     */
    public function __construct($recipient, $message)
    {
        $this->recipient = $recipient;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $twilioSid = env('TWILIO_ID');
        $twilioToken = env('TWILIO_TOKEN');
        $fromNumber = env('TWILIO_FROM_NUMBER_TEST');
        $twilio = new Client($twilioSid, $twilioToken);

        $message = $twilio->messages->create("whatsapp:$this->recipient", array(
            'from' => "whatsapp:$fromNumber",
            'body' => $this->message
        ));

        // Log the message ID
        Log::info('Whatsapp Message sent successfully. Message SID: ' . $message->sid. ' to: ' . $this->recipient);
    }
}

