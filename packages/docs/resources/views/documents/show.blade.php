<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc->doc_number }}</title>
    <style>
        :root {
            color: #111827;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        body {
            background: #f3f4f6;
            margin: 0;
        }

        .doc-shell {
            background: #ffffff;
            box-sizing: border-box;
            margin: 32px auto;
            max-width: 960px;
            min-height: 1120px;
            padding: 48px;
        }

        .doc-block {
            margin-bottom: 32px;
        }

        .doc-header,
        .doc-parties {
            display: grid;
            gap: 32px;
            grid-template-columns: 1fr 1fr;
        }

        .doc-header {
            align-items: start;
            border-bottom: 2px solid #111827;
            padding-bottom: 24px;
        }

        h1,
        h2,
        h3 {
            letter-spacing: 0;
            margin: 0;
        }

        h1 {
            font-size: 36px;
            line-height: 1.1;
            text-transform: uppercase;
        }

        h2 {
            color: #4b5563;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        p {
            margin: 0 0 8px;
        }

        .doc-table {
            border-collapse: collapse;
            width: 100%;
        }

        .doc-table th {
            border-bottom: 2px solid #111827;
            color: #374151;
            font-size: 12px;
            padding: 10px 0;
            text-align: left;
            text-transform: uppercase;
        }

        .doc-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 0;
            vertical-align: top;
        }

        .doc-table small {
            color: #6b7280;
            display: block;
            margin-top: 4px;
        }

        .doc-number {
            text-align: right;
        }

        .doc-metadata,
        .doc-totals dl {
            display: grid;
            gap: 8px;
            margin: 0;
        }

        .doc-metadata div,
        .doc-totals div {
            display: flex;
            justify-content: space-between;
        }

        .doc-metadata dt,
        .doc-totals dt {
            color: #6b7280;
        }

        .doc-metadata dd,
        .doc-totals dd {
            font-weight: 600;
            margin: 0;
        }

        .doc-totals {
            margin-left: auto;
            max-width: 360px;
        }

        .doc-grand-total {
            border-top: 2px solid #111827;
            font-size: 20px;
            padding-top: 12px;
        }

        .doc-notes-terms {
            display: grid;
            gap: 24px;
            grid-template-columns: 1fr 1fr;
        }

        .doc-prose {
            color: #1f2937;
        }

        .doc-prose h1,
        .doc-prose h2,
        .doc-prose h3 {
            color: #111827;
            margin: 20px 0 8px;
            text-transform: none;
        }

        .doc-prose ul,
        .doc-prose ol {
            padding-left: 24px;
        }

        .doc-prose table {
            border-collapse: collapse;
            width: 100%;
        }

        .doc-prose th,
        .doc-prose td {
            border: 1px solid #d1d5db;
            padding: 8px;
        }

        .doc-section-break {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 36px 0;
        }

        .doc-page-break {
            break-after: page;
            page-break-after: always;
        }

        .doc-footer {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 13px;
            margin-top: 48px;
            padding-top: 16px;
            text-align: center;
        }

        @page {
            margin: 0;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .doc-shell {
                margin: 0;
                max-width: none;
                min-height: auto;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <main class="doc-shell">
        {!! $content !!}
    </main>
</body>
</html>
