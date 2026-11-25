<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('roles')->nullable()->after('role');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $roles = [$user->role];

            if ($user->is_lead && $user->role !== 'designer') {
                $roles[] = 'designer';
            }

            DB::table('users')->where('id', $user->id)->update(['roles' => json_encode($roles)]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }
};
