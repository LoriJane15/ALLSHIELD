<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'notifications';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE) && $this->canonical()) {
            return;
        }
        if (Schema::hasTable(self::TABLE) && DB::table(self::TABLE)->count() > 0) {
            throw new RuntimeException('The populated notifications table is incompatible; refusing to replace it.');
        }

        DB::transaction(function (): void {
            Schema::dropIfExists(self::TABLE);
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        // Ownership predates this migration on existing systems; never drop shared notifications destructively.
    }

    private function canonical(): bool
    {
        $columns = collect(Schema::getColumns(self::TABLE))->keyBy('name');
        $required = ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'];
        if ($columns->keys()->sort()->values()->all() !== collect($required)->sort()->values()->all()) {
            return false;
        }

        $notNull = ['id', 'type', 'notifiable_type', 'notifiable_id', 'data'];
        $nullable = ['read_at', 'created_at', 'updated_at'];
        if (collect($notNull)->contains(fn (string $name): bool => $columns[$name]['nullable'] ?? true)
            || collect($nullable)->contains(fn (string $name): bool => ! ($columns[$name]['nullable'] ?? false))
            || ! $this->hasType($columns['id'], ['char', 'varchar', 'text', 'string', 'uuid'])
            || ! $this->hasType($columns['type'], ['char', 'varchar', 'text', 'string'])
            || ! $this->hasType($columns['notifiable_type'], ['char', 'varchar', 'text', 'string'])
            || ! $this->hasType($columns['notifiable_id'], ['bigint', 'int', 'integer', 'smallint'])
            || ! $this->hasType($columns['data'], ['json', 'jsonb', 'text'])) {
            return false;
        }

        $indexes = collect(Schema::getIndexes(self::TABLE));
        $primary = $indexes->contains(fn (array $index): bool => ($index['primary'] ?? false)
            && ($index['unique'] ?? false)
            && ($index['columns'] ?? []) === ['id']);
        $polymorphic = $indexes->contains(fn (array $index): bool => ! ($index['primary'] ?? false)
            && ! ($index['unique'] ?? false)
            && ($index['columns'] ?? []) === ['notifiable_type', 'notifiable_id']);

        return $primary && $polymorphic;
    }

    private function hasType(array $column, array $allowed): bool
    {
        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
        $type = preg_replace('/\(.*/', '', $type);

        return in_array(trim($type), $allowed, true);
    }
};
