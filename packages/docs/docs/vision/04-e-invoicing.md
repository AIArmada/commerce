---
title: E-Invoicing & Compliance
---

# E-Invoicing & Compliance

> **Document:** 04 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Implement **e-invoicing compliance** for Malaysian requirements (MyInvois/LHDN) with digital signatures, QR verification, and structured data formats.

---

## E-Invoicing Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    E-INVOICING FLOW                           │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐    │
│  │   Invoice   │────▶│  Validator  │────▶│  Formatter  │    │
│  │   Created   │     │             │     │  (UBL/XML)  │    │
│  └─────────────┘     └─────────────┘     └─────────────┘    │
│                                                  │            │
│                                                  ▼            │
│                                          ┌─────────────┐     │
│                                          │   Signer    │     │
│                                          │  (Digital)  │     │
│                                          └─────────────┘     │
│                                                  │            │
│                                                  ▼            │
│                                          ┌─────────────┐     │
│                                          │  MyInvois   │     │
│                                          │    API      │     │
│                                          └─────────────┘     │
│                                                  │            │
│                        ┌─────────────────────────┼───────┐   │
│                        │                         │       │   │
│                        ▼                         ▼       ▼   │
│                 ┌───────────┐            ┌───────────┐       │
│                 │  Success  │            │  Rejected │       │
│                 │ • UUID    │            │ • Errors  │       │
│                 │ • QR Code │            │ • Retry   │       │
│                 └───────────┘            └───────────┘       │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## MyInvois Integration

### EInvoiceService

```php
class EInvoiceService
{
    public function __construct(
        private MyInvoisClient $client,
        private DocumentValidator $validator,
        private UblFormatter $formatter,
        private DigitalSigner $signer,
    ) {}
    
    /**
     * Submit invoice to MyInvois
     */
    public function submit(Document $document): EInvoiceSubmission
    {
        // Validate document meets e-invoice requirements
        $this->validator->validateForEInvoice($document);
        
        // Format to UBL XML
        $ublDocument = $this->formatter->format($document);
        
        // Sign document
        $signedDocument = $this->signer->sign($ublDocument);
        
        // Submit to MyInvois
        $response = $this->client->submitDocument($signedDocument);
        
        // Store submission record
        $submission = EInvoiceSubmission::create([
            'document_id' => $document->id,
            'submission_uid' => $response->submissionUid,
            'long_id' => $response->longId,
            'internal_id' => $response->internalId,
            'status' => $response->status,
            'uuid' => $response->uuid,
            'qr_url' => $response->qrUrl,
            'submitted_at' => now(),
        ]);
        
        // Update document
        $document->update([
            'is_e_invoiced' => true,
            'e_invoice_id' => $response->uuid,
        ]);
        
        return $submission;
    }
    
    /**
     * Cancel submitted e-invoice
     */
    public function cancel(Document $document, string $reason): void
    {
        $submission = $document->eInvoiceSubmission;
        
        if (! $submission) {
            throw new EInvoiceException('Document has not been submitted');
        }
        
        $this->client->cancelDocument($submission->uuid, $reason);
        
        $submission->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }
    
    /**
     * Check submission status
     */
    public function checkStatus(EInvoiceSubmission $submission): string
    {
        $response = $this->client->getDocumentStatus($submission->uuid);
        
        $submission->update([
            'status' => $response->status,
            'validation_results' => $response->validationResults,
            'rejection_reason' => $response->rejectionReason,
        ]);
        
        return $response->status;
    }
}
```

---

## MyInvois API Client

### MyInvoisClient

```php
class MyInvoisClient
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;
    
    public function __construct(array $config)
    {
        $this->baseUrl = $config['sandbox'] 
            ? 'https://preprod-api.myinvois.hasil.gov.my'
            : 'https://api.myinvois.hasil.gov.my';
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
    }
    
    /**
     * Submit document to MyInvois
     */
    public function submitDocument(string $signedXml): SubmissionResponse
    {
        $response = Http::withToken($this->getAccessToken())
            ->withBody($signedXml, 'application/xml')
            ->post("{$this->baseUrl}/api/v1.0/documentsubmissions");
        
        if ($response->failed()) {
            throw new MyInvoisException($response->json('error'));
        }
        
        $data = $response->json();
        
        return new SubmissionResponse(
            submissionUid: $data['submissionUid'],
            longId: $data['acceptedDocuments'][0]['longId'] ?? null,
            internalId: $data['acceptedDocuments'][0]['internalId'] ?? null,
            status: $data['acceptedDocuments'][0]['status'] ?? 'pending',
            uuid: $data['acceptedDocuments'][0]['uuid'] ?? null,
            qrUrl: $data['acceptedDocuments'][0]['qrUrl'] ?? null,
        );
    }
    
    /**
     * Get document status
     */
    public function getDocumentStatus(string $uuid): StatusResponse
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/api/v1.0/documents/{$uuid}/raw");
        
        return new StatusResponse(
            status: $response->json('status'),
            validationResults: $response->json('validationResults'),
            rejectionReason: $response->json('rejectionReason'),
        );
    }
    
    /**
     * Cancel document
     */
    public function cancelDocument(string $uuid, string $reason): void
    {
        Http::withToken($this->getAccessToken())
            ->put("{$this->baseUrl}/api/v1.0/documents/state/{$uuid}/state", [
                'status' => 'cancelled',
                'reason' => $reason,
            ]);
    }
    
    private function getAccessToken(): string
    {
        if ($this->accessToken && ! $this->isTokenExpired()) {
            return $this->accessToken;
        }
        
        $response = Http::asForm()->post("{$this->baseUrl}/connect/token", [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => 'InvoicingAPI',
        ]);
        
        $this->accessToken = $response->json('access_token');
        
        return $this->accessToken;
    }
}
```

