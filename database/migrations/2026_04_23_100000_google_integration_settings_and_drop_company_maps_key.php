<?php

use App\Models\Company;
use App\Models\GoogleIntegrationSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->text('maps_api_key')->nullable();
            $table->string('maps_map_id', 255)->nullable();
            $table->timestamps();
        });

        if (Schema::hasColumn('companies', 'google_maps_api_key')) {
            $source = Company::query()
                ->whereNotNull('google_maps_api_key')
                ->orderBy('id')
                ->first();

            if ($source !== null && filled($source->google_maps_api_key)) {
                GoogleIntegrationSettings::query()->create([
                    'maps_api_key' => $source->google_maps_api_key,
                ]);
            }

            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('google_maps_api_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_integration_settings');

        if (! Schema::hasColumn('companies', 'google_maps_api_key')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->text('google_maps_api_key')->nullable()->after('bank_sort_code');
            });
        }
    }
};
