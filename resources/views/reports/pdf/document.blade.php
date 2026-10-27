<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: hindsiliguri;
            font-size: 10pt;
            color: #1b2a22;
        }

        h1 {
            font-size: 16pt;
            margin: 0;
            color: #14532d;
        }

        .sub {
            color: #5b6b62;
            margin: 2px 0 0;
        }

        h2 {
            font-size: 11pt;
            margin: 14px 0 4px;
            color: #14532d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        th {
            background-color: #e6f4ea;
            text-align: left;
            padding: 4px 6px;
            border: 0.5pt solid #b7d3c0;
        }

        td {
            padding: 4px 6px;
            border: 0.5pt solid #d7e5db;
        }

        th.num,
        td.num {
            text-align: right;
        }

        tr.total td {
            font-weight: bold;
            background-color: #f4faf6;
        }

        .notes {
            margin-top: 12px;
            font-size: 8.5pt;
            color: #5b6b62;
        }

        .foot {
            margin-top: 14px;
            font-size: 8pt;
            color: #8a9c93;
        }
    </style>
</head>

<body>

    <h1>{{ $doc['title'] }}</h1>
    <div class="sub">{{ $doc['farm'] }}</div>
    <div class="sub">{{ $doc['period'] }}</div>

    @foreach ($doc['sections'] as $section)
        @php
            $moneyCols = $section['money'] ?? [];
            $intCols = $section['int'] ?? [];
            $numericCols = array_merge($moneyCols, $intCols);
        @endphp

        <h2>{{ $section['heading'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['columns'] as $ci => $label)
                        <th class="{{ in_array($ci, $numericCols, true) ? 'num' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] as $row)
                    <tr class="{{ ($section['total'] ?? false) && $loop->last ? 'total' : '' }}">
                        @foreach (array_values($row) as $ci => $cell)
                            @if (in_array($ci, $moneyCols, true) && is_numeric($cell))
                                <td class="num">{{ \App\Support\Money::fmt($cell) }}</td>
                            @elseif (in_array($ci, $intCols, true))
                                <td class="num">{{ $cell }}</td>
                            @else
                                <td>{{ $cell }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if (!empty($doc['notes']))
        <div class="notes">
            <strong>টীকা</strong>
            @foreach ($doc['notes'] as $note)
                <div>• {{ $note }}</div>
            @endforeach
        </div>
    @endif

    <div class="foot">তৈরির সময়: {{ now()->format('d/m/Y H:i') }}, Gen Z Agro</div>

</body>

</html>