---

## UBL Document Formatter

### UblFormatter

```php
class UblFormatter
{
    /**
     * Format document to UBL 2.1 XML
     */
    public function format(Document $document): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        
        $invoice = $xml->createElement('Invoice');
        $invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        
        // Header
        $invoice->appendChild($this->createElement($xml, 'cbc:UBLVersionID', '2.1'));
        $invoice->appendChild($this->createElement($xml, 'cbc:ID', $document->number));
        $invoice->appendChild($this->createElement($xml, 'cbc:IssueDate', $document->issue_date->format('Y-m-d')));
        $invoice->appendChild($this->createElement($xml, 'cbc:InvoiceTypeCode', $this->getTypeCode($document)));
        $invoice->appendChild($this->createElement($xml, 'cbc:DocumentCurrencyCode', $document->currency));
        
        // Supplier
        $invoice->appendChild($this->formatSupplier($xml));
        
        // Customer
        $invoice->appendChild($this->formatCustomer($xml, $document));
        
        // Line items
        foreach ($document->items as $index => $item) {
            $invoice->appendChild($this->formatLineItem($xml, $item, $index + 1));
        }
        
        // Totals
        $invoice->appendChild($this->formatTaxTotal($xml, $document));
        $invoice->appendChild($this->formatLegalMonetaryTotal($xml, $document));
        
        $xml->appendChild($invoice);
        
        return $xml->saveXML();
    }
    
    private function formatSupplier(DOMDocument $xml): DOMElement
    {
        $supplier = $xml->createElement('cac:AccountingSupplierParty');
        $party = $xml->createElement('cac:Party');
        
        // TIN
        $partyId = $xml->createElement('cac:PartyIdentification');
        $partyId->appendChild($this->createElement($xml, 'cbc:ID', config('docs.einvoice.tin'), [
            'schemeID' => 'TIN',
        ]));
        $party->appendChild($partyId);
        
        // BRN
        $brnId = $xml->createElement('cac:PartyIdentification');
        $brnId->appendChild($this->createElement($xml, 'cbc:ID', config('docs.einvoice.brn'), [
            'schemeID' => 'BRN',
        ]));
        $party->appendChild($brnId);
        
        // Name
        $partyName = $xml->createElement('cac:PartyName');
        $partyName->appendChild($this->createElement($xml, 'cbc:Name', config('docs.einvoice.company_name')));
        $party->appendChild($partyName);
        
        // Address
        $party->appendChild($this->formatAddress($xml, config('docs.einvoice.address')));
        
        $supplier->appendChild($party);
        
        return $supplier;
    }
    
    private function formatLineItem(DOMDocument $xml, DocumentItem $item, int $lineNumber): DOMElement
    {
        $line = $xml->createElement('cac:InvoiceLine');
        
        $line->appendChild($this->createElement($xml, 'cbc:ID', (string) $lineNumber));
        $line->appendChild($this->createElement($xml, 'cbc:InvoicedQuantity', (string) $item->quantity, [
            'unitCode' => $item->unit ?? 'EA',
        ]));
        $line->appendChild($this->createElement($xml, 'cbc:LineExtensionAmount', 
            number_format($item->subtotal_minor / 100, 2, '.', ''),
            ['currencyID' => $item->document->currency]
        ));
        
        // Item details
        $itemElement = $xml->createElement('cac:Item');
        $itemElement->appendChild($this->createElement($xml, 'cbc:Description', $item->description));
        
        // Tax category
        $taxCategory = $xml->createElement('cac:ClassifiedTaxCategory');
        $taxCategory->appendChild($this->createElement($xml, 'cbc:ID', $this->getTaxCategoryCode($item->tax_rate)));
        $taxCategory->appendChild($this->createElement($xml, 'cbc:Percent', (string) $item->tax_rate));
        
        $taxScheme = $xml->createElement('cac:TaxScheme');
        $taxScheme->appendChild($this->createElement($xml, 'cbc:ID', 'OTH'));
        $taxCategory->appendChild($taxScheme);
        
        $itemElement->appendChild($taxCategory);
        $line->appendChild($itemElement);
        
        // Price
        $price = $xml->createElement('cac:Price');
        $price->appendChild($this->createElement($xml, 'cbc:PriceAmount',
            number_format($item->unit_price_minor / 100, 2, '.', ''),
            ['currencyID' => $item->document->currency]
        ));
        $line->appendChild($price);
        
        return $line;
    }
}
```

