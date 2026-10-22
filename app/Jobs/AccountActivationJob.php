<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AccountActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $user;
    protected $callbackUrl;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $callbackUrl)
    {
        $this->user = $user;
        $this->callbackUrl = $callbackUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->user->sendPasswordActivation($this->callbackUrl);
        } catch (\Exception $e) {
            // Handle exception if needed
        }
    }
}
