<?php

use App\Enums\SellerStatus;
use App\Models\SellerProfile;
use App\Services\StoreCustomizationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('published_settings');
            $table->json('draft_settings');
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        $defaults = StoreCustomizationService::defaults();

        SellerProfile::query()
            ->where('status', SellerStatus::Approved)
            ->each(function (SellerProfile $profile) use ($defaults) {
                $profile->storeCustomization()->create([
                    'published_settings' => $defaults,
                    'draft_settings' => $defaults,
                    'setup_completed_at' => now(),
                    'published_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_customizations');
    }
};
