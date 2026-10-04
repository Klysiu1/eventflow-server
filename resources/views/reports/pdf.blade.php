<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Raport Bezpieczeństwa - {{ $report['event']['name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #1d4ed8;
            letter-spacing: -0.5px;
        }
        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .meta-grid {
            width: 100%;
        }
        .meta-grid td {
            vertical-align: top;
            width: 50%;
            padding: 4px;
        }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .summary-cards {
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .summary-card .num {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
        }
        .summary-card .desc {
            font-size: 9px;
            color: #3b82f6;
            text-transform: uppercase;
        }
        h2 {
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9px;
            text-transform: uppercase;
            text-align: left;
            padding: 8px 6px;
            border: 1px solid #cbd5e1;
        }
        table.data-table td {
            padding: 6px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .badge-safe {
            background-color: #dcfce7;
            color: #166534;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #cbd5e1;
            font-size: 9px;
            color: #94a3b8;
            width: 100%;
        }
        .signature-table {
            width: 100%;
            margin-top: 30px;
        }
        .signature-line {
            border-top: 1px dashed #94a3b8;
            padding-top: 5px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">EventFlow · Bezpieczeństwo i Przepływ Tłumu</div>
        <div class="subtitle">Oficjalny protokół analityczny obciążenia stref wydarzenia masowego</div>
    </div>

    <div class="meta-box">
        <table class="meta-grid">
            <tr>
                <td>
                    <div class="label">Wydarzenie</div>
                    <div class="value">{{ $report['event']['name'] }}</div>
                </td>
                <td>
                    <div class="label">Lokalizacja / Obiekt</div>
                    <div class="value">{{ $report['event']['venue'] }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Okres analizy</div>
                    <div class="value">{{ $report['range']['from'] }} — {{ $report['range']['to'] }}</div>
                </td>
                <td>
                    <div class="label">Maksymalna pojemność obiektu</div>
                    <div class="value">{{ number_format($report['event']['max_capacity'], 0, ',', ' ') }} osób</div>
                </td>
            </tr>
        </table>
    </div>

    <h2>Podsumowanie wskaźników operacyjnych</h2>
    <table class="summary-cards">
        <tr>
            <td style="width: 25%; padding: 4px;">
                <div class="summary-card">
                    <div class="num">{{ $report['summary']['total_zones'] }}</div>
                    <div class="desc">Monitorowane strefy</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="summary-card">
                    <div class="num">{{ number_format($report['summary']['peak_count'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="desc">Szczyt osób na obiekcie</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="summary-card">
                    <div class="num">{{ $report['summary']['peak_percent'] !== null ? $report['summary']['peak_percent'] . '%' : 'b.d.' }}</div>
                    <div class="desc">Maksymalne nasycenie</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="summary-card">
                    <div class="num">{{ $report['summary']['samples'] }}</div>
                    <div class="desc">Liczba odczytów IoT</div>
                </div>
            </td>
        </tr>
    </table>

    <h2>Szczegółowa analityka poszczególnych stref</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nazwa strefy</th>
                <th>Pojemność</th>
                <th>Próg kryt.</th>
                <th>Pomiary</th>
                <th>Szczyt (osoby)</th>
                <th>Szczyt (%)</th>
                <th>Średnia</th>
                <th>Incydenty kryt.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['zones'] as $zone)
                <tr>
                    <td><strong>{{ $zone['name'] }}</strong></td>
                    <td>{{ number_format($zone['capacity'], 0, ',', ' ') }}</td>
                    <td>{{ $zone['threshold'] }}%</td>
                    <td>{{ $zone['samples'] }}</td>
                    <td>{{ $zone['peak'] !== null ? number_format($zone['peak'], 0, ',', ' ') : 'b.d.' }}</td>
                    <td>
                        @if ($zone['peak_percent'] !== null)
                            <span class="badge {{ $zone['peak_percent'] >= $zone['threshold'] ? 'badge-danger' : 'badge-safe' }}">
                                {{ $zone['peak_percent'] }}%
                            </span>
                        @else
                            b.d.
                        @endif
                    </td>
                    <td>{{ $zone['average'] !== null ? number_format($zone['average'], 0, ',', ' ') : 'b.d.' }}</td>
                    <td>
                        @if ($zone['critical_samples'] > 0)
                            <span style="color: #b91c1c; font-weight: bold;">{{ $zone['critical_samples'] }}</span>
                        @else
                            <span style="color: #15803d;">0</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td style="width: 45%; vertical-align: top;">
                <div class="signature-line">
                    Podpis Koordynatora Bezpieczeństwa Imprezy
                </div>
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 45%; vertical-align: top;">
                <div class="signature-line">
                    Pieczęć i podpis Kierownika Służb Porządkowych
                </div>
            </td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td>Raport wygenerowany automatycznie przez system EventFlow: {{ $report['generated_at'] }}</td>
            <td style="text-align: right;">Identyfikator wydarzenia: {{ $report['event']['id'] }}</td>
        </tr>
    </table>
</body>
</html>