---

## Digital Signature

### DigitalSigner

```php
class DigitalSigner
{
    private string $certificatePath;
    private string $privateKeyPath;
    private string $privateKeyPassword;
    
    public function __construct(array $config)
    {
        $this->certificatePath = $config['certificate_path'];
        $this->privateKeyPath = $config['private_key_path'];
        $this->privateKeyPassword = $config['private_key_password'];
    }
    
    /**
     * Sign XML document with X509 certificate
     */
    public function sign(string $xmlContent): string
    {
        $doc = new DOMDocument();
        $doc->loadXML($xmlContent);
        
        // Create XMLSecurityDSig
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );
        
        // Load private key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->passphrase = $this->privateKeyPassword;
        $objKey->loadKey($this->privateKeyPath, true);
        
        // Sign
        $objDSig->sign($objKey);
        
        // Add certificate
        $objDSig->add509Cert(file_get_contents($this->certificatePath));
        
        // Append signature
        $objDSig->appendSignature($doc->documentElement);
        
        return $doc->saveXML();
    }
    
    /**
     * Verify signature
     */
    public function verify(string $signedXml): bool
    {
        $doc = new DOMDocument();
        $doc->loadXML($signedXml);
        
        $objDSig = new XMLSecurityDSig();
        $objDSig->locateSignature($doc);
        
        return $objDSig->verify($objDSig->locateKey());
    }
}
```

---

## QR Code Generation

### QrCodeGenerator

```php
class QrCodeGenerator
{
    /**
     * Generate verification QR code
     */
    public function generate(EInvoiceSubmission $submission): string
    {
        $verificationUrl = $submission->qr_url ?? $this->buildVerificationUrl($submission);
        
        return QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($verificationUrl);
    }
    
    private function buildVerificationUrl(EInvoiceSubmission $submission): string
    {
        $baseUrl = config('docs.einvoice.sandbox')
            ? 'https://preprod.myinvois.hasil.gov.my'
            : 'https://myinvois.hasil.gov.my';
        
        return "{$baseUrl}/{$submission->uuid}/share/{$submission->long_id}";
    }
}
```

---

## E-Invoice Submission Model

### EInvoiceSubmission

```php
/**
 * @property string $id
 * @property string $document_id
 * @property string $submission_uid
 * @property string|null $long_id
 * @property string|null $internal_id
 * @property string $status
 * @property string|null $uuid
 * @property string|null $qr_url
 * @property array|null $validation_results
 * @property string|null $rejection_reason
 * @property Carbon $submitted_at
 * @property Carbon|null $validated_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
class EInvoiceSubmission extends Model
{
    use HasUuids;
    
    protected $casts = [
        'validation_results' => 'array',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.einvoice_submissions')
            ?? config('docs.database.table_prefix', 'doc_') . 'einvoice_submissions';
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    public function isValid(): bool
    {
        return $this->status === 'valid';
    }
    
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'submitted']);
    }
    
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
```

---

## Database Schema

```php
// doc_einvoice_submissions table
Schema::create('doc_einvoice_submissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->string('submission_uid');
    $table->string('long_id')->nullable();
    $table->string('internal_id')->nullable();
    $table->string('status'); // pending, valid, rejected, cancelled
    $table->string('uuid')->nullable()->unique();
    $table->string('qr_url')->nullable();
    $table->json('validation_results')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamp('submitted_at');
    $table->timestamp('validated_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancel_reason')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index('status');
    $table->index('submission_uid');
});
```

---

## Configuration

```php
// config/docs.php
return [
    'einvoice' => [
        'enabled' => env('DOCS_EINVOICE_ENABLED', false),
        'sandbox' => env('DOCS_EINVOICE_SANDBOX', true),
        'client_id' => env('DOCS_EINVOICE_CLIENT_ID'),
        'client_secret' => env('DOCS_EINVOICE_CLIENT_SECRET'),
        'tin' => env('DOCS_EINVOICE_TIN'),
        'brn' => env('DOCS_EINVOICE_BRN'),
        'company_name' => env('DOCS_EINVOICE_COMPANY_NAME'),
        'address' => [
            'line1' => env('DOCS_EINVOICE_ADDRESS_LINE1'),
            'city' => env('DOCS_EINVOICE_CITY'),
            'state' => env('DOCS_EINVOICE_STATE'),
            'postal_code' => env('DOCS_EINVOICE_POSTAL'),
            'country' => 'MYS',
        ],
        'certificate_path' => env('DOCS_EINVOICE_CERT_PATH'),
        'private_key_path' => env('DOCS_EINVOICE_KEY_PATH'),
        'private_key_password' => env('DOCS_EINVOICE_KEY_PASSWORD'),
    ],
];
```

---

## Navigation

**Previous:** [03-document-types.md](03-document-types.md)  
**Next:** [05-email-integration.md](05-email-integration.md)
