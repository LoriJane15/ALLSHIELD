<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLES = ['super_admin', 'admin', '39th_ib', 'gov_agency', 'lgu', 'mblrc', 'afp', 'japic', 'pswdo'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->setRoleConstraint(self::ROLES);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION pswdo_document_immutable_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'PSWDO final documents are immutable';
            END;
            $$
        SQL);
        DB::statement('CREATE TRIGGER pswdo_documents_no_update BEFORE UPDATE ON pswdo_enrollment_documents FOR EACH ROW EXECUTE FUNCTION pswdo_document_immutable_guard()');
        DB::statement('CREATE TRIGGER pswdo_documents_no_delete BEFORE DELETE ON pswdo_enrollment_documents FOR EACH ROW EXECUTE FUNCTION pswdo_document_immutable_guard()');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        if (DB::table('users')->where('role', 'pswdo')->exists()) {
            throw new RuntimeException('Cannot remove the PSWDO role constraint while PSWDO users exist.');
        }

        DB::statement('DROP TRIGGER IF EXISTS pswdo_documents_no_update ON pswdo_enrollment_documents');
        DB::statement('DROP TRIGGER IF EXISTS pswdo_documents_no_delete ON pswdo_enrollment_documents');
        DB::statement('DROP FUNCTION IF EXISTS pswdo_document_immutable_guard()');
        $this->setRoleConstraint(array_slice(self::ROLES, 0, -1));
    }

    private function setRoleConstraint(array $roles): void
    {
        $values = implode(', ', array_map(fn (string $role): string => DB::getPdo()->quote($role), $roles));
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$values}))");
    }
};
