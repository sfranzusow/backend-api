<!doctype html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 22mm 18mm 24mm;
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
    <main class="document-content">{!! $content !!}</main>
</body>
</html>
