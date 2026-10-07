<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('intacct_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('token_type');
            $table->timestamp('expires_at');
            $table->json('scopes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intacct_tokens');
    }
};
