<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function actingAsApiUser(): static
    {
        $user = User::where('email', 'admin@gmail.com')->firstOrFail();
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
