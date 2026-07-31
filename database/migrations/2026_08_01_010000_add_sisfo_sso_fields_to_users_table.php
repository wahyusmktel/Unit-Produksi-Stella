<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso_subject')->nullable()->unique()->after('id');
            $table->string('avatar_url')->nullable()->after('email_verified_at');
            $table->json('sso_roles')->nullable()->after('avatar_url');
            $table->timestamp('last_sso_login_at')->nullable()->after('sso_roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['sso_subject']);
            $table->dropColumn(['sso_subject', 'avatar_url', 'sso_roles', 'last_sso_login_at']);
        });
    }
};
