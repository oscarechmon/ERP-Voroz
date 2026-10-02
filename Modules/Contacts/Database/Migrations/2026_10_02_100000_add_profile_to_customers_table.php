<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha completa del cliente del centro: código (CLI-000123), datos personales,
 * cómo nos conoció y los antecedentes que el especialista revisa antes de
 * atender (alergias, contraindicaciones, medicación…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('code', 20)->nullable()->unique()->after('company_id');
            $table->date('birth_date')->nullable()->after('address');
            $table->string('gender', 1)->nullable()->after('birth_date');
            $table->string('whatsapp', 30)->nullable()->after('phone');
            $table->string('district', 100)->nullable()->after('address');
            $table->string('how_knew', 100)->nullable()->after('gender');
            $table->text('allergies')->nullable()->after('notes');
            $table->text('restrictions')->nullable()->after('allergies');
            $table->text('contraindications')->nullable()->after('restrictions');
            $table->text('medications')->nullable()->after('contraindications');
            $table->text('relevant_info')->nullable()->after('medications');
        });

        // Los clientes que ya había reciben su código en orden de alta.
        $number = 0;
        DB::table('customers')->whereNull('code')->orderBy('id')->pluck('id')->each(function (int $id) use (&$number): void {
            DB::table('customers')->where('id', $id)->update(['code' => 'CLI-'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'birth_date', 'gender', 'whatsapp', 'district', 'how_knew',
                'allergies', 'restrictions', 'contraindications', 'medications', 'relevant_info',
            ]);
        });
    }
};
