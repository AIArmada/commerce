<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

enum DocTemplateBlockType: string
{
    case DocumentHeader = 'document_header';
    case Parties = 'parties';
    case DocumentMetadata = 'document_metadata';
    case RichBody = 'rich_body';
    case StaticRichText = 'static_rich_text';
    case LineItems = 'line_items';
    case Totals = 'totals';
    case NotesTerms = 'notes_terms';
    case SignaturePayment = 'signature_payment';
    case PageBreak = 'page_break';
    case Footer = 'footer';
}
