<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\FilamentDocs\Actions\SendEmailAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Support\Facades\Mail;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentDocs_makeSchemaLivewire')) {
    function filamentDocs_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

/**
 * @return array<int, Component|Action|ActionGroup>
 */
function filamentDocs_flattenSchemaComponents(Schema $schema): array
{
    $flattened = [];

    $walk = function (array $components) use (&$walk, &$flattened): void {
        foreach ($components as $component) {
            $flattened[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $walk($component->getChildComponents());
            }
        }
    };

    $walk($schema->getComponents());

    return $flattened;
}

it('derives recipient name and email from customer_data', function (): void {
    $doc = Doc::factory()->make([
        'customer_data' => [
            'email' => 'to@example.test',
            'name' => 'Jane Doe',
        ],
    ]);

    $emailMethod = new ReflectionMethod(SendEmailAction::class, 'getRecipientEmail');

    $nameMethod = new ReflectionMethod(SendEmailAction::class, 'getRecipientName');

    expect($emailMethod->invoke(null, $doc))->toBe('to@example.test');
    expect($nameMethod->invoke(null, $doc))->toBe('Jane Doe');
});

it('builds template select options from configured model', function (): void {
    config()->set('docs.models.email_template', DocEmailTemplate::class);

    DocEmailTemplate::query()->create([
        'name' => 'Template A',
        'slug' => 'template-a',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Hello',
        'body' => 'Body',
        'is_active' => true,
    ]);

    DocEmailTemplate::query()->create([
        'name' => 'Template B',
        'slug' => 'template-b',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Hello',
        'body' => 'Body',
        'is_active' => false,
    ]);

    $action = SendEmailAction::make();
    $schema = $action->getForm(Schema::make(filamentDocs_makeSchemaLivewire()));

    $select = collect($schema ? filamentDocs_flattenSchemaComponents($schema) : [])
        ->first(fn ($component) => $component instanceof Select && $component->getName() === 'template_id');

    expect($select)->toBeInstanceOf(Select::class);
    expect($select->getOptions())->toHaveCount(1);
});

it('sends email successfully and pushes a success notification to the session', function (): void {
    $doc = Doc::factory()->create([
        'customer_data' => [
            'email' => 'to@example.test',
            'name' => 'Jane Doe',
        ],
    ]);

    $method = new ReflectionMethod(SendEmailAction::class, 'sendEmail');

    $method->invoke(null, $doc, ['to' => 'to@example.test']);

    expect($doc->emails()->count())->toBe(1);

    $notifications = session()->get('filament.notifications', []);
    expect($notifications)->not()->toBeEmpty();
    expect(collect($notifications)->last()['title'])->toBe('Email Sent');
});

it('passes subject message and cc overrides through the send email action', function (): void {
    Mail::fake();
    config()->set('docs.email.queue_enabled', false);

    $doc = Doc::factory()->create([
        'customer_data' => [
            'email' => 'to@example.test',
            'name' => 'Jane Doe',
        ],
    ]);

    $method = new ReflectionMethod(SendEmailAction::class, 'sendEmail');

    $method->invoke(null, $doc, [
        'to' => 'to@example.test',
        'cc' => 'copy@example.test',
        'subject' => 'Custom Subject',
        'message' => 'Custom body',
    ]);

    $email = $doc->emails()->latest('created_at')->first();

    expect($email)->not()->toBeNull();
    expect($email->subject)->toBe('Custom Subject');
    expect($email->body)->toBe('Custom body');
    expect($email->metadata)->toMatchArray(['cc' => 'copy@example.test']);

    Mail::assertSent(
        DocMail::class,
        fn (DocMail $mail): bool => count($mail->envelope()->cc ?? []) === 1
            && $mail->envelope()->cc[0]->address === 'copy@example.test'
            && $mail->envelope()->subject === 'Custom Subject'
    );
});

it('handles email failures and pushes an error notification to the session', function (): void {
    $doc = Doc::factory()->create([
        'customer_data' => [
            'email' => 'to@example.test',
            'name' => 'Jane Doe',
        ],
    ]);

    app()->bind(DocEmailService::class, fn (): never => throw new Exception('fail'));

    $method = new ReflectionMethod(SendEmailAction::class, 'sendEmail');

    $method->invoke(null, $doc, ['to' => 'to@example.test']);

    $notifications = session()->get('filament.notifications', []);
    expect($notifications)->not()->toBeEmpty();
    expect(collect($notifications)->last()['title'])->toBe('Email Failed');
});
