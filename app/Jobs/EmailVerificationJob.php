<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmailVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $name;
    protected $callbackUrl;
    protected $newEmail;
    /**
     * Create a new job instance.
     */
    public function __construct(User $user, $name, $callbackUrl, $newEmail)
    {
        $this->user = $user;
        $this->name = $name;
        $this->callbackUrl = $callbackUrl;
        $this->newEmail = $newEmail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

            $this->user->sendEmailVerification($this->name, $this->newEmail, $this->callbackUrl);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
