<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .sub { color: #64748b; margin-bottom: 18px; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #4f46e5; color: #4f46e5; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef2ff; text-align: left; }
        th, td { border: 1px solid #e2e8f0; padding: 4px 6px; vertical-align: top; }
        td.k { width: 32%; font-weight: bold; background: #f8fafc; }
        .empty { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="sub">{{ $report['subtitle'] }} &middot; Generated {{ now()->format('d M Y H:i') }}</div>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['heading'] }}</h2>
        @if (count($section['rows']) === 0)
            <div class="empty">Nothing recorded.</div>
        @elseif ($section['columns'] === null)
            <table>
                @foreach ($section['rows'] as [$label, $value])
                    <tr><td class="k">{{ $label }}</td><td>{{ $value ?? '-' }}</td></tr>
                @endforeach
            </table>
        @else
            <table>
                <tr>@foreach ($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
                @foreach ($section['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td>{{ $cell ?? '-' }}</td>@endforeach</tr>
                @endforeach
            </table>
        @endif
    @endforeach
</body>
</html>
