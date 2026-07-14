<?php

namespace App\Console\Commands;

use App\Models\ApiConsumer;
use Illuminate\Console\Command;

class CreateApiConsumer extends Command
{
    protected $signature = 'api-consumers:create {name : A human-readable name for the consuming system, e.g. "hr-portal"}';

    protected $description = 'Create a new API consumer and issue it a Sanctum token';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (ApiConsumer::where('name', $name)->exists()) {
            $this->error("An API consumer named \"{$name}\" already exists.");

            return self::FAILURE;
        }

        $consumer = ApiConsumer::create([
            'name' => $name,
            'is_active' => true,
        ]);

        $token = $consumer->createToken($name)->plainTextToken;

        $this->info("API consumer \"{$name}\" created.");
        $this->newLine();
        $this->line('Token (shown once, store it securely):');
        $this->line($token);

        return self::SUCCESS;
    }
}
