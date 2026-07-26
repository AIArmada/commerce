<?php

declare(strict_types=1);

use AIArmada\FilamentPersons\Resources\PersonResource\RelationManagers\TitleAssignmentsRelationManager;
use AIArmada\Persons\Enums\AssignmentStatus;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('resolves title assignment colors from the enum state', function (): void {
    $table = (new TitleAssignmentsRelationManager)->table(
        Table::make(Mockery::mock(HasTable::class)),
    );

    $statusColumn = $table->getColumn('status');

    expect($statusColumn?->getColor(AssignmentStatus::Active))->toBe('success')
        ->and($statusColumn?->getColor(AssignmentStatus::Revoked))->toBe('danger')
        ->and($statusColumn?->getColor(AssignmentStatus::Expired))->toBe('warning');
});
