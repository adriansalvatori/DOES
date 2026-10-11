<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_channel')->default('any'); // whatsapp, call, email, any
            $table->string('preferred_language')->default('es'); // es, en, both
            $table->string('role_title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'contact_id']);
        });

        if (! Schema::hasColumn('orders', 'contact_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('client_location_id')->constrained('contacts')->nullOnDelete();
            });
        }

        // Data migration from legacy client_contacts
        if (Schema::hasTable('client_contacts')) {
            $legacyContacts = DB::table('client_contacts')->orderBy('id')->get();
            $createdContactsMap = []; // uppercase_name => Contact ID

            foreach ($legacyContacts as $legacy) {
                $cleanName = mb_strtoupper(trim($legacy->name ?? ''), 'UTF-8');
                if (empty($cleanName)) {
                    continue;
                }

                if (! isset($createdContactsMap[$cleanName])) {
                    $contactId = DB::table('contacts')->insertGetId([
                        'name' => $cleanName,
                        'phone' => ! empty($legacy->phone) ? trim($legacy->phone) : null,
                        'email' => ! empty($legacy->email) ? trim($legacy->email) : null,
                        'role_title' => ! empty($legacy->department) ? trim($legacy->department) : null,
                        'preferred_channel' => 'any',
                        'preferred_language' => 'es',
                        'notes' => null,
                        'created_at' => $legacy->created_at ?? now(),
                        'updated_at' => $legacy->updated_at ?? now(),
                    ]);
                    $createdContactsMap[$cleanName] = $contactId;
                } else {
                    $existingId = $createdContactsMap[$cleanName];
                    $updates = [];
                    if (! empty($legacy->phone)) {
                        $current = DB::table('contacts')->where('id', $existingId)->value('phone');
                        if (empty($current)) {
                            $updates['phone'] = trim($legacy->phone);
                        }
                    }
                    if (! empty($legacy->email)) {
                        $current = DB::table('contacts')->where('id', $existingId)->value('email');
                        if (empty($current)) {
                            $updates['email'] = trim($legacy->email);
                        }
                    }
                    if (! empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('contacts')->where('id', $existingId)->update($updates);
                    }
                }

                $contactId = $createdContactsMap[$cleanName];

                // Link in pivot table if client exists
                if (! empty($legacy->client_id)) {
                    $existsInPivot = DB::table('client_contact')
                        ->where('client_id', $legacy->client_id)
                        ->where('contact_id', $contactId)
                        ->exists();

                    if (! $existsInPivot) {
                        DB::table('client_contact')->insert([
                            'client_id' => $legacy->client_id,
                            'contact_id' => $contactId,
                            'is_primary' => (bool) ($legacy->is_primary ?? false),
                            'role' => ! empty($legacy->department) ? trim($legacy->department) : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Link existing orders with contact_id based on responsible_person
            $allContacts = DB::table('contacts')->select('id', 'name')->get();
            foreach ($allContacts as $c) {
                DB::table('orders')
                    ->whereNull('contact_id')
                    ->where(function ($q) use ($c) {
                        $q->where('responsible_person', $c->name)
                            ->orWhere('responsible_person', mb_strtolower($c->name, 'UTF-8'))
                            ->orWhere('responsible_person', ucwords(mb_strtolower($c->name, 'UTF-8')));
                    })
                    ->update(['contact_id' => $c->id]);
            }

            // Safely rename legacy table
            Schema::rename('client_contacts', 'client_contacts_legacy');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('client_contacts_legacy')) {
            Schema::rename('client_contacts_legacy', 'client_contacts');
        }

        if (Schema::hasColumn('orders', 'contact_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            });
        }

        Schema::dropIfExists('client_contact');
        Schema::dropIfExists('contacts');
    }
};
