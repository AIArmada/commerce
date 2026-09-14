<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Rendering\DocsRichContentFileAttachmentProvider;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
    CarbonImmutable::setTestNow();
});

it('duplicates email templates with collision-free slugs and feedback (L3)', function (): void {
    $template = DocEmailTemplate::query()->create([
        'name' => 'Welcome',
        'slug' => 'welcome',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Hello',
        'body' => 'Body',
        'is_active' => true,
    ]);

    $first = DocEmailTemplateResource::duplicateTemplate($template);
    $second = DocEmailTemplateResource::duplicateTemplate($template);

    expect($first->slug)->not()->toBe($template->slug);
    expect($second->slug)->not()->toBe($first->slug);
    expect(DocEmailTemplate::query()->where('slug', $first->slug)->count())->toBe(1);
    expect(DocEmailTemplate::query()->where('slug', $second->slug)->count())->toBe(1);

    $notifications = session()->get('filament.notifications', []);
    expect($notifications)->not()->toBeEmpty();
    expect(collect($notifications)->last()['title'])->toBe('Email template duplicated');
});

it('approves and rejects through the domain approval methods (L4)', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Page Approver',
        'email' => 'page-approver@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $doc = Doc::factory()->create();

    $approval = DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => 'someone-else',
        'assigned_to' => (string) $user->id,
        'status' => DocApprovalStatus::Pending,
    ]);

    $approve = new ReflectionMethod(PendingApprovalsPage::class, 'approveApproval');
    $approve->invoke(null, $approval, 'looks good');

    $approval->refresh();
    expect($approval->status)->toBe(DocApprovalStatus::Approved);
    expect($approval->approved_at)->not()->toBeNull();
    expect($approval->comments)->toBe('looks good');

    $rejection = DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => 'someone-else',
        'assigned_to' => (string) $user->id,
        'status' => DocApprovalStatus::Pending,
    ]);

    $reject = new ReflectionMethod(PendingApprovalsPage::class, 'rejectApproval');
    $reject->invoke(null, $rejection, 'needs changes');

    $rejection->refresh();
    expect($rejection->status)->toBe(DocApprovalStatus::Rejected);
    expect($rejection->rejected_at)->not()->toBeNull();
    expect($rejection->comments)->toBe('needs changes');
});

it('refuses page approval actions from other users (L4)', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Assignee',
        'email' => 'assignee@example.test',
        'password' => bcrypt('password'),
    ]);

    /** @var User $other */
    $other = User::query()->create([
        'name' => 'Stranger',
        'email' => 'stranger@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($other);

    $doc = Doc::factory()->create();

    $approval = DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => 'someone-else',
        'assigned_to' => (string) $user->id,
        'status' => DocApprovalStatus::Pending,
    ]);

    $approve = new ReflectionMethod(PendingApprovalsPage::class, 'approveApproval');

    expect(fn (): mixed => $approve->invoke(null, $approval, null))
        ->toThrow(fn (HttpException $exception): bool => $exception->getStatusCode() === 403);
});

it('expires private attachment urls at the configured minutes (L5)', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 10:00:00'));
    config()->set('docs.storage.disk', 'local');
    config()->set('docs.storage.rich_content_visibility', 'private');
    config()->set('filament.temporary_file_url_expiry_minutes', 30);

    $file = 'docs/rich-content/attachment.pdf';

    $disk = Mockery::mock();
    $disk->shouldReceive('exists')->with($file)->andReturn(true);
    $disk->shouldReceive('temporaryUrl')->with($file, Mockery::capture($expiry))->andReturn('https://cdn.test/signed');

    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $url = (new DocsRichContentFileAttachmentProvider)->getFileAttachmentUrl($file);

    expect($url)->toBe('https://cdn.test/signed');
    expect($expiry)->toEqual(CarbonImmutable::parse('2026-01-01 10:30:00'));
});
