<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Login memakai nama_pengguna (username) selain email, sesuai cara kerja
     * kasir di kasir yang lebih cepat mengetik nama singkat daripada email.
     *
     * Kolomnya nullable dan diisi dari email user yang sudah ada, jadi migrasi
     * ini tidak mengubah data lama maupun memblokir user tanpa username.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 60)->nullable()->after('name')->unique();
        });

        $taken = DB::table('users')->whereNotNull('username')->pluck('username')->all();

        DB::table('users')->orderBy('id')->get(['id', 'email', 'username'])->each(
            function (object $user) use (&$taken): void {
                if ($user->username !== null) {
                    return;
                }

                $base = Str::of((string) $user->email)
                    ->before('@')
                    ->lower()
                    ->replaceMatches('/[^a-z0-9._-]/', '')
                    ->limit(50, '')
                    ->value();

                $base = $base !== '' ? $base : 'user';
                $username = $base;
                $suffix = 1;

                while (in_array($username, $taken, true)) {
                    $username = $base.'.'.++$suffix;
                }

                $taken[] = $username;

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
