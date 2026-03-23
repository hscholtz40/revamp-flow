<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encrypt existing plaintext integration secrets (Xero, SMS, WhatsApp, SMTP).
     * Idempotent: skips values that already decrypt successfully.
     */
    public function up(): void
    {
        $this->widenStringColumnsIfSupported();

        $enc = function (?string $value): ?string {
            if ($value === null || $value === '') {
                return $value;
            }
            try {
                Crypt::decryptString($value);

                return $value;
            } catch (\Throwable) {
                return Crypt::encryptString($value);
            }
        };

        foreach (DB::table('xero_settings')->select('id', 'client_secret', 'access_token', 'refresh_token')->cursor() as $row) {
            DB::table('xero_settings')->where('id', $row->id)->update([
                'client_secret' => $enc($row->client_secret),
                'access_token' => $enc($row->access_token),
                'refresh_token' => $enc($row->refresh_token),
            ]);
        }

        foreach (DB::table('sms_settings')->select('id', 'bulksms_password')->cursor() as $row) {
            DB::table('sms_settings')->where('id', $row->id)->update([
                'bulksms_password' => $enc($row->bulksms_password),
            ]);
        }

        if (Schema::hasTable('whatsapp_settings')) {
            foreach (DB::table('whatsapp_settings')->select('id', 'api_key', 'api_secret')->cursor() as $row) {
                DB::table('whatsapp_settings')->where('id', $row->id)->update([
                    'api_key' => $enc($row->api_key),
                    'api_secret' => $enc($row->api_secret),
                ]);
            }
        }

        if (Schema::hasColumn('companies', 'smtp_password')) {
            foreach (DB::table('companies')->select('id', 'smtp_password')->whereNotNull('smtp_password')->cursor() as $row) {
                DB::table('companies')->where('id', $row->id)->update([
                    'smtp_password' => $enc($row->smtp_password),
                ]);
            }
        }

        if (Schema::hasColumn('users', 'smtp_password')) {
            foreach (DB::table('users')->select('id', 'smtp_password')->whereNotNull('smtp_password')->cursor() as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'smtp_password' => $enc($row->smtp_password),
                ]);
            }
        }
    }

    private function widenStringColumnsIfSupported(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE xero_settings MODIFY client_secret TEXT NULL');
            DB::statement('ALTER TABLE sms_settings MODIFY bulksms_password TEXT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE xero_settings ALTER COLUMN client_secret TYPE TEXT');
            DB::statement('ALTER TABLE sms_settings ALTER COLUMN bulksms_password TYPE TEXT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dec = function (?string $value): ?string {
            if ($value === null || $value === '') {
                return $value;
            }
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return $value;
            }
        };

        foreach (DB::table('xero_settings')->select('id', 'client_secret', 'access_token', 'refresh_token')->cursor() as $row) {
            DB::table('xero_settings')->where('id', $row->id)->update([
                'client_secret' => $dec($row->client_secret),
                'access_token' => $dec($row->access_token),
                'refresh_token' => $dec($row->refresh_token),
            ]);
        }

        foreach (DB::table('sms_settings')->select('id', 'bulksms_password')->cursor() as $row) {
            DB::table('sms_settings')->where('id', $row->id)->update([
                'bulksms_password' => $dec($row->bulksms_password),
            ]);
        }

        if (Schema::hasTable('whatsapp_settings')) {
            foreach (DB::table('whatsapp_settings')->select('id', 'api_key', 'api_secret')->cursor() as $row) {
                DB::table('whatsapp_settings')->where('id', $row->id)->update([
                    'api_key' => $dec($row->api_key),
                    'api_secret' => $dec($row->api_secret),
                ]);
            }
        }

        if (Schema::hasColumn('companies', 'smtp_password')) {
            foreach (DB::table('companies')->select('id', 'smtp_password')->whereNotNull('smtp_password')->cursor() as $row) {
                DB::table('companies')->where('id', $row->id)->update([
                    'smtp_password' => $dec($row->smtp_password),
                ]);
            }
        }

        if (Schema::hasColumn('users', 'smtp_password')) {
            foreach (DB::table('users')->select('id', 'smtp_password')->whereNotNull('smtp_password')->cursor() as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'smtp_password' => $dec($row->smtp_password),
                ]);
            }
        }
    }
};
