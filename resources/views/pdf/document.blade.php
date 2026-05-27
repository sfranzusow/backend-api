<!doctype html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: {{ $hasHeader ? '34mm' : '22mm' }} 18mm {{ $hasFooter ? '30mm' : '20mm' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
        }

        .document-content {
            white-space: pre-wrap;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .document-layout-header,
        .document-layout-footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #4b5563;
            font-size: 8.5pt;
            line-height: 1.25;
        }

        .document-layout-header {
            top: -25mm;
            min-height: 18mm;
            padding-bottom: 4mm;
            border-bottom: 0.4pt solid #d1d5db;
        }

        .document-layout-footer {
            bottom: -20mm;
            min-height: 14mm;
            padding-top: 3mm;
            border-top: 0.4pt solid #d1d5db;
        }

        .document-layout-banner {
            display: block;
            width: 100%;
            max-height: 16mm;
            object-fit: contain;
            margin-bottom: 2mm;
        }

        .document-layout-footer .document-layout-banner {
            max-height: 10mm;
            margin: 0 0 1.5mm;
        }

        .document-layout-content {
            white-space: pre-wrap;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .document-content h1 {
            margin: 0 0 14pt;
            font-size: 18pt;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
        }

        .document-content h2 {
            margin: 16pt 0 7pt;
            font-size: 13pt;
            font-weight: 700;
            line-height: 1.25;
        }

        .document-content h3 {
            margin: 12pt 0 6pt;
            font-size: 11.5pt;
            font-weight: 700;
        }

        .document-content p {
            margin: 0 0 7pt;
        }

        .document-content ul,
        .document-content ol {
            margin: 0 0 8pt 18pt;
            padding: 0;
        }

        .document-content table {
            width: 100%;
            margin: 8pt 0 10pt;
            border-collapse: collapse;
        }

        .document-content th,
        .document-content td {
            padding: 5pt 6pt;
            border: 0.6pt solid #d1d5db;
            vertical-align: top;
        }

        .document-content tr {
            page-break-inside: avoid;
        }

        .document-content .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @if ($hasHeader)
        <header class="document-layout-header">
            @if ($headerBannerPath)
                <img class="document-layout-banner" src="{{ $headerBannerPath }}" alt="">
            @endif

            @if ($headerContent)
                <div class="document-layout-content">{!! $headerContent !!}</div>
            @endif
        </header>
    @endif

    @if ($hasFooter)
        <footer class="document-layout-footer">
            @if ($footerBannerPath)
                <img class="document-layout-banner" src="{{ $footerBannerPath }}" alt="">
            @endif

            @if ($footerContent)
                <div class="document-layout-content">{!! $footerContent !!}</div>
            @endif
        </footer>
    @endif

    <main class="document-content">{!! $content !!}</main>
</body>
</html>
