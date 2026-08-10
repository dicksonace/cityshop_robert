<?php

use App\Services\PlatformSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('platform_settings')->where('key', PlatformSettings::WITHDRAWAL_FEE_KEY)->first();
        if (! $row) {
            return;
        }

        $decoded = is_string($row->value) ? json_decode($row->value, true) : null;
        if (! is_array($decoded)) {
            return;
        }

        $decoded['bank_tiers'] = PlatformSettings::normalizeBankFeeTiers($decoded['bank_tiers'] ?? null);

        DB::table('platform_settings')->where('key', PlatformSettings::WITHDRAWAL_FEE_KEY)->update([
            'value' => json_encode($decoded),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
