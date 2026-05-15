<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_super_admin')->default(false)->after('is_active');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP FOREIGN KEY users_tenant_id_foreign');
            DB::statement('ALTER TABLE users MODIFY tenant_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP FOREIGN KEY users_tenant_id_foreign');
            DB::statement('ALTER TABLE users MODIFY tenant_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_super_admin');
            });
        }
    }
};
