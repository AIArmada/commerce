---
title: Contacting Usage
---

# Usage

## Add Traits to Your Model

```php
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;

class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}
```

## Create Email Contact

```php
use AIArmada\Contacting\Actions\CreateContactMethodAction;

app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'email',
    'purpose' => 'admin',
    'label' => 'Admin Email',
    'value' => 'admin@masjidcontoh.my',
    'is_primary' => true,
    'is_public' => true,
]);
```

## Create WhatsApp Contact

```php
app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'whatsapp',
    'purpose' => 'admin',
    'label' => 'Admin WhatsApp',
    'value' => '012-345 6789',
    'country_code' => 'MY',
    'is_primary' => true,
    'is_public' => true,
]);
```

## Create Website Contact

```php
app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'website',
    'label' => 'Official Website',
    'value' => 'https://example.com',
    'is_public' => true,
]);
```

## Create Social Profile (Facebook)

```php
use AIArmada\Contacting\Actions\CreateSocialProfileAction;

app(CreateSocialProfileAction::class)->execute($institution, [
    'platform' => 'facebook',
    'label' => 'Official Facebook Page',
    'handle' => '@masjidcontoh',
    'url' => 'https://facebook.com/masjidcontoh',
    'is_primary' => true,
    'is_public' => true,
]);
```

## Create Social Profile (Instagram/TikTok)

```php
// Instagram
app(CreateSocialProfileAction::class)->execute($institution, [
    'platform' => 'instagram',
    'handle' => '@masjidcontoh',
    'url' => 'https://instagram.com/masjidcontoh',
    'is_primary' => true,
]);

// TikTok
app(CreateSocialProfileAction::class)->execute($institution, [
    'platform' => 'tiktok',
    'handle' => '@masjidcontoh',
    'url' => 'https://tiktok.com/@masjidcontoh',
]);
```

## Set Primary Contact

```php
use AIArmada\Contacting\Actions\SetPrimaryContactMethodAction;

$email = $institution->contactMethods()->where('type', 'email')->first();
app(SetPrimaryContactMethodAction::class)->execute($email);
```

## Build Contact Links

```php
use AIArmada\Contacting\Actions\BuildContactLinksAction;

$links = app(BuildContactLinksAction::class)->forContactable($institution);

echo $links->mailtoUrl;    // mailto:admin@masjidcontoh.my
echo $links->telUrl;       // tel:+60123456789
echo $links->whatsappUrl;  // https://wa.me/60123456789
echo $links->websiteUrl;   // https://example.com
```

## Using Data Objects

```php
use AIArmada\Contacting\Data\ContactMethodData;

// Factory helpers
$email = ContactMethodData::email('admin@example.com');
$phone = ContactMethodData::phone('+60123456789', 'MY', 'admin');
$whatsapp = ContactMethodData::whatsapp('+60123456789', 'MY', 'support');
$website = ContactMethodData::website('https://example.com');

// Using the trait
$institution->addContactMethod(ContactMethodData::email('admin@example.com', 'admin'));
$institution->addSocialProfile(new SocialProfileData(
    platform: 'facebook',
    handle: 'myPage',
    url: 'https://facebook.com/myPage',
    isPrimary: true,
));
```

## Using Helper Methods on Model

```php
// Get all contact methods
$institution->contactMethods;

// Get only public contact methods
$institution->publicContactMethods;

// Get primary email
$institution->primaryContactMethod('email');

// Get contacts for a specific purpose
$institution->contactMethodsForPurpose('admin');

// Get contacts of a specific type
$institution->contactMethodsOfType('whatsapp');

// Get all social profiles
$institution->socialProfiles;

// Get public social profiles
$institution->publicSocialProfiles;

// Get primary Facebook profile
$institution->primarySocialProfile('facebook');
```

## Create Event Contact Snapshot

```php
use AIArmada\Contacting\Actions\CreateContactSnapshotAction;

// When event is published, snapshot the organizer's public contacts
app(CreateContactSnapshotAction::class)->fromBundle(
    snapshotable: $event,
    contactMethods: $event->organizer->publicContactMethods()->get(),
    socialProfiles: $event->organizer->publicSocialProfiles()->get(),
    reason: 'event_public_contact',
);
```

If the organizer changes their WhatsApp later, the event snapshot remains unchanged.