<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string APPLICATION_INDEX = 'membership_applications_subject_applicant_status_unique';

    private const string INVITATION_INDEX = 'membership_invitations_subject_email_role_status_unique';

    public function up(): void
    {
        $this->addUniqueIndex(
            (string) config('membership.database.tables.applications', 'membership_applications'),
            self::APPLICATION_INDEX,
            ['subject_type', 'subject_id', 'applicant_id', 'status'],
        );

        $this->addUniqueIndex(
            (string) config('membership.database.tables.invitations', 'membership_invitations'),
            self::INVITATION_INDEX,
            ['subject_type', 'subject_id', 'email', 'role', 'status'],
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addUniqueIndex(string $tableName, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }
};
