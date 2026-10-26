<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>جدول نتایج · {{ $tournament->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f6fa; color: #183044; font: 14px Tahoma, Arial, sans-serif; line-height: 1.8; }
        main { padding: 28px; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; }
        h1 { margin: 0; font-size: 24px; }
        p { margin: 8px 0 0; color: #536a7a; }
        nav { display: flex; gap: 10px; flex-wrap: wrap; }
        a, button { padding: 10px 16px; border: 1px solid #cad6df; border-radius: 8px; background: white; color: #183044; text-decoration: none; font: inherit; cursor: pointer; }
        a.primary { background: #087c79; border-color: #087c79; color: white; }
        .table-wrap { overflow: auto; max-height: 75vh; background: white; border: 1px solid #c6d2dc; border-radius: 10px; }
        table { width: 100%; min-width: 1650px; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        caption { text-align: right; padding: 14px 18px; background: #edf3f7; font-weight: bold; }
        th, td { padding: 12px 10px; border-left: 1px solid #d7e1e8; border-bottom: 1px solid #d7e1e8; vertical-align: middle; text-align: right; }
        thead th { position: sticky; top: 0; z-index: 1; background: #17364b; color: white; white-space: nowrap; }
        tbody tr:nth-child(even) td { background: #f1f6fa; }
        tbody tr:hover td { background: #e9f4f3; }
        .number { direction: ltr; text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .athlete { min-width: 170px; font-weight: bold; }
        .category { min-width: 185px; }
        .total { font-weight: bold; background: #e8f4f1 !important; }
        .winner { color: #08764e; font-weight: bold; }
        .empty { padding: 40px; text-align: center; background: white; border-radius: 10px; }
        @media print {
            @page { size: landscape; margin: 10mm; }
            body { background: white; }
            main { padding: 0; }
            nav { display: none; }
            .table-wrap { max-height: none; overflow: visible; border-radius: 0; }
            table { min-width: 0; font-size: 9px; table-layout: fixed; }
            th, td { padding: 6px 3px; white-space: normal !important; overflow-wrap: anywhere; }
            thead th { position: static; background: #edf3f7; color: #183044; }
            tr { break-inside: avoid; }
            .athlete, .category { min-width: 0; }
        }
    </style>
</head>
<body>
<main>
    <header>
        <div><h1>جدول نتایج {{ $tournament->name }}</h1><p>هر ردیف، نتیجهٔ یک ورزشکار یا تیم در یک رقابت است.</p></div>
        <nav>
            <a class="primary" href="{{ route('scoreboard.excel', $tournament) }}">دریافت Excel مرتب (.xlsx)</a>
            <a href="{{ route('scoreboard.results', [$tournament, 'download' => 1]) }}">دریافت جدول برای مرورگر</a>
            <a href="{{ route('scoreboard.export', $tournament) }}">دریافت CSV</a>
            <button type="button" onclick="window.print()">چاپ جدول</button>
            <a href="{{ route('tournaments.show', $tournament) }}">بازگشت به مسابقه</a>
        </nav>
    </header>
    @if ($table['rows'] === [])
        <p class="empty">هنوز نتیجهٔ تأییدشده‌ای ثبت نشده است.</p>
    @else
        <div class="table-wrap">
            <table>
                <caption>{{ count($table['rows']) }} ردیف نتیجه · نمره‌های جدول با سه رقم اعشار؛ مقدار دقیق در CSV</caption>
                <thead><tr>@foreach ($table['headers'] as $header)<th scope="col">{{ $header }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($table['rows'] as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            <td @class(['number' => in_array($index, [0, 4, 9, 11, 12, 13], true), 'category' => $index === 1, 'athlete' => $index === 6, 'total' => $index === 12, 'winner' => $index === 14 && $cell === 'برنده']) title="{{ $cell }}">{{ $cell === '' ? '—' : (in_array($index, [9, 11, 12], true) ? number_format((float) $cell, 3, '.', '') : $cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</main>
</body>
</html>
