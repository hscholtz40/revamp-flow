<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove duplicate entries from user_companies table, keeping only the first occurrence.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite approach: Create a temporary table with unique entries, then replace
            DB::statement('
                CREATE TEMPORARY TABLE user_companies_temp AS
                SELECT MIN(id) as id, user_id, company_id, MIN(created_at) as created_at, MIN(updated_at) as updated_at
                FROM user_companies
                GROUP BY user_id, company_id
            ');
            
            DB::statement('DELETE FROM user_companies');
            
            DB::statement('
                INSERT INTO user_companies (id, user_id, company_id, created_at, updated_at)
                SELECT id, user_id, company_id, created_at, updated_at
                FROM user_companies_temp
            ');
            
            DB::statement('DROP TABLE user_companies_temp');
        } else {
            // MySQL/MariaDB approach: Delete duplicates keeping the minimum id
            DB::statement('
                DELETE uc1 FROM user_companies uc1
                INNER JOIN user_companies uc2
                WHERE uc1.id > uc2.id
                AND uc1.user_id = uc2.user_id
                AND uc1.company_id = uc2.company_id
            ');
        }
    }

    /**
     * Reverse the migrations.
     * This migration cannot be reversed as we don't know which duplicates existed.
     */
    public function down(): void
    {
        // Cannot reverse - duplicates were removed
    }
};
