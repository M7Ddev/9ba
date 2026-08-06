<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brew log.
 *
 * There is no authentication in this app, so brews are grouped by `client_id` —
 * a random identifier the browser generates once and keeps in localStorage. It
 * is not a credential and grants no access to anything; it only separates one
 * person's history from another's on a shared install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brews', function (Blueprint $table) {
            $table->id();

            $table->string('client_id', 64)->index();

            // The setup that produced this recipe.
            $table->string('method');
            $table->string('roast');
            $table->string('origin');
            $table->string('process');
            $table->string('grinder')->nullable();
            $table->unsignedSmallInteger('amount_ml');
            $table->string('taste');

            // The recipe itself, as returned to the browser.
            $table->json('recipe');

            // How the cup actually tasted. Null until the user tells us.
            $table->string('feedback')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brews');
    }
};
